<?php

namespace EcoModular\SocialProofNotifications\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $product_id
 * @property string $session_id
 * @property Carbon $last_seen_at
 */
class LiveProductViewer extends Model
{
    protected $table = 'social_proof_live_viewers';

    public $timestamps = false;

    public $incrementing = false;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'product_id' => 'integer',
            'last_seen_at' => 'datetime',
        ];
    }

    /**
     * Record or refresh a heartbeat for a product and session.
     */
    public static function recordHeartbeat(int $productId, string $sessionId): void
    {
        static::query()->upsert(
            [
                'product_id' => $productId,
                'session_id' => $sessionId,
                'last_seen_at' => now(),
            ],
            ['product_id', 'session_id'],
            ['last_seen_at']
        );
    }

    /**
     * Get active viewer count for a product in the given window.
     */
    public static function countForProduct(int $productId, int $windowMinutes = 5): int
    {
        return static::query()
            ->where('product_id', $productId)
            ->where('last_seen_at', '>=', now()->subMinutes($windowMinutes))
            ->count();
    }

    /**
     * Prune old records to keep table lightweight.
     */
    public static function pruneStale(int $windowMinutes = 30): int
    {
        return static::query()
            ->where('last_seen_at', '<', now()->subMinutes($windowMinutes))
            ->delete();
    }
}
