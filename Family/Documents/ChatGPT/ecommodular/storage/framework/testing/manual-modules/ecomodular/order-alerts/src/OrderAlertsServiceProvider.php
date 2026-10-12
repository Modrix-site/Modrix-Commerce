<?php

namespace EcoModular\OrderAlerts;

use App\Domains\Orders\Events\OrderPaid;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class OrderAlertsServiceProvider extends ServiceProvider
{
    public const MODULE = 'ecomodular/order-alerts';

    public function boot(): void
    {
        Event::listen(OrderPaid::class, SendPaidOrderAlert::class);
    }
}
