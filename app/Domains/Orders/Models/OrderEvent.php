<?php

namespace App\Domains\Orders\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Order history entry (status changes, payments, notes). Append-only.
 *
 * @property int $id
 * @property int $order_id
 * @property string $type
 * @property string $message
 * @property bool $is_customer_visible
 * @property int|null $actor_id
 * @property array<string, mixed>|null $metadata
 * @property Carbon $created_at
 */
class OrderEvent extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['*'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_customer_visible' => 'boolean', 'metadata' => 'array', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Order history is immutable.'));
        static::deleting(fn () => throw new LogicException('Order history is immutable.'));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
