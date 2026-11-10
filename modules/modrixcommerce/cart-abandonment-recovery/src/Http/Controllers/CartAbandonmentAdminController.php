<?php

namespace EcoModular\CartAbandonmentRecovery\Http\Controllers;

use App\Core\Audit\AuditLogger;
use App\Http\Controllers\Controller;
use EcoModular\CartAbandonmentRecovery\Models\AbandonedCart;
use EcoModular\CartAbandonmentRecovery\Services\CartRecoveryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CartAbandonmentAdminController extends Controller
{
    private const ROUTE = 'admin.modrixcommerce.cart-abandonment-recovery.';

    public function __construct(
        private readonly CartRecoveryService $service,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request): Response
    {
        $status = $request->string('status')->toString();
        $search = $request->string('search')->toString();

        $query = AbandonedCart::query()->with('recoveredOrder')->latest('id');

        if ($status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('email', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('recovery_token', 'like', "%{$search}%");
            });
        }

        $abandonedCarts = $query->paginate(25)->withQueryString()->through(fn (AbandonedCart $c) => [
            'id' => $c->id,
            'email' => $c->email,
            'customer_name' => $c->customer_name,
            'phone' => $c->phone,
            'formatted_subtotal' => $c->formattedSubtotal(),
            'currency' => $c->currency,
            'status' => $c->status,
            'items_count' => $c->items_count,
            'items_snapshot' => $c->items_snapshot ?? [],
            'abandoned_at' => $c->abandoned_at->diffForHumans(),
            'abandoned_date' => $c->abandoned_at->toFormattedDateString(),
            'recovered_at' => $c->recovered_at?->toFormattedDateString(),
            'recovered_order_number' => $c->recoveredOrder?->number,
            'recovery_revenue' => $c->recovery_revenue,
            'emails_sent_count' => $c->emails_sent_count,
            'last_email_sent_at' => $c->last_email_sent_at?->diffForHumans(),
            'next_email_scheduled_at' => $c->next_email_scheduled_at?->diffForHumans(),
            'recovery_url' => $c->recoveryUrl(),
            'send_email_url' => route(self::ROUTE.'send-email', $c),
            'mark_lost_url' => route(self::ROUTE.'mark-lost', $c),
            'mark_recovered_url' => route(self::ROUTE.'mark-recovered', $c),
        ]);

        return Inertia::render('module/modrixcommerce/cart-abandonment-recovery/admin/index', [
            'stats' => $this->service->getStats(),
            'carts' => $abandonedCarts,
            'filters' => [
                'status' => $status ?: 'all',
                'search' => $search,
            ],
            'scanUrl' => route(self::ROUTE.'scan'),
            'exportUrl' => route(self::ROUTE.'export'),
        ]);
    }

    public function scanNow(): RedirectResponse
    {
        $detected = $this->service->detectAndSyncAbandonedCarts();
        $dispatched = $this->service->processScheduledEmails();

        $this->audit->record('cart_abandonment.scanned', null, [
            'detected' => $detected,
            'dispatched' => $dispatched,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Scanner completed: {$detected} carts detected, {$dispatched} recovery emails sent.",
        ]);

        return back();
    }

    public function sendEmail(AbandonedCart $cart): RedirectResponse
    {
        $sent = $this->service->sendManualEmail($cart);

        if ($sent) {
            $this->audit->record('cart_abandonment.email_sent', null, [
                'abandoned_cart_id' => $cart->id,
                'email' => $cart->email,
            ]);

            Inertia::flash('toast', [
                'type' => 'success',
                'message' => "Recovery reminder sent to {$cart->email}.",
            ]);
        } else {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => "Failed to send email to {$cart->email}.",
            ]);
        }

        return back();
    }

    public function markLost(AbandonedCart $cart): RedirectResponse
    {
        $cart->markAsLost();
        $this->audit->record('cart_abandonment.marked_lost', null, ['abandoned_cart_id' => $cart->id]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Cart marked as lost.']);

        return back();
    }

    public function markRecovered(AbandonedCart $cart): RedirectResponse
    {
        $cart->update([
            'status' => AbandonedCart::STATUS_RECOVERED,
            'recovered_at' => now(),
            'recovery_revenue' => $cart->subtotal,
            'next_email_scheduled_at' => null,
        ]);

        $this->audit->record('cart_abandonment.marked_recovered', null, ['abandoned_cart_id' => $cart->id]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Cart marked as recovered.']);

        return back();
    }

    public function export(): StreamedResponse
    {
        $this->audit->record('cart_abandonment.exported', null, ['total' => AbandonedCart::query()->count()]);

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fputcsv($out, [
                'id',
                'email',
                'customer_name',
                'currency',
                'subtotal',
                'status',
                'items_count',
                'emails_sent',
                'abandoned_at',
                'recovered_at',
                'recovery_url',
            ], escape: '');

            AbandonedCart::query()->orderBy('id')->chunk(500, function ($chunk) use ($out) {
                foreach ($chunk as $cart) {
                    /** @var AbandonedCart $cart */
                    fputcsv($out, array_map(self::safe(...), [
                        (string) $cart->id,
                        $cart->email,
                        (string) ($cart->customer_name ?? ''),
                        $cart->currency,
                        (string) $cart->subtotal,
                        $cart->status,
                        (string) $cart->items_count,
                        (string) $cart->emails_sent_count,
                        $cart->abandoned_at->toIso8601String(),
                        $cart->recovered_at?->toIso8601String() ?? '',
                        $cart->recoveryUrl(),
                    ]), escape: '');
                }
            });

            fclose($out);
        }, 'abandoned-carts-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private static function safe(string $value): string
    {
        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }
}
