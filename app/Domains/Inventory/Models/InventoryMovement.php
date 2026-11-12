<?php

namespace App\Domains\Inventory\Models;

use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Inventory\MovementReason;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Append-only stock ledger entry.
 *
 * @property int $id
 * @property int $variant_id
 * @property int $location_id
 * @property int $quantity_change
 * @property int $quantity_after
 * @property MovementReason $reason
 * @property string|null $reference_type
 * @property int|null $reference_id
 * @property int|null $actor_id
 * @property string|null $note
 * @property Carbon $created_at
 */
class InventoryMovement extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reason' => MovementReason::class,
            'quantity_change' => 'integer',
            'quantity_after' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Inventory movements are immutable.'));
        static::deleting(fn () => throw new LogicException('Inventory movements are immutable.'));
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
