<?php

namespace EcoModular\DeliverySlots;

use App\Core\Settings\StoreSettings;
use Carbon\Carbon;
use EcoModular\DeliverySlots\Models\DeliveryBlackoutDate;
use EcoModular\DeliverySlots\Models\DeliverySlot;

/**
 * Resolves available delivery dates and time slots for checkout.
 */
class DeliverySlotResolver
{
    public function __construct(
        private readonly DeliverySlotsSettings $settings,
        private readonly StoreSettings $storeSettings,
    ) {}

    /**
     * Store's current local time.
     */
    public function storeNow(): Carbon
    {
        $tz = $this->storeSettings->timezone() ?: config('app.timezone', 'UTC');

        return Carbon::instance(now($tz));
    }

    /**
     * Returns a list of candidate delivery dates for the storefront date picker.
     *
     * @return array<int, array{
     *     date: string,
     *     day_name: string,
     *     formatted: string,
     *     is_today: bool,
     *     is_tomorrow: bool,
     *     is_available: bool,
     *     available_slots_count: int,
     *     reason: string|null
     * }>
     */
    public function getAvailableDates(?int $daysLimit = null): array
    {
        if (! $this->settings->enabled()) {
            return [];
        }

        $now = $this->storeNow();
        $today = $now->copy()->startOfDay();
        $daysCount = $daysLimit !== null
            ? max(1, min(30, $daysLimit))
            : $this->settings->maxAdvanceDays();

        $activeSlots = DeliverySlot::query()->active()->get();
        if ($activeSlots->isEmpty()) {
            return [];
        }

        $endDate = $today->copy()->addDays($daysCount);
        $blackoutDates = DeliveryBlackoutDate::query()
            ->whereBetween('date', [$today->toDateString(), $endDate->toDateString()])
            ->pluck('reason', 'date')
            ->mapWithKeys(fn ($reason, $date) => [Carbon::parse($date)->toDateString() => $reason])
            ->all();

        $result = [];

        for ($i = 0; $i < $daysCount; $i++) {
            $candidate = $today->copy()->addDays($i);
            $dateStr = $candidate->toDateString();
            $dayOfWeek = $candidate->dayOfWeek; // 0 = Sunday, 6 = Saturday

            $isToday = $candidate->isSameDay($today);
            $isTomorrow = $candidate->isSameDay($today->copy()->addDay());

            // Check if blacked out
            if (isset($blackoutDates[$dateStr])) {
                $result[] = [
                    'date' => $dateStr,
                    'day_name' => $candidate->format('D'),
                    'formatted' => $candidate->format('M j'),
                    'is_today' => $isToday,
                    'is_tomorrow' => $isTomorrow,
                    'is_available' => false,
                    'available_slots_count' => 0,
                    'reason' => $blackoutDates[$dateStr] ?: 'Holiday / Unavailable',
                ];

                continue;
            }

            // Check same day cutoff
            if ($isToday) {
                $cutoffParts = explode(':', $this->settings->sameDayCutoff());
                $sameDayCutoffTime = $today->copy()->setHour((int) $cutoffParts[0])->setMinute((int) ($cutoffParts[1] ?? 0));
                if ($now->greaterThanOrEqualTo($sameDayCutoffTime)) {
                    $result[] = [
                        'date' => $dateStr,
                        'day_name' => $candidate->format('D'),
                        'formatted' => $candidate->format('M j'),
                        'is_today' => true,
                        'is_tomorrow' => false,
                        'is_available' => false,
                        'available_slots_count' => 0,
                        'reason' => 'Same-day cutoff passed',
                    ];

                    continue;
                }
            }

            // Find slots running on this day of week with capacity
            $matchingSlots = $activeSlots->filter(fn (DeliverySlot $slot) => $slot->runsOnDayOfWeek($dayOfWeek));
            $availableSlotsCount = 0;

            foreach ($matchingSlots as $slot) {
                if ($isToday && $slot->isCutoffPassed($candidate, $now)) {
                    continue;
                }
                if ($slot->hasCapacity($dateStr)) {
                    $availableSlotsCount++;
                }
            }

            $isAvailable = $availableSlotsCount > 0;

            $result[] = [
                'date' => $dateStr,
                'day_name' => $candidate->format('D'),
                'formatted' => $candidate->format('M j'),
                'is_today' => $isToday,
                'is_tomorrow' => $isTomorrow,
                'is_available' => $isAvailable,
                'available_slots_count' => $availableSlotsCount,
                'reason' => $isAvailable ? null : ($matchingSlots->isEmpty() ? 'No deliveries on this day' : 'All slots fully booked'),
            ];
        }

        return $result;
    }

