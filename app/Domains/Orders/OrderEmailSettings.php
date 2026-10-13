<?php

namespace App\Domains\Orders;

use App\Core\Settings\SettingsRepository;

/**
 * Which status changes email the customer. Every email is on by default;
 * staff can still override the choice for a single change.
 */
final class OrderEmailSettings
{
    public const KINDS = [
        'confirmed' => 'Order confirmed (after review)',
        'payment_failed' => 'Payment failed',
        'shipped' => 'Shipped, with tracking',
        'out_for_delivery' => 'Out for delivery',
        'delivered' => 'Delivered',
        'delivery_failed' => 'Delivery failed',
        'returned' => 'Return received',
    ];

    public function __construct(private readonly SettingsRepository $settings) {}

    public function enabled(string $kind): bool
    {
        return (bool) $this->settings->get("orders.notify.{$kind}", true);
    }

    public function shouldSend(string $kind, ?bool $override): bool
    {
        return $override ?? $this->enabled($kind);
    }

    /**
     * @return array<string, bool>
     */
    public function all(): array
    {
        return array_map(fn (string $kind) => $this->enabled($kind), array_combine(array_keys(self::KINDS), array_keys(self::KINDS)));
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function update(array $values): void
    {
        $settings = [];
        foreach (array_keys(self::KINDS) as $kind) {
            if (array_key_exists($kind, $values)) {
                $settings["orders.notify.{$kind}"] = (bool) $values[$kind];
            }
        }

        if ($settings !== []) {
            $this->settings->setMany($settings);
        }
    }
}
