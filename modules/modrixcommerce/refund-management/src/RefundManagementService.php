<?php

namespace EcoModular\RefundManagement;

use App\Core\Extensions\ModuleSettingsSchema;
use App\Core\Settings\Countries;
use App\Core\Settings\StoreSettings;
use App\Domains\Orders\Documents\DocumentSettings;
use App\Domains\Orders\Documents\PdfRenderer;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\OrderAddress;
use App\Domains\Orders\Models\OrderEvent;
use App\Domains\Orders\Models\OrderItem;
use App\Domains\Orders\Models\Refund;
use App\Domains\Orders\Models\RefundItem;
use App\Domains\Orders\OrderRefunds;
use App\Domains\Orders\OrderWorkflow;
use App\Domains\Payments\Models\Payment;
use App\Domains\Pricing\Money;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use EcoModular\RefundManagement\Models\CreditMemo;
use EcoModular\RefundManagement\Models\RefundPaymentTrack;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RefundManagementService
{
    private const COUNTER = 'credit_memo';

    public function __construct(
        private readonly ModuleSettingsSchema $settings,
        private readonly OrderRefunds $orderRefunds,
        private readonly StoreSettings $store,
        private readonly DocumentSettings $documentSettings,
        private readonly PdfRenderer $pdf,
    ) {}

    public function creditMemoPrefix(): string
    {
        $prefix = $this->settings->of(RefundManagementServiceProvider::MODULE, 'credit_memo_prefix', 'CM-{year}-');

        return is_string($prefix) && trim($prefix) !== '' ? $prefix : 'CM-{year}-';
    }

    public function creditMemoPadding(): int
    {
        $padding = $this->settings->of(RefundManagementServiceProvider::MODULE, 'credit_memo_padding', 5);

        return is_int($padding) && $padding >= 1 && $padding <= 10 ? $padding : 5;
    }

    public function autoGenerateCreditMemo(): bool
    {
        return (bool) $this->settings->of(RefundManagementServiceProvider::MODULE, 'auto_generate_credit_memo', true);
    }

    public function defaultRestock(): bool
    {
        return (bool) $this->settings->of(RefundManagementServiceProvider::MODULE, 'default_restock', true);
    }

    /**
     * Processes a full or partial refund on an order, records the refund payment track,
     * and issues a credit memo when configured or requested.
     *
     * @param  array{
     *     mode?: string,
     *     amount?: string|null,
     *     reason: string,
     *     items?: array<int|string, array{quantity?: int|string, restock?: int|string|bool}>,
     *     shipping_refund?: string|null,
     *     adjustment_amount?: string|null,
     *     channel?: string|null,
     *     payout_status?: string|null,
     *     reference?: string|null,
     *     destination?: string|null,
     *     notes?: string|null,
     *     generate_credit_memo?: bool
     * }  $payload
     *
     * @throws ValidationException
     */
    public function processRefund(Order $order, User $actor, array $payload): Refund
    {
        $order->loadMissing('items', 'payments', 'refunds.items');

        $currency = $order->currency;
        $refundable = $order->refundable();

        if (! $refundable->isPositive()) {
            throw ValidationException::withMessages([
                'order' => 'This order has no remaining refundable balance.',
            ]);
        }

        $mode = (string) ($payload['mode'] ?? 'full');
        $shippingRefund = $this->parseMoney($payload['shipping_refund'] ?? '0', $currency, 'shipping_refund');
        $adjustment = $this->parseSignedMoney($payload['adjustment_amount'] ?? '0', $currency, 'adjustment_amount');

        $selectedQty = [];
        $restock = [];
        $itemsInput = is_array($payload['items'] ?? null) ? $payload['items'] : [];

        if ($mode === 'full') {
            $amount = $refundable;
            $useDefaultRestock = (bool) ($payload['restock_all'] ?? $this->defaultRestock());

            foreach ($order->items as $item) {
                $remainingRestock = max(0, $item->quantity - $item->restocked_quantity);
                $selectedQty[$item->id] = $item->quantity;
                if ($useDefaultRestock && $remainingRestock > 0) {
                    $restock[$item->id] = $remainingRestock;
                }
            }
        } elseif ($mode === 'items') {
            $linesTotal = Money::zero($currency);

            foreach ($order->items as $item) {
                $row = $itemsInput[$item->id] ?? $itemsInput[(string) $item->id] ?? null;
                if (! is_array($row)) {
                    continue;
                }

                $qty = max(0, (int) ($row['quantity'] ?? 0));
                $restockQty = max(0, (int) ($row['restock'] ?? 0));

                if ($qty > $item->quantity) {
                    throw ValidationException::withMessages([
                        'items' => "Refund quantity for {$item->product_name} exceeds ordered quantity.",
                    ]);
                }

                if ($qty > 0) {
                    $selectedQty[$item->id] = $qty;
                    $unitRefund = $this->effectiveUnitRefund($item, $currency);
                    $linesTotal = $linesTotal->plus($unitRefund->multipliedBy($qty)->rounded());
                }

                if ($restockQty > 0) {
                    $restock[$item->id] = $restockQty;
                }
            }

            $rawTotal = $linesTotal->plus($shippingRefund)->plus($adjustment)->rounded();

            if (isset($payload['amount']) && is_string($payload['amount']) && trim($payload['amount']) !== '') {
                $amount = $this->parseMoney($payload['amount'], $currency, 'amount');
            } else {
                if (! $rawTotal->isPositive()) {
                    throw ValidationException::withMessages([
                        'amount' => 'Select at least one item or enter a positive refund amount.',
                    ]);
                }
                $amount = $rawTotal->min($refundable);
            }
        } else {
            $amount = $this->parseMoney((string) ($payload['amount'] ?? ''), $currency, 'amount');

            foreach ($order->items as $item) {
                $row = $itemsInput[$item->id] ?? $itemsInput[(string) $item->id] ?? null;
                if (! is_array($row)) {
                    continue;
                }
                $restockQty = max(0, (int) ($row['restock'] ?? 0));
                if ($restockQty > 0) {
                    $restock[$item->id] = $restockQty;
                }
            }
        }

        if (! $amount->isPositive() || $amount->greaterThan($refundable)) {
            throw ValidationException::withMessages([
                'amount' => "Refund amount must be between 0.01 and {$refundable->format()}.",
            ]);
        }

        $refund = $this->orderRefunds->refund(
            $order,
            $actor,
            $amount,
            trim((string) $payload['reason']),
            $restock,
        );

        // Record non-restocked item lines on the refund if selected in partial item mode so credit memos have exact line items.
        foreach ($selectedQty as $itemId => $qty) {
            if (! isset($restock[$itemId]) && $qty > 0) {
                $exists = RefundItem::query()
                    ->where('refund_id', $refund->id)
                    ->where('order_item_id', $itemId)
                    ->exists();

                if (! $exists) {
                    $line = new RefundItem;
                    $line->forceFill([
                        'refund_id' => $refund->id,
                        'order_item_id' => $itemId,
                        'quantity' => $qty,
                        'restocked' => false,
                    ])->save();
                }
            }
        }

        $this->recordPaymentTrack($refund->fresh(), $actor, [
            'channel' => $payload['channel'] ?? null,
            'payout_status' => $payload['payout_status'] ?? null,
            'reference' => $payload['reference'] ?? null,
            'destination' => $payload['destination'] ?? null,
            'notes' => $payload['notes'] ?? null,
        ]);

        $shouldIssueMemo = array_key_exists('generate_credit_memo', $payload)
            ? (bool) $payload['generate_credit_memo']
            : $this->autoGenerateCreditMemo();

        if ($shouldIssueMemo && $refund->status !== Refund::STATUS_FAILED) {
            $this->issueCreditMemo($refund->fresh(), $actor, [
                'shipping_refund' => $shippingRefund->toDecimal(),
                'adjustment_amount' => $adjustment->toDecimal(),
                'notes' => isset($payload['notes']) && is_string($payload['notes']) ? trim($payload['notes']) : null,
            ]);
        }

        return $refund->fresh();
    }

    /**
     * Ensures a payment tracking row exists for a refund and updates it with any provided fields.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function recordPaymentTrack(Refund $refund, ?User $actor = null, array $attributes = []): RefundPaymentTrack
    {
        $order = Order::query()->with('payments')->findOrFail($refund->order_id);
        $payment = $refund->payment_id
            ? $order->payments->firstWhere('id', $refund->payment_id)
            : $order->payments->firstWhere('status', Payment::STATUS_PAID);

        $defaultChannel = $this->defaultChannel($order, $payment);
        $defaultStatus = match ($refund->status) {
            Refund::STATUS_COMPLETED => RefundPaymentTrack::STATUS_SETTLED,
            Refund::STATUS_FAILED => RefundPaymentTrack::STATUS_FAILED,
            default => RefundPaymentTrack::STATUS_PENDING,
        };

        $channel = is_string($attributes['channel'] ?? null) && array_key_exists($attributes['channel'], RefundPaymentTrack::CHANNELS)
            ? $attributes['channel']
            : $defaultChannel;

        $status = is_string($attributes['payout_status'] ?? null) && array_key_exists($attributes['payout_status'], RefundPaymentTrack::STATUSES)
            ? $attributes['payout_status']
            : $defaultStatus;

        $reference = isset($attributes['reference']) && is_string($attributes['reference']) && trim($attributes['reference']) !== ''
            ? trim($attributes['reference'])
            : $refund->provider_reference;

        $destination = isset($attributes['destination']) && is_string($attributes['destination']) && trim($attributes['destination']) !== ''
            ? trim($attributes['destination'])
            : $order->email;

        $notes = isset($attributes['notes']) && is_string($attributes['notes']) && trim($attributes['notes']) !== ''
            ? trim($attributes['notes'])
            : null;

        $track = RefundPaymentTrack::query()->where('refund_id', $refund->id)->first();

        if ($track === null) {
            $track = new RefundPaymentTrack;
            $track->forceFill([
                'refund_id' => $refund->id,
                'order_id' => $order->id,
                'channel' => $channel,
                'payout_status' => $status,
                'reference' => $reference,
                'destination' => $destination,
                'notes' => $notes,
                'processed_at' => $status === RefundPaymentTrack::STATUS_SETTLED ? now() : null,
                'updated_by' => $actor?->id,
            ])->save();

            return $track;
        }

        $newStatus = is_string($attributes['payout_status'] ?? null) && array_key_exists($attributes['payout_status'], RefundPaymentTrack::STATUSES)
            ? $attributes['payout_status']
            : $track->payout_status;

        $track->forceFill([
            'channel' => is_string($attributes['channel'] ?? null) && array_key_exists($attributes['channel'], RefundPaymentTrack::CHANNELS)
                ? $attributes['channel']
                : $track->channel,
            'payout_status' => $newStatus,
            'reference' => array_key_exists('reference', $attributes) ? ($reference ?? null) : $track->reference,
            'destination' => array_key_exists('destination', $attributes) ? ($destination ?? null) : $track->destination,
            'notes' => array_key_exists('notes', $attributes) ? $notes : $track->notes,
            'processed_at' => $newStatus === RefundPaymentTrack::STATUS_SETTLED
                ? ($track->processed_at ?? now())
                : ($newStatus === RefundPaymentTrack::STATUS_PENDING ? null : $track->processed_at),
            'updated_by' => $actor?->id ?? $track->updated_by,
        ])->save();

        if ($reference !== null && $refund->provider_reference !== $reference) {
            $refund->forceFill(['provider_reference' => $reference])->save();
        }

        return $track->fresh();
    }

    /**
     * Issues an immutable credit memo for a refund. Idempotent: returns the existing memo if already issued.
     *
     * @param  array{shipping_refund?: string, adjustment_amount?: string, notes?: string|null}  $options
     */
    public function issueCreditMemo(Refund $refund, ?User $actor = null, array $options = []): CreditMemo
    {
        return DB::transaction(function () use ($refund, $actor, $options) {
            $existing = CreditMemo::query()->where('refund_id', $refund->id)->lockForUpdate()->first();
            if ($existing !== null) {
                $notes = isset($options['notes']) && is_string($options['notes']) && trim($options['notes']) !== ''
                    ? trim($options['notes'])
                    : null;

                if ($notes !== null && $existing->notes !== $notes) {
                    $existing->forceFill(['notes' => $notes])->save();
                }

                return $existing->fresh();
            }

            $order = Order::query()->with(['items', 'addresses', 'invoice'])->findOrFail($refund->order_id);
            $refund->loadMissing('items');

            [$sequence, $number] = $this->allocateCreditMemoNumber();

            $currency = $order->currency;
            $refundAmount = Money::of($refund->amount, $currency);
            $shippingRefund = Money::of((string) ($options['shipping_refund'] ?? '0'), $currency);
            $adjustment = Money::of((string) ($options['adjustment_amount'] ?? '0'), $currency);

            $lines = [];
            $subtotal = Money::zero($currency);
            $taxTotal = Money::zero($currency);

            $itemsById = $order->items->keyBy('id');

            if ($refund->items->isNotEmpty()) {
                foreach ($refund->items as $refundItem) {
                    /** @var OrderItem|null $orderItem */
                    $orderItem = $itemsById->get($refundItem->order_item_id);
                    if ($orderItem === null) {
                        continue;
                    }

                    $qty = max(1, (int) $refundItem->quantity);
                    $unitPrice = Money::of($orderItem->unit_price, $currency);
                    $lineSubtotal = $unitPrice->multipliedBy($qty)->rounded();
                    $unitTax = $orderItem->quantity > 0
                        ? Money::of(
                            (string) BigDecimal::of($orderItem->tax_total)->dividedBy($orderItem->quantity, 4, RoundingMode::HALF_UP),
                            $currency,
                        )
                        : Money::zero($currency);
                    $lineTax = $unitTax->multipliedBy($qty)->rounded();
                    $lineTotal = $order->prices_include_tax ? $lineSubtotal : $lineSubtotal->plus($lineTax);

                    $subtotal = $subtotal->plus($lineSubtotal);
                    $taxTotal = $taxTotal->plus($lineTax);

                    $lines[] = [
                        'sku' => $orderItem->sku,
                        'name' => $orderItem->product_name,
                        'variant' => $orderItem->variant_name,
                        'quantity' => $qty,
                        'restocked' => (bool) $refundItem->restocked,
                        'unit_price' => $unitPrice->format(),
                        'tax' => $lineTax->format(),
                        'total' => $lineTotal->format(),
                    ];
                }
            }

            // If no specific items were attached or the refund amount differs from the item sum, record or balance appropriately.
            $computedItemsTotal = $order->prices_include_tax ? $subtotal : $subtotal->plus($taxTotal);
            $accountedTotal = $computedItemsTotal->plus($shippingRefund)->plus($adjustment);

            if ($lines === []) {
                $isFullOrderRefund = $refundAmount->equals($order->money('grand_total'));

                if ($isFullOrderRefund) {
                    foreach ($order->items as $orderItem) {
                        $lines[] = [
                            'sku' => $orderItem->sku,
                            'name' => $orderItem->product_name,
                            'variant' => $orderItem->variant_name,
                            'quantity' => $orderItem->quantity,
                            'restocked' => $orderItem->restocked_quantity > 0,
                            'unit_price' => $orderItem->money('unit_price', $currency)->format(),
                            'tax' => $orderItem->money('tax_total', $currency)->format(),
                            'total' => $orderItem->money('total', $currency)->format(),
                        ];
                    }
                    $subtotal = $order->money('subtotal');
                    $taxTotal = $order->money('tax_total');
                    $shippingRefund = $order->money('shipping_total');
                } else {
                    $subtotal = $refundAmount;
                    $lines[] = [
                        'sku' => 'REFUND',
                        'name' => 'Order refund — '.$refund->reason,
                        'variant' => null,
                        'quantity' => 1,
                        'restocked' => false,
                        'unit_price' => $refundAmount->format(),
                        'tax' => Money::zero($currency)->format(),
                        'total' => $refundAmount->format(),
                    ];
                }
            } elseif (! $accountedTotal->equals($refundAmount)) {
                $adjustment = $refundAmount->minus($computedItemsTotal)->minus($shippingRefund);
            }

            $totals = [
                'subtotal' => $subtotal->format(),
                'tax_total' => $taxTotal->format(),
                'shipping_refund' => $shippingRefund->isZero() ? null : $shippingRefund->format(),
                'adjustment_amount' => $adjustment->isZero() ? null : $adjustment->format(),
                'grand_total' => $refundAmount->format(),
                'prices_include_tax' => $order->prices_include_tax,
            ];

            $notes = isset($options['notes']) && is_string($options['notes']) && trim($options['notes']) !== ''
                ? trim($options['notes'])
                : $refund->reason;

            $memo = new CreditMemo;
            $memo->forceFill([
                'refund_id' => $refund->id,
                'order_id' => $order->id,
                'number' => $number,
                'sequence' => $sequence,
                'currency' => $currency,
                'subtotal' => $subtotal->toDecimal(),
                'tax_total' => $taxTotal->toDecimal(),
                'shipping_refund' => $shippingRefund->toDecimal(),
                'adjustment_amount' => $adjustment->toDecimal(),
                'grand_total' => $refundAmount->toDecimal(),
                'seller' => $this->sellerSnapshot(),
                'buyer' => $this->buyerSnapshot($order),
                'lines' => $lines,
                'totals' => $totals,
                'notes' => $notes,
                'issued_at' => now(),
                'issued_by' => $actor?->id ?? $refund->actor_id,
            ])->save();

            OrderEvent::query()->forceCreate([
                'order_id' => $order->id,
                'type' => 'credit_memo',
                'message' => "Credit memo {$number} issued for {$refundAmount->format()}.",
                'is_customer_visible' => true,
                'actor_id' => $actor?->id ?? $refund->actor_id,
                'metadata' => [
                    'credit_memo_id' => $memo->id,
                    'credit_memo_number' => $number,
                    'refund_id' => $refund->id,
                ],
            ]);

            return $memo;
        });
    }

    /**
     * Renders a printable PDF for the given credit memo using the platform's PDF renderer.
     */
    public function creditMemoPdf(CreditMemo $memo): string
    {
        $memo->loadMissing(['order.invoice', 'refund']);
        $track = RefundPaymentTrack::query()->where('refund_id', $memo->refund_id)->first();

        return $this->pdf->render('refund-management::credit-memo', [
            'title' => "Credit Memo {$memo->number}",
            'heading' => 'Credit Memo',
            'number' => $memo->number,
            'seller' => $memo->seller,
            'footer' => $memo->seller['footer'] ?? null,
            'memo' => $memo,
            'order' => $memo->order,
            'refund' => $memo->refund,
            'track' => $track,
        ]);
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function allocateCreditMemoNumber(): array
    {
        DB::table('document_counters')->insertOrIgnore([
            'name' => self::COUNTER,
            'next' => 1,
        ]);

        $sequence = (int) DB::table('document_counters')
            ->where('name', self::COUNTER)
            ->lockForUpdate()
            ->value('next');

        if ($sequence < 1) {
            $sequence = 1;
        }

        DB::table('document_counters')
            ->where('name', self::COUNTER)
            ->update(['next' => $sequence + 1]);

        $prefix = str_replace('{year}', (string) now()->year, $this->creditMemoPrefix());
        $number = $prefix.str_pad((string) $sequence, $this->creditMemoPadding(), '0', STR_PAD_LEFT);

        return [$sequence, $number];
    }

    /**
     * @return array<string, mixed>
     */
    private function sellerSnapshot(): array
    {
        $store = $this->store->toArray();
        $docs = $this->documentSettings->toArray();

        return [
            'name' => $store['store_name'] ?? 'Modrix Commerce',
            'email' => $store['store_email'] ?? null,
            'phone' => $store['store_phone'] ?? null,
            'country' => isset($store['country_code']) ? Countries::name($store['country_code']) : '',
            'address' => $docs['seller_address'] ?? null,
            'tax_id' => $docs['tax_id'] ?? null,
            'footer' => $docs['footer'] ?? null,
            'logo' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buyerSnapshot(Order $order): array
    {
        $billing = $order->addresses->firstWhere('type', OrderAddress::BILLING)
            ?? $order->addresses->firstWhere('type', OrderAddress::SHIPPING);

        return [
            'email' => $order->email,
            'phone' => $order->phone,
            'address' => $billing?->toPresentable(),
        ];
    }

    private function defaultChannel(Order $order, ?Payment $payment): string
    {
        if ($payment !== null && ! isset(OrderWorkflow::MANUAL_METHODS[$payment->method_code])) {
            return 'gateway';
        }

        if (isset(RefundPaymentTrack::CHANNELS[$order->payment_method_code])) {
            return $order->payment_method_code;
        }

        return 'bank_transfer';
    }

    private function effectiveUnitRefund(OrderItem $item, string $currency): Money
    {
        if ($item->quantity <= 0) {
            return Money::of($item->unit_price, $currency);
        }

        $perUnit = BigDecimal::of($item->total)->dividedBy($item->quantity, 4, RoundingMode::HALF_UP);

        return Money::of((string) $perUnit, $currency);
    }

    private function parseMoney(mixed $raw, string $currency, string $field): Money
    {
        $value = trim((string) $raw);
        if ($value === '' || ! preg_match('/^\d+(?:\.\d{1,4})?$/', $value)) {
            throw ValidationException::withMessages([$field => 'Enter a valid non-negative amount.']);
        }

        return Money::of($value, $currency)->rounded();
    }

    private function parseSignedMoney(mixed $raw, string $currency, string $field): Money
    {
        $value = trim((string) $raw);
        if ($value === '') {
            return Money::zero($currency);
        }

        if (! preg_match('/^-?\d+(?:\.\d{1,4})?$/', $value)) {
            throw ValidationException::withMessages([$field => 'Enter a valid adjustment amount.']);
        }

        return Money::of($value, $currency)->rounded();
    }
}
