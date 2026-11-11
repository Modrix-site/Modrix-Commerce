<?php

namespace EcoModular\StorePickup;

use App\Core\Settings\SettingsRepository;

/**
 * Configuration settings for Store Pickup & Click & Collect module.
 */
final class StorePickupSettings
{
    public const PREFIX = 'store_pickup';

    public const COD_PAY_IN_STORE = 'pay_in_store';
    public const COD_HIDE = 'hide';
    public const COD_KEEP = 'keep';

    public function __construct(
        private readonly SettingsRepository $settings,
    ) {}

    public function enabled(): bool
    {
        return (bool) $this->settings->get(self::PREFIX.'.enabled', true);
    }

    public function shippingLabel(): string
    {
        $val = $this->settings->get(self::PREFIX.'.shipping_label');

        return is_string($val) && trim($val) !== '' ? trim($val) : 'In-Store & Branch Pickup';
    }

    public function allowAlternativeCollector(): bool
    {
        return (bool) $this->settings->get(self::PREFIX.'.allow_alternative_collector', true);
    }

    public function allowSchedule(): bool
    {
        return (bool) $this->settings->get(self::PREFIX.'.allow_schedule', true);
    }

    public function instructions(): string
    {
        $val = $this->settings->get(self::PREFIX.'.instructions');

        return is_string($val) && trim($val) !== ''
            ? trim($val)
            : 'Please bring your Order # and a valid photo ID to collect your items.';
    }

    public function codBehavior(): string
    {
        $val = $this->settings->get(self::PREFIX.'.cod_behavior', self::COD_PAY_IN_STORE);

        return in_array($val, [self::COD_PAY_IN_STORE, self::COD_HIDE, self::COD_KEEP], true)
            ? (string) $val
            : self::COD_PAY_IN_STORE;
    }

    public function payInStoreLabel(): string
    {
        $val = $this->settings->get(self::PREFIX.'.pay_in_store_label');

        return is_string($val) && trim($val) !== '' ? trim($val) : 'Pay in store';
    }

    public function payInStoreInstructions(): string
    {
        $val = $this->settings->get(self::PREFIX.'.pay_in_store_instructions');

        return is_string($val) && trim($val) !== ''
            ? trim($val)
            : 'Pay in store (cash or card) when you collect your order.';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled(),
            'shipping_label' => $this->shippingLabel(),
            'allow_alternative_collector' => $this->allowAlternativeCollector(),
            'allow_schedule' => $this->allowSchedule(),
            'instructions' => $this->instructions(),
            'cod_behavior' => $this->codBehavior(),
            'pay_in_store_label' => $this->payInStoreLabel(),
            'pay_in_store_instructions' => $this->payInStoreInstructions(),
        ];
    }
}
