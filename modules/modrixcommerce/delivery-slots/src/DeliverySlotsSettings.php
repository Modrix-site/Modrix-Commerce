<?php

namespace EcoModular\DeliverySlots;

use App\Core\Settings\SettingsRepository;
use App\Domains\Shipping\Models\ShippingMethod;
use Illuminate\Support\Facades\Schema;

/**
 * Settings configuration for Delivery Date & Time Slots module.
 */
final class DeliverySlotsSettings
{
    public const PREFIX = 'delivery_slots';

    public function __construct(
        private readonly SettingsRepository $settings,
    ) {}

    public function enabled(): bool
    {
        return (bool) $this->settings->get(self::PREFIX.'.enabled', true);
    }

    public function isRequired(): bool
    {
        return (bool) $this->settings->get(self::PREFIX.'.is_required', false);
    }

    public function maxAdvanceDays(): int
    {
        $days = (int) $this->settings->get(self::PREFIX.'.max_advance_days', 2);

        return max(1, min(30, $days));
    }

    public function sameDayCutoff(): string
    {
        $val = $this->settings->get(self::PREFIX.'.same_day_cutoff');

        return is_string($val) && preg_match('/^\d{2}:\d{2}$/', trim($val)) ? trim($val) : '14:00';
    }

    public function heading(): string
    {
        $val = $this->settings->get(self::PREFIX.'.heading');

        return is_string($val) && trim($val) !== '' ? trim($val) : 'Select Preferred Delivery Time';
    }

    /**
     * Raw saved per-shipping-method overrides.
     *
     * @return array<string, array{enabled?: bool, max_days?: int, show_time_slots?: bool}>
     */
    public function methodRules(): array
    {
        $raw = $this->settings->get(self::PREFIX.'.method_rules');
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : [];
        }

