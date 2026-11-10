<?php

namespace EcoModular\DeliverySlots\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A configurable delivery window (e.g. Morning 9 AM - 1 PM).
 */
class DeliverySlot extends Model
{
    protected $table = 'delivery_slots';

    protected $guarded = [];

    protected $casts = [
        'cutoff_hours' => 'integer',
        'max_orders' => 'integer',
        'fee' => 'decimal:4',
        'is_active' => 'boolean',
        'days_of_week' => 'array',
        'order' => 'integer',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('order')->orderBy('start_time');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(OrderDeliverySchedule::class);
    }

    /**
     * Check if this slot runs on the given day of week (0 = Sunday ... 6 = Saturday).
     */
    public function runsOnDayOfWeek(int $dayOfWeek): bool
    {
        $days = (array) ($this->days_of_week ?? []);

        return empty($days) || in_array($dayOfWeek, array_map('intval', $days), true);
    }

    /**
     * Check if order placement has passed the cutoff time for this slot on a specific date.
     */
    public function isCutoffPassed(Carbon $deliveryDate, ?Carbon $now = null): bool
    {
        $now ??= now();

        if ($deliveryDate->isPast() && ! $deliveryDate->isToday()) {
            return true;
        }

        if ($deliveryDate->isToday()) {
            // Cutoff is slot start_time minus cutoff_hours
            $startTimeParts = explode(':', $this->start_time);
            $slotStartToday = today()->setHour((int) $startTimeParts[0])->setMinute((int) ($startTimeParts[1] ?? 0));
            $cutoffTime = $slotStartToday->subHours($this->cutoff_hours);

            if ($now->greaterThanOrEqualTo($cutoffTime)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks if this slot has remaining capacity for the given date.
     */
    public function hasCapacity(string $date): bool
    {
        if ($this->max_orders <= 0) {
            return true; // Uncapped
        }

        $booked = $this->schedules()->where('delivery_date', $date)->count();

        return $booked < $this->max_orders;
    }
}
