<?php

namespace EcoModular\StorePickup\Models;

use App\Domains\Orders\Models\Order;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pickup schedule, location snapshot, and status for an order.
 *
 * @property int $id
 * @property int $order_id
 * @property int|null $pickup_branch_id
 * @property string $branch_name
 * @property string $branch_address
 * @property string|null $branch_phone
 * @property string|null $pickup_hours
 * @property string|null $collector_name
 * @property string|null $collector_phone
 * @property Carbon|null $pickup_date
 * @property string|null $pickup_time
 * @property string $status
 * @property Carbon|null $ready_at
 * @property Carbon|null $picked_up_at
 * @property string|null $instructions
 * @property string|null $customer_notes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class OrderPickup extends Model
{
    public const STATUS_PENDING = 'pending_prep';

    public const STATUS_READY = 'ready_for_pickup';

    public const STATUS_PICKED_UP = 'picked_up';

    public const STATUS_CANCELLED = 'cancelled';

    protected $table = 'order_pickups';

    protected $fillable = [
        'order_id',
        'pickup_branch_id',
        'branch_name',
        'branch_address',
        'branch_phone',
        'pickup_hours',
        'collector_name',
        'collector_phone',
        'pickup_date',
        'pickup_time',
        'status',
        'ready_at',
        'picked_up_at',
        'instructions',
        'customer_notes',
    ];

    protected $casts = [
        'pickup_date' => 'date',
        'ready_at' => 'datetime',
        'picked_up_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(PickupBranch::class, 'pickup_branch_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isReady(): bool
    {
        return $this->status === self::STATUS_READY;
    }

    public function isPickedUp(): bool
    {
        return $this->status === self::STATUS_PICKED_UP;
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeReady(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_READY);
    }

    public function scopePickedUp(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PICKED_UP);
    }

    public function statusBadge(): array
    {
        return match ($this->status) {
            self::STATUS_READY => [
                'label' => 'Ready for Pickup',
                'variant' => 'success',
                'color' => '#10b981',
            ],
            self::STATUS_PICKED_UP => [
                'label' => 'Picked Up / Completed',
                'variant' => 'secondary',
                'color' => '#6b7280',
            ],
            self::STATUS_CANCELLED => [
                'label' => 'Cancelled',
                'variant' => 'destructive',
                'color' => '#ef4444',
            ],
            default => [
                'label' => 'Preparing Order',
                'variant' => 'warning',
                'color' => '#f59e0b',
            ],
        };
    }
}
