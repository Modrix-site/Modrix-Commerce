<?php

namespace EcoModular\SocialProofNotifications\Listeners;

use App\Domains\Orders\Events\CartCheckedOut;
use App\Domains\Orders\Models\Order;
use EcoModular\SocialProofNotifications\Services\SocialProofService;
use Throwable;

class HandleOrderCheckedOut
{
    public function __construct(
        protected SocialProofService $socialProofService
    ) {}

    public function handle(CartCheckedOut $event): void
    {
        try {
            /** @var Order|null $order */
            $order = Order::query()
                ->with(['items', 'shippingAddress', 'billingAddress', 'customer'])
                ->find($event->orderId);

            if ($order !== null) {
                $this->socialProofService->recordOrder($order);
            }
        } catch (Throwable) {
            // Keep checkout fast and safe: never fail checkout on marketing proof logging
        }
    }
}
