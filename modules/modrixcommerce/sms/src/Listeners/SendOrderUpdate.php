<?php

namespace ModrixCommerce\Sms\Listeners;

use App\Core\Settings\StoreSettings;
use App\Domains\Orders\Events\OrderCancelled;
use App\Domains\Orders\Events\OrderDelivered;
use App\Domains\Orders\Events\OrderPaid;
use App\Domains\Orders\Events\OrderPlaced;
use App\Domains\Orders\Events\OrderRefunded;
use App\Domains\Orders\Events\OrderShipped;
use App\Domains\Orders\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\UniqueConstraintViolationException;
use ModrixCommerce\Sms\Models\SmsMessageLog;
use ModrixCommerce\Sms\Models\SmsPhone;
use ModrixCommerce\Sms\SmsMessage;
use ModrixCommerce\Sms\SmsSender;
use ModrixCommerce\Sms\SmsSettings;

/**
 * Texts the customer when their order changes. Only account orders whose
 * owner confirmed a number and kept order texts on are texted. Queued and
 * idempotent: the unique (order_id, event) row is claimed before sending, so
 * a repeated event never texts twice.
 */
final class SendOrderUpdate implements ShouldQueue
{
    public const EVENTS = [
        OrderPlaced::class => 'order_placed',
        OrderPaid::class => 'order_paid',
        OrderShipped::class => 'order_shipped',
        OrderDelivered::class => 'order_delivered',
        OrderCancelled::class => 'order_cancelled',
        OrderRefunded::class => 'order_refunded',
    ];

    public int $tries = 3;

    public function __construct(
        private readonly SmsSettings $settings,
        private readonly SmsSender $sender,
        private readonly StoreSettings $store,
    ) {}

    public function handle(OrderPlaced|OrderPaid|OrderShipped|OrderDelivered|OrderCancelled|OrderRefunded $event): void
    {
        $name = self::EVENTS[$event::class];

        if (! $this->settings->eventEnabled($name) || ($event instanceof OrderShipped && $event->notify === false)) {
            return;
        }

        $order = Order::query()->find($event->orderId);

        if ($order === null || $order->user_id === null) {
            return;
        }

        $phone = SmsPhone::query()->where('user_id', $order->user_id)->whereNotNull('verified_at')->where('order_updates', true)->first();

        if ($phone === null) {
            return;
        }

        $tracking = $order->shipments()->whereNotNull('tracking_number')->latest('id')->value('tracking_number');
        $values = [
            'store' => $this->store->name(),
            'name' => (string) ($order->user()->value('name') ?? ''),
            'order_number' => (string) $order->number,
            'total' => $order->money('grand_total')->format(),
            'order_url' => route('account.orders.show', $order),
            'tracking_number' => (string) $tracking,
            'tracking' => $tracking ? " Tracking: {$tracking}" : '',
        ];
        $body = SmsSender::render($this->settings->eventTemplate($name), $values);

        try {
            $log = SmsMessageLog::query()->create([
                'user_id' => $order->user_id, 'order_id' => $order->id, 'event' => $name,
                'purpose' => SmsMessage::PURPOSE_ORDER_UPDATE, 'to' => $phone->phone, 'body' => mb_substr($body, 0, 1000),
                'provider' => (string) $this->settings->value('provider'), 'status' => 'pending',
            ]);
        } catch (UniqueConstraintViolationException) {
            return; // already texted (or being texted) for this event
        }

        $this->sender->send(new SmsMessage($phone->phone, $body, SmsMessage::PURPOSE_ORDER_UPDATE, $values, $name), [], $log);
    }
}
