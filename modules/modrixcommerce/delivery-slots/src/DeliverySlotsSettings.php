<?php

namespace EcoModular\DeliverySlots;

use App\Core\Settings\SettingsRepository;

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
        return (bool) $this->settings->get(self::PREFIX . '.enabled', true);
    }

    public function isRequired(): bool
    {
        return (bool) $this->settings->get(self::PREFIX . '.is_required', false);
    }

    public function maxAdvanceDays(): int
    {
        $days = (int) $this->settings->get(self::PREFIX . '.max_advance_days', 7);

        return max(1, min(30, $days));
    }

    public function sameDayCutoff(): string
    {
        $val = $this->settings->get(self::PREFIX . '.same_day_cutoff');

        return is_string($val) && preg_match('/^\d{2}:\d{2}$/', trim($val)) ? trim($val) : '14:00';
    }

    public function heading(): string
    {
        $val = $this->settings->get(self::PREFIX . '.heading');

        return is_string($val) && trim($val) !== '' ? trim($val) : 'Select Preferred Delivery Time';
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

        if (array_key_exists('is_required', $input)) {
            $this->settings->set(self::PREFIX . '.is_required', (bool) $input['is_required']);
        }

        if (array_key_exists('max_advance_days', $input)) {
            $this->settings->set(self::PREFIX . '.max_advance_days', max(1, min(30, (int) $input['max_advance_days'])));
        }

        if (array_key_exists('same_day_cutoff', $input)) {
            $val = trim((string) $input['same_day_cutoff']);
            if (preg_match('/^\d{2}:\d{2}$/', $val)) {
                $this->settings->set(self::PREFIX . '.same_day_cutoff', $val);
            }
        }

        if (array_key_exists('heading', $input)) {
            $this->settings->set(self::PREFIX . '.heading', trim((string) $input['heading']));
        }
    }
}
