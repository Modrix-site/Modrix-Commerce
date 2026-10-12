<?php

namespace App\Domains\Orders\Listeners;

use App\Core\Settings\StoreSettings;
use App\Domains\Orders\Events\OrderCancelled;
use App\Domains\Orders\Events\OrderPaid;
use App\Domains\Orders\Events\OrderPlaced;
use App\Domains\Orders\Events\OrderRefunded;
use App\Domains\Orders\Events\OrderShipped;
use App\Domains\Orders\Mail\OrderMail;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\OrderEvent;
use App\Domains\Payments\PaymentMethods;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

/**
 * Queued, idempotent order emails. Each email is recorded in the order
 * history; a retried or duplicated job finds that record and does not send
 * the same email twice. Refund emails are keyed by the refunded total so each
 * distinct refund produces one email.
 */
final class SendOrderEmails implements ShouldQueue
{
    public int $tries = 3;

    public function __construct(
        private readonly PaymentMethods $paymentMethods,
        private readonly StoreSettings $store,
    ) {}

    public function placed(OrderPlaced $event): void
    {
        $this->send($event->orderId, 'placed', withInstructions: true);
        $this->sendToStaff($event->orderId);
    }

    public function paid(OrderPaid $event): void
    {
        $this->send($event->orderId, 'paid');
    }

    public function shipped(OrderShipped $event): void
    {
        $this->send($event->orderId, 'shipped');
    }

    public function cancelled(OrderCancelled $event): void
    {
        $this->send($event->orderId, 'cancelled');
    }

    public function refunded(OrderRefunded $event): void
    {
        $order = Order::query()->find($event->orderId);

        if ($order !== null) {
            $this->send($event->orderId, 'refunded', key: 'refunded:'.$order->refunded_total);
        }
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            OrderPlaced::class => 'placed',
            OrderPaid::class => 'paid',
            OrderShipped::class => 'shipped',
            OrderCancelled::class => 'cancelled',
            OrderRefunded::class => 'refunded',
        ];
    }

    private function send(int $orderId, string $kind, bool $withInstructions = false, ?string $key = null): void
    {
        $key ??= $kind;
        $lock = Cache::lock("order-mail:{$orderId}:{$key}", 30);

        if (! $lock->get()) {
            return; // another worker is sending this exact email
        }

        try {
            $order = Order::query()->find($orderId);

            if ($order === null || $this->alreadySent($order, $key)) {
                return;
            }

            $instructions = $withInstructions ? $this->paymentMethods->definition($order->payment_method_code)?->instructions() : null;
            Mail::to($order->email)->send(new OrderMail($order, $kind, $instructions));

            OrderEvent::query()->forceCreate([
                'order_id' => $order->id,
                'type' => 'email',
                'message' => 'Email sent to customer: '.OrderMail::KINDS[$kind].'.',
                'is_customer_visible' => false,
                'metadata' => ['key' => $key],
            ]);
        } finally {
            $lock->release();
        }
    }

    private function sendToStaff(int $orderId): void
    {
        $email = $this->store->toArray()['store_email'];
        $order = Order::query()->find($orderId);

        if ($email === null || $order === null || $this->alreadySent($order, 'staff_new_order')) {
            return;
        }

        Mail::to($email)->send(new OrderMail($order, 'staff_new_order'));

        OrderEvent::query()->forceCreate([
            'order_id' => $order->id,
            'type' => 'email',
            'message' => 'New-order notification sent to the store.',
            'metadata' => ['key' => 'staff_new_order'],
        ]);
    }

    private function alreadySent(Order $order, string $key): bool
    {
        return $order->events()->where('type', 'email')->get()
            ->contains(fn (OrderEvent $e) => ($e->metadata['key'] ?? null) === $key);
    }
}
