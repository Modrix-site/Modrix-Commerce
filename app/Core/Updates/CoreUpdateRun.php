<?php

namespace App\Core\Updates;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One core platform update attempt (see CoreUpdater).
 *
 * @property int $id
 * @property string $from_version
 * @property string $to_version
 * @property array<string, mixed> $release the signed release item as served by Modrix Cloud
 * @property string $status
 * @property string|null $message
 * @property string|null $backup_name
 * @property string|null $code_backup_path relative to the updates root
 * @property bool $needs_attention a failed run whose rollback could not undo everything
 * @property int|null $actor_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class CoreUpdateRun extends Model
{
    public const QUEUED = 'queued';

    public const DOWNLOADING = 'downloading';

    public const VERIFYING = 'verifying';

    public const BACKING_UP = 'backing_up';

    public const INSTALLING = 'installing';

    public const MIGRATING = 'migrating';

    public const COMPLETED = 'completed';

    public const FAILED = 'failed';

    public const ROLLED_BACK = 'rolled_back';

    public const TERMINAL = [self::COMPLETED, self::FAILED, self::ROLLED_BACK];

    /** A run whose row has not moved for this long is treated as dead. */
    public const STALE_MINUTES = 120;

    /** A queued run the detached process has not picked up after this long probably never started. */
    public const START_TIMEOUT_SECONDS = 60;

    /** A live run whose row has not moved for this long is reported by the health check. */
    public const STUCK_MINUTES = 10;

    protected $table = 'core_update_runs';

    protected $guarded = ['*'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['release' => 'array', 'needs_attention' => 'boolean'];
    }

    /**
     * Runs that have not finished and are not dead (a crashed process leaves its row behind).
     *
     * @param  Builder<CoreUpdateRun>  $query
     */
    public function scopeLive(Builder $query): void
    {
        $query->whereNotIn('status', self::TERMINAL)
            ->where('updated_at', '>', now()->subMinutes(self::STALE_MINUTES));
    }

    public static function live(): ?self
    {
        return static::query()->live()->latest('id')->first();
    }

    /** Shown while a queued run has not been picked up by the detached process. */
    public function startHint(): ?string
    {
        if ($this->status !== self::QUEUED || $this->updated_at === null || $this->updated_at->gt(now()->subSeconds(self::START_TIMEOUT_SECONDS))) {
            return null;
        }

        return "The update did not start. Run php artisan ecomodular:core-update {$this->getKey()} on the server.";
    }

    public function isActive(): bool
    {
        return ! in_array($this->status, self::TERMINAL, true);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
