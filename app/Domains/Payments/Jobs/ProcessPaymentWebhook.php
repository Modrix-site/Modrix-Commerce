<?php

namespace App\Domains\Payments\Jobs;

use App\Domains\Payments\Models\PaymentWebhookEvent;
use App\Domains\Payments\PaymentMethods;
use App\Domains\Payments\PaymentReconciler;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Str;
use Throwable;

/**
 * Applies one stored, verified webhook event. Safe to run more than once:
 * finished events are skipped and every effect is idempotent.
 */
final class ProcessPaymentWebhook implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [10, 60, 300, 900];

    public function __construct(public readonly int $eventId) {}

    public function handle(PaymentMethods $methods, PaymentReconciler $reconciler): void
    {
        $stored = PaymentWebhookEvent::query()->find($this->eventId);

        if ($stored === null || $stored->isFinished()) {
            return;
        }

        $stored->forceFill(['attempts' => $stored->attempts + 1])->save();
        $gateway = $methods->gatewayForProvider($stored->provider);

        if ($gateway === null) {
            $this->finish($stored, PaymentWebhookEvent::STATUS_FAILED, null, "No gateway is registered for provider [{$stored->provider}].");

            return;
        }

        try {
            $event = $gateway->normalizeEvent($stored->payload);

            $order = match (true) {
                $event->payment !== null => $reconciler->applyPayment($gateway, $event->payment),
                $event->refund !== null => $reconciler->applyRefund($gateway, $event->refund),
                default => null,
            };

            $this->finish($stored, $order === null ? PaymentWebhookEvent::STATUS_IGNORED : PaymentWebhookEvent::STATUS_PROCESSED, $order?->id);
        } catch (Throwable $e) {
            $this->finish($stored, PaymentWebhookEvent::STATUS_FAILED, null, Str::limit(class_basename($e).': '.$e->getMessage(), 1000));

            throw $e;
        }
    }

    private function finish(PaymentWebhookEvent $stored, string $status, ?int $orderId, ?string $error = null): void
    {
        $stored->forceFill([
            'status' => $status,
            'order_id' => $orderId ?? $stored->order_id,
            'error' => $error,
            'processed_at' => $status === PaymentWebhookEvent::STATUS_FAILED ? null : now(),
        ])->save();
    }
}
