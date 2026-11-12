<?php

namespace App\Domains\Marketplace\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Who publishes marketplace products: the platform owner (`is_platform`)
 * and, since Phase 8.4, approved third-party sellers. A seller's packages
 * must use its slug as their vendor prefix (`<slug>/<package>`).
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property bool $is_platform
 * @property string $status
 * @property string $commission_percent
 * @property string|null $legal_name
 * @property string|null $country
 * @property string|null $website
 * @property string|null $payout_email
 * @property string|null $support_email
 * @property Carbon|null $verified_at
 */
class Publisher extends Model
{
    public const ACTIVE = 'active';

    public const SUSPENDED = 'suspended';

    protected $table = 'marketplace_publishers';

    protected $guarded = ['*'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_platform' => 'boolean', 'verified_at' => 'datetime'];
    }

    public static function platform(): self
    {
        return self::query()->where('is_platform', true)->firstOrFail();
    }

    public function isActive(): bool
    {
        return $this->status === self::ACTIVE;
    }

    /**
     * @return HasMany<MarketplaceProduct, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(MarketplaceProduct::class, 'publisher_id');
    }

    /**
     * @return HasMany<PublisherMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(PublisherMember::class, 'publisher_id');
    }

    /**
     * @return HasMany<LedgerEntry, $this>
     */
    public function ledger(): HasMany
    {
        return $this->hasMany(LedgerEntry::class, 'publisher_id');
    }
}