        return is_array($raw) ? $raw : [];
    }

    /**
     * Infer default slot behavior from a shipping method's code, name, description, and estimate.
     *
     * - Instant / 1-Day Delivery: 1 day only (Today, no next-day), and do not display time slots.
     * - Standard / 1-2 Days Delivery: 2 days only (1-2 days slot delivery) with time slots.
     *
     * @return array{enabled: bool, max_days: int, show_time_slots: bool}
     */
    public function inferDefaultRule(
        string $code,
        ?string $name = null,
        ?string $description = null,
        ?string $deliveryEstimate = null,
    ): array {
        $haystack = strtolower(trim("{$code} {$name} {$description} {$deliveryEstimate}"));

        $isInstantOneDay = str_contains($haystack, 'instant')
            || str_contains($haystack, 'same day')
            || str_contains($haystack, 'same-day')
            || (preg_match('/\b1\s*[-]?\s*day\b/', $haystack) && ! preg_match('/1\s*[-–to]+\s*2/', $haystack));

        if ($isInstantOneDay) {
            return [
                'enabled' => true,
                'max_days' => 1,
                'show_time_slots' => false,
            ];
        }

        $isOneToTwoDays = (bool) preg_match('/\b(1\s*[-–to]+\s*2|2)\s*days?\b/', $haystack)
            || str_contains($haystack, 'standard')
            || str_contains($haystack, 'next day')
            || str_contains($haystack, 'next-day');

        if ($isOneToTwoDays) {
            return [
                'enabled' => true,
                'max_days' => min(2, max(1, $this->maxAdvanceDays())),
                'show_time_slots' => true,
            ];
        }

        return [
            'enabled' => true,
            'max_days' => $this->maxAdvanceDays(),
            'show_time_slots' => true,
        ];
    }

    /**
     * Resolved rules for all configured shipping methods in the store.
     *
     * @return array<string, array{
     *     code: string,
     *     name: string,
     *     description: string|null,
     *     delivery_estimate: string|null,
     *     enabled: bool,
     *     max_days: int,
     *     show_time_slots: bool
     * }>
     */
    public function resolvedMethodRules(): array
    {
        $saved = $this->methodRules();
        $resolved = [];

        if (class_exists(ShippingMethod::class) && Schema::hasTable('shipping_methods')) {
            $methods = ShippingMethod::query()->orderBy('position')->get();
            foreach ($methods as $method) {
                $default = $this->inferDefaultRule(
                    $method->code,
                    $method->name,
                    $method->description,
                    $method->delivery_estimate,
                );
                $custom = $saved[$method->code] ?? [];

                $resolved[$method->code] = [
                    'code' => $method->code,
                    'name' => $method->name,
                    'description' => $method->description,
                    'delivery_estimate' => $method->delivery_estimate,
                    'enabled' => array_key_exists('enabled', $custom) ? (bool) $custom['enabled'] : $default['enabled'],
                    'max_days' => array_key_exists('max_days', $custom)
                        ? max(1, min(30, (int) $custom['max_days']))
                        : $default['max_days'],
                    'show_time_slots' => array_key_exists('show_time_slots', $custom)
                        ? (bool) $custom['show_time_slots']
                        : $default['show_time_slots'],
                ];
            }
        }

        foreach ($saved as $code => $custom) {
            if (! isset($resolved[$code])) {
                $default = $this->inferDefaultRule((string) $code);
                $resolved[(string) $code] = [
                    'code' => (string) $code,
                    'name' => ucwords(str_replace(['_', '-'], ' ', (string) $code)),
                    'description' => null,
                    'delivery_estimate' => null,
                    'enabled' => array_key_exists('enabled', $custom) ? (bool) $custom['enabled'] : $default['enabled'],
                    'max_days' => array_key_exists('max_days', $custom)
                        ? max(1, min(30, (int) $custom['max_days']))
                        : $default['max_days'],
                    'show_time_slots' => array_key_exists('show_time_slots', $custom)
                        ? (bool) $custom['show_time_slots']
                        : $default['show_time_slots'],
                ];
            }
        }

        return $resolved;
    }

    /**
     * Resolve the effective slot rule for a specific shipping method code.
     *
     * @return array{enabled: bool, max_days: int, show_time_slots: bool}
     */
    public function ruleForMethod(?string $methodCode): array
    {
        if (! $methodCode || trim($methodCode) === '') {
            return [
                'enabled' => true,
                'max_days' => $this->maxAdvanceDays(),
                'show_time_slots' => true,
            ];
        }

        $all = $this->resolvedMethodRules();
        if (isset($all[$methodCode])) {
            return [
                'enabled' => $all[$methodCode]['enabled'],
                'max_days' => $all[$methodCode]['max_days'],
                'show_time_slots' => $all[$methodCode]['show_time_slots'],
            ];
        }

        return $this->inferDefaultRule($methodCode);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled(),
            'is_required' => $this->isRequired(),
            'max_advance_days' => $this->maxAdvanceDays(),
            'same_day_cutoff' => $this->sameDayCutoff(),
            'heading' => $this->heading(),
            'method_rules' => $this->resolvedMethodRules(),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function save(array $input): void
    {
        if (array_key_exists('enabled', $input)) {
            $this->settings->set(self::PREFIX.'.enabled', (bool) $input['enabled']);
        }

        if (array_key_exists('is_required', $input)) {
            $this->settings->set(self::PREFIX.'.is_required', (bool) $input['is_required']);
        }

        if (array_key_exists('max_advance_days', $input)) {
            $this->settings->set(self::PREFIX.'.max_advance_days', max(1, min(30, (int) $input['max_advance_days'])));
        }

        if (array_key_exists('same_day_cutoff', $input)) {
            $val = trim((string) $input['same_day_cutoff']);
            if (preg_match('/^\d{2}:\d{2}$/', $val)) {
                $this->settings->set(self::PREFIX.'.same_day_cutoff', $val);
            }
        }

        if (array_key_exists('heading', $input)) {
            $this->settings->set(self::PREFIX.'.heading', trim((string) $input['heading']));
        }

        if (array_key_exists('method_rules', $input) && is_array($input['method_rules'])) {
            $sanitized = [];
            foreach ($input['method_rules'] as $code => $rule) {
                if (! is_string($code) || $code === '' || ! is_array($rule)) {
                    continue;
                }
                $sanitized[$code] = [
                    'enabled' => (bool) ($rule['enabled'] ?? true),
                    'max_days' => max(1, min(30, (int) ($rule['max_days'] ?? 2))),
                    'show_time_slots' => (bool) ($rule['show_time_slots'] ?? true),
                ];
            }
            $this->settings->set(self::PREFIX.'.method_rules', $sanitized);
        }
    }
}
