<?php

namespace App\Http\Controllers\Admin;

use App\Core\Audit\AuditLogger;
use App\Domains\Payments\Jobs\ProcessPaymentWebhook;
use App\Domains\Payments\Models\PaymentWebhookEvent;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The payment event audit trail: every verified webhook received, what was
 * done with it, and a retry for failed ones.
 */
class PaymentWebhookEventController extends Controller
{
    private const STATUSES = [
        PaymentWebhookEvent::STATUS_RECEIVED,
        PaymentWebhookEvent::STATUS_PROCESSED,
        PaymentWebhookEvent::STATUS_IGNORED,
        PaymentWebhookEvent::STATUS_FAILED,
    ];

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(self::STATUSES)],
        ]);

        $events = PaymentWebhookEvent::query()
            ->with('order:id,number')
            ->when($filters['status'] ?? null, fn ($q, string $status) => $q->where('status', $status))
            ->latest('id')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (PaymentWebhookEvent $e) => [
                'id' => $e->id,
                'provider' => $e->provider,
                'event_id' => $e->event_id,
                'type' => $e->type,
                'status' => $e->status,
                'attempts' => $e->attempts,
                'error' => $e->error,
                'order_number' => $e->order?->number,
                'received_at' => $e->received_at->toIso8601String(),
                'processed_at' => $e->processed_at?->toIso8601String(),
            ]);

        return Inertia::render('admin/payments/webhooks', [
            'events' => $events,
            'filters' => ['status' => $filters['status'] ?? ''],
            'statuses' => self::STATUSES,
        ]);
    }

    public function retry(PaymentWebhookEvent $event, AuditLogger $audit): RedirectResponse
    {
        abort_unless($event->status === PaymentWebhookEvent::STATUS_FAILED, 422, 'Only failed events can be retried.');

        $event->forceFill(['status' => PaymentWebhookEvent::STATUS_RECEIVED, 'error' => null])->save();
        ProcessPaymentWebhook::dispatch($event->id);
        $audit->record('payments.webhook_retried', null, ['event_id' => $event->event_id, 'provider' => $event->provider]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Event queued for processing.')]);

        return back();
    }
}
