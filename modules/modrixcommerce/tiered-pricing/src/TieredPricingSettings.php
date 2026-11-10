<?php

namespace EcoModular\TieredPricing;

use App\Core\Settings\SettingsRepository;

/**
 * Settings configuration for Tiered Pricing & Volume Discounts.
 */
final class TieredPricingSettings
{
    public const PREFIX = 'tiered_pricing';

    public function __construct(
        private readonly SettingsRepository $settings,
    ) {}

    public function enabled(): bool
    {
        return (bool) $this->settings->get(self::PREFIX . '.enabled', true);
    }

    public function tableHeading(): string
    {
        $val = $this->settings->get(self::PREFIX . '.table_heading');

        return is_string($val) && trim($val) !== '' ? trim($val) : 'Buy in Bulk & Save';
    }

    public function tableSubheading(): string
    {
        $val = $this->settings->get(self::PREFIX . '.table_subheading');

        return is_string($val) && trim($val) !== ''
            ? trim($val)
            : 'Purchase multiple quantities to unlock tiered volume discounts';
    }

    public function showSavingsBadge(): bool
    {
        return (bool) $this->settings->get(self::PREFIX . '.show_savings_badge', true);
    }

    public function highlightActiveTier(): bool
    {
        return (bool) $this->settings->get(self::PREFIX . '.highlight_active_tier', true);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled(),
            'table_heading' => $this->tableHeading(),
            'table_subheading' => $this->tableSubheading(),
            'show_savings_badge' => $this->showSavingsBadge(),
            'highlight_active_tier' => $this->highlightActiveTier(),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function save(array $input): void
    {
        if (array_key_exists('enabled', $input)) {
            $this->settings->set(self::PREFIX . '.enabled', (bool) $input['enabled']);
        }

        if (array_key_exists('table_heading', $input)) {
            $this->settings->set(self::PREFIX . '.table_heading', trim((string) $input['table_heading']));
        }

        if (array_key_exists('table_subheading', $input)) {
            $this->settings->set(self::PREFIX . '.table_subheading', trim((string) $input['table_subheading']));
        }

        if (array_key_exists('show_savings_badge', $input)) {
            $this->settings->set(self::PREFIX . '.show_savings_badge', (bool) $input['show_savings_badge']);
        }

        if (array_key_exists('highlight_active_tier', $input)) {
            $this->settings->set(self::PREFIX . '.highlight_active_tier', (bool) $input['highlight_active_tier']);
        }
    }
}
