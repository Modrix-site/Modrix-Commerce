<?php

namespace App\Core\Extensions\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Append-only lifecycle log entry.
 *
 * @property int $id
 * @property string $extension
 * @property string $action
 * @property string|null $from_state
 * @property string|null $to_state
 * @property string|null $version
 * @property bool $success
 * @property string|null $message
 * @property int|null $actor_id
 * @property Carbon $created_at
 */
class ExtensionEvent extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['*'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Extension events are append-only.'));
        static::deleting(fn () => throw new LogicException('Extension events are append-only.'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['success' => 'boolean', 'created_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
