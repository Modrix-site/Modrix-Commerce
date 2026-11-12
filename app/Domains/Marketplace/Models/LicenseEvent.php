<?php

namespace App\Domains\Marketplace\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Append-only license audit trail.
 *
 * @property int $id
 * @property int $license_id
 * @property string $action
 * @property int|null $actor_id
 * @property string|null $instance_id
 * @property string|null $ip_address
 * @property array<string, mixed>|null $details
 * @property Carbon $created_at
 */
class LicenseEvent extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['*'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('License events are append-only.'));
        static::deleting(fn () => throw new LogicException('License events are append-only.'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['details' => 'array', 'created_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
