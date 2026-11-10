<?php

namespace EcoModular\FlashSales\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A time-limited sale: a default discount (percentage or fixed amount) for
 * the products in it, between `starts_at` (inclusive) and `ends_at`.
 *
 * @property int $id
 * @property string $name
 * @property string $discount_type
 * @property string $discount_value
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property bool $is_active
 * @property bool $show_banner
 * @property int|null $products_count
 */
class FlashSale extends Model
{
    public const PERCENT = 'percent';

    public const FIXED = 'fixed';

    protected $fillable = ['name', 'discount_type', 'discount_value', 'starts_at', 'ends_at', 'is_active', 'show_banner'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'discount_value' => 'decimal:4',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
            'show_banner' => 'boolean',
        ];
    }

    /**
     * @return HasMany<FlashSaleProduct, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(FlashSaleProduct::class);
    }

    /**
     * Running now: switched on and inside its time window.
     *
     * @param  Builder<FlashSale>  $query
     */
    public function scopeLive(Builder $query, ?Carbon $now = null): void
    {
        $now ??= now();
        $query->where('is_active', true)->where('starts_at', '<=', $now)->where('ends_at', '>', $now);
    }

    /** Scheduled, Live, Ended or Off. */
    public function state(?Carbon $now = null): string
    {
        $now ??= now();

        return match (true) {
            ! $this->is_active => 'off',
            $this->ends_at->lte($now) => 'ended',
            $this->starts_at->gt($now) => 'scheduled',
            default => 'live',
        };
    }
}
