<?php

namespace App\Domains\Notifications;

use Illuminate\Container\Attributes\Singleton;
use InvalidArgumentException;

/**
 * What each kind of notification says and who may get it. Staff kinds need a
 * permission (owners always qualify); customer kinds go to the customer the
 * event is about. Templates use :placeholders filled from the params.
 * Modules may add their own kinds with register().
 */
#[Singleton]
final class NotificationKinds
{
    public const STAFF = 'staff';

    public const CUSTOMER = 'customer';

    /** @var array<string, array{audience: string, permission: string|null, icon: string, label: string, title: string, body: string}> */
    private array $kinds = [
        // Staff
        'order.placed' => ['audience' => self::STAFF, 'permission' => 'orders.view', 'icon' => 'shopping-bag', 'label' => 'New orders',
            'title' => 'New order :number', 'body' => ':customer placed an order for :total.'],
        'order.paid' => ['audience' => self::STAFF, 'permission' => 'orders.view', 'icon' => 'banknote', 'label' => 'Payments received',
            'title' => 'Payment received for :number', 'body' => ':total was paid.'],
        'payment.failed' => ['audience' => self::STAFF, 'permission' => 'orders.view', 'icon' => 'circle-alert', 'label' => 'Failed payments',
            'title' => 'Payment failed for :number', 'body' => 'The payment for :total did not go through.'],
        'order.cancelled' => ['audience' => self::STAFF, 'permission' => 'orders.view', 'icon' => 'circle-x', 'label' => 'Cancelled orders',
            'title' => 'Order :number was cancelled', 'body' => 'Order total :total.'],
        'order.refunded' => ['audience' => self::STAFF, 'permission' => 'orders.view', 'icon' => 'undo-2', 'label' => 'Refunds',
            'title' => 'Refund on :number', 'body' => 'Order :number was refunded.'],
        'stock.low' => ['audience' => self::STAFF, 'permission' => 'inventory.view', 'icon' => 'package', 'label' => 'Low stock',
            'title' => 'Low stock: :product', 'body' => ':sku has :on_hand left.'],
        'customer.new' => ['audience' => self::STAFF, 'permission' => 'customers.view', 'icon' => 'user-plus', 'label' => 'New customers',
            'title' => 'New customer :name', 'body' => ':email created an account.'],

        // Customers (their own orders)
        'customer.order_confirmed' => ['audience' => self::CUSTOMER, 'permission' => null, 'icon' => 'circle-check', 'label' => 'Order confirmed',
            'title' => 'Your order :number is confirmed', 'body' => 'We are preparing it now.'],
        'customer.order_shipped' => ['audience' => self::CUSTOMER, 'permission' => null, 'icon' => 'truck', 'label' => 'Order shipped',
            'title' => 'Your order :number has shipped', 'body' => 'It is on its way.'],
        'customer.out_for_delivery' => ['audience' => self::CUSTOMER, 'permission' => null, 'icon' => 'truck', 'label' => 'Out for delivery',
            'title' => 'Your order :number is out for delivery', 'body' => 'It arrives today.'],
        'customer.delivered' => ['audience' => self::CUSTOMER, 'permission' => null, 'icon' => 'package-check', 'label' => 'Delivered',
            'title' => 'Your order :number was delivered', 'body' => 'Enjoy your purchase.'],
        'customer.cancelled' => ['audience' => self::CUSTOMER, 'permission' => null, 'icon' => 'circle-x', 'label' => 'Order cancelled',
            'title' => 'Your order :number was cancelled', 'body' => 'Contact us if you have questions.'],
        'customer.refunded' => ['audience' => self::CUSTOMER, 'permission' => null, 'icon' => 'undo-2', 'label' => 'Refund',
            'title' => 'Refund for order :number', 'body' => 'Your refund is on its way.'],
        'customer.payment_failed' => ['audience' => self::CUSTOMER, 'permission' => null, 'icon' => 'circle-alert', 'label' => 'Payment failed',
            'title' => 'Payment for order :number failed', 'body' => 'Please try again or choose another payment method.'],
        'customer.downloads_ready' => ['audience' => self::CUSTOMER, 'permission' => null, 'icon' => 'download', 'label' => 'Downloads ready',
            'title' => 'Your downloads for :number are ready', 'body' => 'Open your order to download your files.'],
    ];

    /**
     * @return array<string, array{audience: string, permission: string|null, icon: string, label: string, title: string, body: string}>
     */
    public function all(): array
    {
        return $this->kinds;
    }

    /**
     * @return array{audience: string, permission: string|null, icon: string, label: string, title: string, body: string}|null
     */
    public function get(string $kind): ?array
    {
        return $this->kinds[$kind] ?? null;
    }

    /**
     * @param  array{audience: string, permission?: string|null, icon?: string, label: string, title: string, body: string}  $definition
     */
    public function register(string $kind, array $definition): void
    {
        if ($definition['audience'] === self::STAFF && empty($definition['permission'])) {
            throw new InvalidArgumentException("Staff notification kind [{$kind}] needs a permission.");
        }

        $this->kinds[$kind] = [
            'audience' => $definition['audience'],
            'permission' => $definition['permission'] ?? null,
            'icon' => $definition['icon'] ?? 'bell',
            'label' => $definition['label'],
            'title' => $definition['title'],
            'body' => $definition['body'],
        ];
    }

    /**
     * @param  array<string, scalar|null>  $params
     * @return array{title: string, body: string}
     */
    public function render(string $kind, array $params): array
    {
        $definition = $this->get($kind) ?? ['title' => $kind, 'body' => ''];
        $replace = [];

        foreach ($params as $key => $value) {
            $replace[':'.$key] = (string) $value;
        }

        // Longest placeholders first, so :on_hand is not cut by a shorter key.
        uksort($replace, fn (string $a, string $b) => strlen($b) <=> strlen($a));

        return ['title' => strtr($definition['title'], $replace), 'body' => strtr($definition['body'], $replace)];
    }
}