    /**
     * Get slots for a specific date with availability status.
     *
     * @return array<int, array{
     *     id: int,
     *     name: string,
     *     start_time: string,
     *     end_time: string,
     *     formatted_time: string,
     *     fee: float,
     *     is_available: bool,
     *     remaining_orders: int|null,
     *     reason: string|null
     * }>
     */
    public function getSlotsForDate(string $dateStr): array
    {
        $now = $this->storeNow();
        $date = Carbon::parse($dateStr, $now->getTimezone())->startOfDay();

        if (DeliveryBlackoutDate::isBlackedOut($dateStr)) {
            return [];
        }

        $dayOfWeek = $date->dayOfWeek;
        $activeSlots = DeliverySlot::query()->active()->get();

        $slots = [];

        foreach ($activeSlots as $slot) {
            if (! $slot->runsOnDayOfWeek($dayOfWeek)) {
                continue;
            }

            $isAvailable = true;
            $reason = null;

            if ($date->isSameDay($now->startOfDay()) && $slot->isCutoffPassed($date, $now)) {
                $isAvailable = false;
                $reason = 'Booking cutoff has passed';
            } elseif (! $slot->hasCapacity($dateStr)) {
                $isAvailable = false;
                $reason = 'Slot is fully booked';
            }

            $booked = $slot->max_orders > 0
                ? $slot->schedules()->where('delivery_date', $dateStr)->count()
                : 0;

            $remaining = $slot->max_orders > 0 ? max(0, $slot->max_orders - $booked) : null;

            $startTimeFormatted = Carbon::createFromFormat('H:i:s', strlen($slot->start_time) === 5 ? "{$slot->start_time}:00" : $slot->start_time)->format('g:i A');
            $endTimeFormatted = Carbon::createFromFormat('H:i:s', strlen($slot->end_time) === 5 ? "{$slot->end_time}:00" : $slot->end_time)->format('g:i A');

            $slots[] = [
                'id' => $slot->id,
                'name' => $slot->name,
                'start_time' => $slot->start_time,
                'end_time' => $slot->end_time,
                'formatted_time' => "{$startTimeFormatted} - {$endTimeFormatted}",
                'fee' => (float) $slot->fee,
                'is_available' => $isAvailable,
                'remaining_orders' => $remaining,
                'reason' => $reason,
            ];
        }

        return $slots;
    }

    /**
     * Validate a user's slot selection.
     *
     * @return array{valid: bool, error: string|null, slot: DeliverySlot|null}
     */
    public function validateSelection(string $dateStr, ?int $slotId = null, ?int $maxAllowedDays = null): array
    {
        $now = $this->storeNow();
        $today = $now->copy()->startOfDay();

        try {
            $deliveryDate = Carbon::parse($dateStr, $now->getTimezone())->startOfDay();
        } catch (\Throwable) {
            return ['valid' => false, 'error' => 'Invalid delivery date format.', 'slot' => null];
        }

        if ($deliveryDate->isBefore($today)) {
            return ['valid' => false, 'error' => 'Delivery date cannot be in the past.', 'slot' => null];
        }

        $maxDate = $maxAllowedDays !== null
            ? $today->copy()->addDays(max(0, $maxAllowedDays - 1))
            : $today->copy()->addDays(max(2, $this->settings->maxAdvanceDays()));

        if ($deliveryDate->isAfter($maxDate)) {
            return ['valid' => false, 'error' => 'Delivery date exceeds the allowed advance booking window.', 'slot' => null];
        }

        if (DeliveryBlackoutDate::isBlackedOut($dateStr)) {
            return ['valid' => false, 'error' => 'Selected date is unavailable for delivery.', 'slot' => null];
        }

        if ($slotId === null) {
            return ['valid' => true, 'error' => null, 'slot' => null];
        }

        /** @var DeliverySlot|null $slot */
        $slot = DeliverySlot::query()->active()->find($slotId);
        if (! $slot) {
            return ['valid' => false, 'error' => 'Selected delivery time slot was not found.', 'slot' => null];
        }

        if (! $slot->runsOnDayOfWeek($deliveryDate->dayOfWeek)) {
            return ['valid' => false, 'error' => 'Selected time slot does not run on this day of the week.', 'slot' => null];
        }

        if ($deliveryDate->isSameDay($today) && $slot->isCutoffPassed($deliveryDate, $now)) {
            return ['valid' => false, 'error' => 'Booking cutoff for this slot has already passed for today.', 'slot' => null];
        }

        if (! $slot->hasCapacity($dateStr)) {
            return ['valid' => false, 'error' => 'Selected time slot is already fully booked for this date.', 'slot' => null];
        }

        return ['valid' => true, 'error' => null, 'slot' => $slot];
    }
}
