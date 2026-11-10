<?php

namespace EcoModular\RefundManagement;

use App\Domains\Orders\Events\OrderRefunded;
use EcoModular\RefundManagement\Listeners\HandleOrderRefunded;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class RefundManagementServiceProvider extends ServiceProvider
{
    public const MODULE = 'modrixcommerce/refund-management';

    public function register(): void
    {
        $this->app->singleton(RefundManagementService::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'refund-management');
        Event::listen(OrderRefunded::class, HandleOrderRefunded::class);
    }
}
