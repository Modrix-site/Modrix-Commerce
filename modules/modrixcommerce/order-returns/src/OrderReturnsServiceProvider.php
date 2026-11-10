<?php

namespace EcoModular\OrderReturns;

use App\Core\Extensions\StorefrontSlots;
use App\Core\Storefront\AccountMenu;
use App\Domains\Orders\Models\Order;
use Illuminate\Support\ServiceProvider;

/**
 * Shows the return request on the customer's order page. Review happens in
 * the admin list declared by module.json.
 */
class OrderReturnsServiceProvider extends ServiceProvider
{
    public const MODULE = 'modrixcommerce/order-returns';

    public function register(): void
    {
        $this->app->singleton(ReturnRequests::class);
    }

    public function boot(StorefrontSlots $slots, AccountMenu $menu): void
    {
        $menu->add('returns', 'Returns', 'modrixcommerce.order-returns.index', 'orders', 10);

        $slots->register('account.order', self::MODULE, 'ReturnRequest', function (array $context) {
            $order = $context['order'] ?? null;

            return $order instanceof Order ? $this->app->make(ReturnRequests::class)->present($order) : null;
        });
    }
}
