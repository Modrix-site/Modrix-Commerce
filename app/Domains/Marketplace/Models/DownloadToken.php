<?php

namespace App\Domains\Marketplace\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A short-lived, single-use authorization to download one release. Only
 * the token's hash is stored.
 *
 * @property int $id
 * @property int $license_id
 * @property int $release_id
 * @property string $token_hash
 * @property string|null $instance_id
 * @property Carbon $expires_at
 * @property Carbon|null $used_at
 * @property string|null $ip_address
 * @property-read License $license
 * @property-read MarketplaceRelease $release
 */
class DownloadToken extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['*'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'used_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<License, $this>
     */
    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }

    /**
     * @return BelongsTo<MarketplaceRelease, $this>
     */
    public function release(): BelongsTo
    {
        return $this->belongsTo(MarketplaceRelease::class);
    }
}
