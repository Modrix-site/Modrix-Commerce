<?php

namespace App\Domains\Marketplace\Models;

use App\Domains\Pricing\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A product sold on the marketplace: one installable module or theme.
 *
 * @property int $id
 * @property int $publisher_id
 * @property string $slug
 * @property string $extension_name
 * @property string $type
 * @property string $name
 * @property string|null $summary
 * @property string|null $description
 * @property string|null $documentation_url
 * @property string|null $support_email
 * @property string|null $support_url
 * @property string $price
 * @property string $currency
 * @property string $status
 * @property int $updates_months
 * @property int|null $activation_limit
 * @property Carbon|null $created_at
 * @property-read Publisher $publisher
 */
class MarketplaceProduct extends Model
{
    use SoftDeletes;

    public const DRAFT = 'draft';

    public const PUBLISHED = 'published';

    public const RETIRED = 'retired';

    public const TYPES = ['module', 'theme'];

    protected $guarded = ['*'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['price' => 'decimal:4', 'updates_months' => 'integer', 'activation_limit' => 'integer'];
    }

    public function money(): Money
    {
        return Money::of($this->price, $this->currency);
    }

    public function isFree(): bool
    {
        return $this->money()->isZero();
    }

    /**
     * @return BelongsTo<Publisher, $this>
     */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(Publisher::class);
    }

    /**
     * @return HasMany<MarketplaceRelease, $this>
     */
    public function releases(): HasMany
    {
        return $this->hasMany(MarketplaceRelease::class, 'product_id');
    }

    /**
     * @return HasMany<MarketplaceAdvisory, $this>
     */
    public function advisories(): HasMany
    {
        return $this->hasMany(MarketplaceAdvisory::class, 'product_id');
    }

    /**
     * Products shown and sold: published, from an active publisher.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeListed(Builder $query): Builder
    {
        return $query->where('status', self::PUBLISHED)
            ->whereHas('publisher', fn (Builder $q) => $q->where('status', Publisher::ACTIVE));
    }

    public function isListed(): bool
    {
        return $this->status === self::PUBLISHED && $this->publisher->isActive();
    }

    public function latestPublishedRelease(): ?MarketplaceRelease
    {
        return $this->releases()->where('status', MarketplaceRelease::PUBLISHED)->orderByDesc('published_at')->orderByDesc('id')->first();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
