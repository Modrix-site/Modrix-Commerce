<?php

namespace App\Domains\Payments;

use App\Domains\Payments\Contracts\PaymentGateway;
use App\Domains\Payments\Gateway\InvalidWebhook;
use App\Domains\Payments\Jobs\ProcessPaymentWebhook;
use App\Domains\Payments\Models\PaymentWebhookEvent;

/**
 * Accepts webhook deliveries: verify, store once, queue for processing.
 *
 * Nothing is stored for a delivery that fails verification. A redelivered
 * event that was already processed is acknowledged without side effects; one
 * that previously failed is queued again.
 */
final class WebhookInbox
{
    /**
     * @param  array<string, string>  $headers  lower-cased header names
     * @return bool true when the event was queued, false for an already-processed duplicate
     *
     * @throws InvalidWebhook
     */
    public function receive(PaymentGateway $gateway, string $payload, array $headers): bool
    {
        $event = $gateway->verifyWebhook($payload, $headers);

        PaymentWebhookEvent::query()->insertOrIgnore([
            'provider' => $gateway->provider(),
            'event_id' => (string) $event['id'],
            'type' => mb_substr((string) $event['type'], 0, 120),
            'payload' => json_encode($event, JSON_THROW_ON_ERROR),
            'status' => PaymentWebhookEvent::STATUS_RECEIVED,
            'attempts' => 0,
            'received_at' => now(),
        ]);

        /** @var PaymentWebhookEvent $stored */
        $stored = PaymentWebhookEvent::query()
            ->where('provider', $gateway->provider())
            ->where('event_id', (string) $event['id'])
            ->firstOrFail();

        if ($stored->isFinished()) {
            return false;
        }

        ProcessPaymentWebhook::dispatch($stored->id);

        return true;
    }
}
