<?php

namespace EcoModular\CartAbandonmentRecovery\Services;

use App\Core\Audit\AuditLogger;
use App\Core\Extensions\ModuleSettingsSchema;
use App\Core\Settings\StoreSettings;
use App\Domains\Cart\CartManager;
use App\Domains\Cart\Models\Cart;
use App\Domains\Cart\Models\CartItem;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Orders\Models\Order;
use App\Domains\Pricing\Money;
use App\Models\User;
use EcoModular\CartAbandonmentRecovery\CartAbandonmentRecoveryServiceProvider as Module;
use EcoModular\CartAbandonmentRecovery\Mail\CartRecoveryMailable;
use EcoModular\CartAbandonmentRecovery\Models\AbandonedCart;
use EcoModular\CartAbandonmentRecovery\Models\AbandonedCartEmail;
use EcoModular\CartAbandonmentRecovery\Models\AbandonedCartUnsubscribe;
use Illuminate\Contracts\Session\Session;
use Illuminate\Session\SessionManager;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class CartRecoveryService
{
    public function __construct(
        private readonly ModuleSettingsSchema $settings,
        private readonly CartManager $cartManager,
        private readonly StoreSettings $storeSettings,
        private readonly AuditLogger $audit,
    ) {}

    public function detectAndSyncAbandonedCarts(): int
    {
        $enabled = (bool) $this->settings->of(Module::MODULE, 'enabled', true);
        if (! $enabled) {
            return 0;
        }

        $thresholdMinutes = max(5, (int) $this->settings->of(Module::MODULE, 'abandonment_threshold_minutes', 60));
        $cutoff = now()->subMinutes($thresholdMinutes);

        // Active carts that have items and haven't been modified recently
        $carts = Cart::query()
            ->whereHas('items')
            ->where('updated_at', '<=', $cutoff)
            ->with(['items.variant.product.images', 'user'])
            ->get();

        $count = 0;

        foreach ($carts as $cart) {
            /** @var Cart $cart */
            // Check if cart has an email: either from user or already tracked
            $email = $cart->user?->email;
            $customerName = $cart->user?->name;

            $existing = AbandonedCart::query()->where('cart_id', $cart->id)->first();

            if ($existing !== null) {
                // If it's already recovered or lost, skip
                if (in_array($existing->status, [AbandonedCart::STATUS_RECOVERED, AbandonedCart::STATUS_LOST, AbandonedCart::STATUS_UNSUBSCRIBED], true)) {
                    continue;
                }

                // If cart has been updated since abandonment, update snapshot and subtotal
                if ($cart->updated_at->gt($existing->abandoned_at)) {
                    $snapshot = $this->buildItemsSnapshot($cart);
                    $subtotal = $this->calculateSubtotal($snapshot);
                    $existing->update([
                        'subtotal' => $subtotal,
                        'items_count' => count($snapshot),
                        'items_snapshot' => $snapshot,
                        'abandoned_at' => $cart->updated_at,
                    ]);
                }

                continue;
            }

            // If we don't have an email for guest cart, we can't send emails yet
            if (empty($email)) {
                continue;
            }

            if (AbandonedCartUnsubscribe::isUnsubscribed($email)) {
                continue;
            }

            // Create new abandoned cart record
            $snapshot = $this->buildItemsSnapshot($cart);
            if (empty($snapshot)) {
                continue;
            }

            $subtotal = $this->calculateSubtotal($snapshot);
            $step1Hours = max(1, (int) $this->settings->of(Module::MODULE, 'step1_delay_hours', 1));
            $couponCode = trim((string) $this->settings->of(Module::MODULE, 'step1_discount_code', '')) ?: null;

            AbandonedCart::query()->create([
                'cart_id' => $cart->id,
                'user_id' => $cart->user_id,
                'email' => strtolower($email),
                'customer_name' => $customerName,
                'recovery_token' => Str::random(64),
                'currency' => $cart->currency,
                'subtotal' => $subtotal,
                'items_count' => count($snapshot),
                'items_snapshot' => $snapshot,
                'status' => AbandonedCart::STATUS_ABANDONED,
                'applied_coupon_code' => $couponCode,
                'abandoned_at' => $cart->updated_at,
                'emails_sent_count' => 0,
                'next_email_scheduled_at' => now()->addHours($step1Hours),
            ]);

            $count++;
        }

        return $count;
    }

    public function processScheduledEmails(): int
    {
        $enabled = (bool) $this->settings->of(Module::MODULE, 'enabled', true);
        if (! $enabled) {
            return 0;
        }

        $dueRecords = AbandonedCart::query()
            ->where('status', AbandonedCart::STATUS_ABANDONED)
            ->whereNotNull('next_email_scheduled_at')
            ->where('next_email_scheduled_at', '<=', now())
            ->limit(50)
            ->get();

        $sentCount = 0;

        foreach ($dueRecords as $record) {
            /** @var AbandonedCart $record */
            if (AbandonedCartUnsubscribe::isUnsubscribed($record->email)) {
                $record->update([
                    'status' => AbandonedCart::STATUS_UNSUBSCRIBED,
                    'next_email_scheduled_at' => null,
                ]);

                continue;
            }

            $stepToSend = $record->emails_sent_count + 1;

            if ($stepToSend === 1) {
                $subject = (string) $this->settings->of(Module::MODULE, 'step1_subject', 'Did you forget something in your cart?');
                $coupon = trim((string) $this->settings->of(Module::MODULE, 'step1_discount_code', '')) ?: null;
                $sent = $this->sendSequenceEmail($record, 1, $subject, $coupon);

                if ($sent) {
                    $sentCount++;
                    $step2Enabled = (bool) $this->settings->of(Module::MODULE, 'step2_enabled', true);
                    $step2Hours = max(2, (int) $this->settings->of(Module::MODULE, 'step2_delay_hours', 24));

                    $record->update([
                        'emails_sent_count' => 1,
                        'last_email_sent_at' => now(),
                        'next_email_scheduled_at' => $step2Enabled ? now()->addHours($step2Hours) : null,
                    ]);
                }
            } elseif ($stepToSend === 2) {
                $step2Enabled = (bool) $this->settings->of(Module::MODULE, 'step2_enabled', true);
                if (! $step2Enabled) {
                    $record->update(['next_email_scheduled_at' => null]);

                    continue;
                }

                $subject = (string) $this->settings->of(Module::MODULE, 'step2_subject', 'Your cart items are waiting for you');
                $coupon = trim((string) $this->settings->of(Module::MODULE, 'step2_discount_code', '')) ?: null;
                $sent = $this->sendSequenceEmail($record, 2, $subject, $coupon);

                if ($sent) {
                    $sentCount++;
                    $step3Enabled = (bool) $this->settings->of(Module::MODULE, 'step3_enabled', true);
                    $step3Hours = max(6, (int) $this->settings->of(Module::MODULE, 'step3_delay_hours', 72));

                    $record->update([
                        'emails_sent_count' => 2,
                        'last_email_sent_at' => now(),
                        'next_email_scheduled_at' => $step3Enabled ? now()->addHours($step3Hours) : null,
                    ]);
                }
            } elseif ($stepToSend === 3) {
                $step3Enabled = (bool) $this->settings->of(Module::MODULE, 'step3_enabled', true);
                if (! $step3Enabled) {
                    $record->update(['next_email_scheduled_at' => null]);

                    continue;
                }

                $subject = (string) $this->settings->of(Module::MODULE, 'step3_subject', 'Here is an exclusive discount to finish your order!');
                $coupon = trim((string) $this->settings->of(Module::MODULE, 'step3_discount_code', '')) ?: null;
                $sent = $this->sendSequenceEmail($record, 3, $subject, $coupon);

                if ($sent) {
                    $sentCount++;
                    $record->update([
                        'emails_sent_count' => 3,
                        'last_email_sent_at' => now(),
                        'next_email_scheduled_at' => null, // Final sequence step
                    ]);
                }
            } else {
                $record->update(['next_email_scheduled_at' => null]);
            }
        }

        return $sentCount;
    }

    public function sendSequenceEmail(AbandonedCart $record, int $step, string $subject, ?string $discountCode = null): bool
    {
        $emailRecord = AbandonedCartEmail::query()->create([
            'abandoned_cart_id' => $record->id,
            'step' => $step,
            'recipient_email' => $record->email,
            'subject' => $subject,
            'discount_code' => $discountCode,
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        try {
            Mail::to($record->email)->send(
                new CartRecoveryMailable($record, $step, $subject, $discountCode, $emailRecord)
            );

            return true;
        } catch (Throwable $e) {
            report($e);
            $emailRecord->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function sendManualEmail(AbandonedCart $record): bool
    {
        $subject = (string) $this->settings->of(Module::MODULE, 'step1_subject', 'Did you forget something in your cart?');
        $coupon = trim((string) $this->settings->of(Module::MODULE, 'step1_discount_code', '')) ?: null;

        $sent = $this->sendSequenceEmail($record, $record->emails_sent_count + 1, $subject, $coupon);
        if ($sent) {
            $record->update([
                'emails_sent_count' => $record->emails_sent_count + 1,
                'last_email_sent_at' => now(),
            ]);
        }

        return $sent;
    }

    public function captureEmail(Cart $cart, string $email, ?string $name = null, ?string $phone = null): AbandonedCart
    {
        $email = strtolower(trim($email));

        $snapshot = $this->buildItemsSnapshot($cart);
        $subtotal = $this->calculateSubtotal($snapshot);
        $step1Hours = max(1, (int) $this->settings->of(Module::MODULE, 'step1_delay_hours', 1));
        $couponCode = trim((string) $this->settings->of(Module::MODULE, 'step1_discount_code', '')) ?: null;

        $record = AbandonedCart::query()->where('cart_id', $cart->id)->first();

        if ($record !== null) {
            $record->update([
                'email' => $email,
                'customer_name' => $name ?: $record->customer_name,
                'phone' => $phone ?: $record->phone,
                'items_snapshot' => $snapshot,
                'items_count' => count($snapshot),
                'subtotal' => $subtotal,
            ]);

            return $record;
        }

        return AbandonedCart::query()->create([
            'cart_id' => $cart->id,
            'user_id' => $cart->user_id,
            'email' => $email,
            'customer_name' => $name,
            'phone' => $phone,
            'recovery_token' => Str::random(64),
            'currency' => $cart->currency,
            'subtotal' => $subtotal,
            'items_count' => count($snapshot),
            'items_snapshot' => $snapshot,
            'status' => AbandonedCart::STATUS_ABANDONED,
            'applied_coupon_code' => $couponCode,
            'abandoned_at' => now(),
            'emails_sent_count' => 0,
            'next_email_scheduled_at' => now()->addHours($step1Hours),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    public function restoreCartFromToken(string $token, Session|SessionManager $session, ?User $user): ?Cart
    {
        $record = AbandonedCart::query()->where('recovery_token', $token)->first();
        if ($record === null) {
            return null;
        }

        $record->recordClick();

        $sessionInstance = $session instanceof SessionManager ? $session->driver() : $session;
        $cart = $this->cartManager->current($sessionInstance, $user, create: true);
        if ($cart === null) {
            return null;
        }

        // Restore items from snapshot
        $items = $record->items_snapshot ?? [];

        foreach ($items as $item) {
            $variantId = $item['variant_id'] ?? null;
            $quantity = (int) ($item['quantity'] ?? 1);

            if (! $variantId || $quantity <= 0) {
                continue;
            }

            $variant = ProductVariant::query()->find($variantId);
            if ($variant instanceof ProductVariant && $variant->is_active) {
                try {
                    $this->cartManager->add($cart, $variant, $quantity);
                } catch (Throwable) {
                    // Variant might already be in cart or max reached; continue safely
                }
            }
        }

        // Apply coupon if present and valid
        if (! empty($record->applied_coupon_code)) {
            $cart->forceFill(['coupon_code' => $record->applied_coupon_code])->save();
        }

        return $cart;
    }

    public function recordOrderConversion(int $cartId, int $orderId): void
    {
        $order = Order::query()->find($orderId);
        if ($order === null) {
            return;
        }

        // Check if there is an abandoned cart matching this cart_id
        $record = AbandonedCart::query()->where('cart_id', $cartId)->first();

        // If not found by cart_id, check by email within recent 30 days
        if ($record === null && ! empty($order->email)) {
            $record = AbandonedCart::query()
                ->where('email', strtolower($order->email))
                ->where('status', AbandonedCart::STATUS_ABANDONED)
                ->where('abandoned_at', '>=', now()->subDays(30))
                ->latest('id')
                ->first();
        }

        if ($record !== null) {
            $record->markAsRecovered($order);
            $this->audit->record('cart_abandonment.recovered', null, [
                'abandoned_cart_id' => $record->id,
                'order_id' => $order->id,
                'revenue' => $order->grand_total,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getStats(): array
    {
        $currency = $this->storeSettings->currency();

        $totalAbandoned = AbandonedCart::query()->count();
        $totalRecovered = AbandonedCart::query()->where('status', AbandonedCart::STATUS_RECOVERED)->count();
        $totalLost = AbandonedCart::query()->where('status', AbandonedCart::STATUS_LOST)->count();
        $activeAbandoned = AbandonedCart::query()->where('status', AbandonedCart::STATUS_ABANDONED)->count();

        $sumRecovered = AbandonedCart::query()
            ->where('status', AbandonedCart::STATUS_RECOVERED)
            ->sum('recovery_revenue');
        $recoveredRevenue = (string) $sumRecovered;

        $sumAbandoned = AbandonedCart::query()
            ->where('status', AbandonedCart::STATUS_ABANDONED)
            ->sum('subtotal');
        $abandonedRevenue = (string) $sumAbandoned;

        $recoveryRate = $totalAbandoned > 0
            ? round(($totalRecovered / $totalAbandoned) * 100, 1)
            : 0;

        $totalEmailsSent = AbandonedCartEmail::query()->where('status', 'sent')->count();
        $totalEmailsOpened = AbandonedCartEmail::query()->whereNotNull('opened_at')->count();
        $totalEmailsClicked = AbandonedCartEmail::query()->whereNotNull('clicked_at')->count();

        $openRate = $totalEmailsSent > 0
            ? round(($totalEmailsOpened / $totalEmailsSent) * 100, 1)
            : 0;

        $clickRate = $totalEmailsSent > 0
            ? round(($totalEmailsClicked / $totalEmailsSent) * 100, 1)
            : 0;

        return [
            'currency' => $currency,
            'total_abandoned' => $totalAbandoned,
            'total_recovered' => $totalRecovered,
            'total_lost' => $totalLost,
            'active_abandoned' => $activeAbandoned,
            'recovered_revenue' => (float) $recoveredRevenue,
            'formatted_recovered_revenue' => Money::of($recoveredRevenue, $currency)->format(),
            'abandoned_revenue' => (float) $abandonedRevenue,
            'formatted_abandoned_revenue' => Money::of($abandonedRevenue, $currency)->format(),
            'recovery_rate' => $recoveryRate,
            'total_emails_sent' => $totalEmailsSent,
            'open_rate' => $openRate,
            'click_rate' => $clickRate,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildItemsSnapshot(Cart $cart): array
    {
        $snapshot = [];
        $cart->loadMissing(['items.variant.product.images']);

        foreach ($cart->items as $item) {
            /** @var CartItem $item */
            $variant = $item->variant;
            $product = $variant->product;
            $unitPrice = $item->seen_unit_price ?? $variant->priceIn($cart->currency)?->toDecimal() ?? '0.0000';
            $formattedPrice = Money::of($unitPrice, $cart->currency)->format();

            $image = $product->images->firstWhere('variant_id', $variant->id) ?? $product->images->first();
            $imageUrl = $image !== null ? $image->url() : null;

            $snapshot[] = [
                'variant_id' => $variant->id,
                'product_id' => $product->id,
                'product_name' => $product->name,
                'variant_name' => $variant->name,
                'sku' => $variant->sku,
                'quantity' => $item->quantity,
                'unit_price' => $unitPrice,
                'formatted_price' => $formattedPrice,
                'image_url' => $imageUrl,
                'url' => route('storefront.product', ['slug' => $product->slug]),
            ];
        }

        return $snapshot;
    }

    /**
     * @param  list<array<string, mixed>>  $snapshot
     */
    private function calculateSubtotal(array $snapshot): string
    {
        $sum = 0.0;
        foreach ($snapshot as $item) {
            $price = (float) ($item['unit_price'] ?? 0);
            $qty = (int) ($item['quantity'] ?? 1);
            $sum += $price * $qty;
        }

        return number_format($sum, 4, '.', '');
    }
}
