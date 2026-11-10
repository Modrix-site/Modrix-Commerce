<?php

namespace EcoModular\DeliverySlots\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Dates where deliveries are unavailable (holidays, store closures).
 */
class DeliveryBlackoutDate extends Model
{
    protected $table = 'delivery_blackout_dates';

    protected $guarded = [];

    protected $casts = [
        'date' => 'date',
    ];

    public static function isBlackedOut(string $date): bool
    {
        return static::query()->whereDate('date', $date)->exists();
    }
}
