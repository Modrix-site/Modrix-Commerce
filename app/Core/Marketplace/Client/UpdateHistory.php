<?php

namespace App\Core\Marketplace\Client;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Append-only history of marketplace installs, updates and rollbacks.
 *
 * @property int $id
 * @property string $extension_name
 * @property string $type
 * @property string $action
 * @property string|null $from_version
 * @property string|null $to_version
 * @property bool $success
 * @property string|null $message
 * @property string|null $backup_path
 * @property string|null $sha256
 * @property int|null $actor_id
 * @property Carbon $created_at
 */
class UpdateHistory extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'marketplace_update_history';

    protected $guarded = ['*'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Update history is append-only.'));
        static::deleting(fn () => throw new LogicException('Update history is append-only.'));
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
