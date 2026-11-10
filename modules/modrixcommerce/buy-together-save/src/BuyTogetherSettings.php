<?php

namespace EcoModular\BuyTogetherSave;

use App\Core\Settings\SettingsRepository;

/**
 * Settings configuration for the Buy Together & Save module.
 */
final class BuyTogetherSettings
{
    public const PREFIX = 'buy_together';

    public function __construct(
        private readonly SettingsRepository $settings,
    ) {}

    public function enabled(): bool
    {
        return (bool) $this->settings->get(self::PREFIX . '.enabled', true);
    }

    public function heading(): string
    {
        $val = $this->settings->get(self::PREFIX . '.heading');

        return is_string($val) && trim($val) !== '' ? trim($val) : 'Buy Together & Save';
    }

    public function subheading(): string
    {
        $val = $this->settings->get(self::PREFIX . '.subheading');

        return is_string($val) && trim($val) !== ''
            ? trim($val)
            : 'Purchase these items together and get exclusive bundle savings';
    }

    public function defaultDiscountType(): string
    {
        $val = $this->settings->get(self::PREFIX . '.default_discount_type', 'percent');

        return in_array($val, ['percent', 'fixed'], true) ? $val : 'percent';
    }

    public function defaultDiscountValue(): float
    {
        $val = $this->settings->get(self::PREFIX . '.default_discount_value', 10);

        return is_numeric($val) ? (float) $val : 10.0;
    }

    public function autoBundleFallback(): bool
    {
        return (bool) $this->settings->get(self::PREFIX . '.auto_bundle_fallback', true);
    }

    public function ctaButtonText(): string
    {
        $val = $this->settings->get(self::PREFIX . '.cta_button_text');

        return is_string($val) && trim($val) !== '' ? trim($val) : 'Add Bundle to Cart & Save';
    }

    public function showSavingsBadge(): bool
    {
        return (bool) $this->settings->get(self::PREFIX . '.show_savings_badge', true);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled(),
            'heading' => $this->heading(),
            'subheading' => $this->subheading(),
            'default_discount_type' => $this->defaultDiscountType(),
            'default_discount_value' => $this->defaultDiscountValue(),
            'auto_bundle_fallback' => $this->autoBundleFallback(),
            'cta_button_text' => $this->ctaButtonText(),
            'show_savings_badge' => $this->showSavingsBadge(),
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

        if (array_key_exists('heading', $input)) {
            $this->settings->set(self::PREFIX . '.heading', trim((string) $input['heading']));
        }

        if (array_key_exists('subheading', $input)) {
            $this->settings->set(self::PREFIX . '.subheading', trim((string) $input['subheading']));
        }

        if (array_key_exists('default_discount_type', $input)) {
            $this->settings->set(self::PREFIX . '.default_discount_type', trim((string) $input['default_discount_type']));
        }

        if (array_key_exists('default_discount_value', $input)) {
            $this->settings->set(self::PREFIX . '.default_discount_value', (float) $input['default_discount_value']);
        }

        if (array_key_exists('auto_bundle_fallback', $input)) {
            $this->settings->set(self::PREFIX . '.auto_bundle_fallback', (bool) $input['auto_bundle_fallback']);
        }

        if (array_key_exists('cta_button_text', $input)) {
            $this->settings->set(self::PREFIX . '.cta_button_text', trim((string) $input['cta_button_text']));
        }

        if (array_key_exists('show_savings_badge', $input)) {
            $this->settings->set(self::PREFIX . '.show_savings_badge', (bool) $input['show_savings_badge']);
        }
    }
}
