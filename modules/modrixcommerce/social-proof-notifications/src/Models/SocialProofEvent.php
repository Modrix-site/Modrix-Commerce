<?php

namespace EcoModular\SocialProofNotifications\Models;

use App\Domains\Catalog\Models\Product;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $event_type
 * @property int|null $product_id
 * @property string $product_name
 * @property string|null $product_image_url
 * @property string|null $product_url
 * @property string|null $customer_name
 * @property string|null $customer_city
 * @property string|null $customer_country
 * @property string|null $message
 * @property Carbon|null $occurred_at
 * @property bool $is_active
 * @property bool $is_simulated
 * @property int $impressions_count
 * @property int $clicks_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Product|null $product
 */
class SocialProofEvent extends Model
{
    public const TYPE_RECENT_PURCHASE = 'recent_purchase';

    public const TYPE_ADDED_TO_CART = 'added_to_cart';

    public const TYPE_PRODUCT_VIEWERS = 'product_viewers';

    public const TYPE_LOW_STOCK = 'low_stock';

    public const TYPE_RECENT_REVIEW = 'recent_review';

    public const TYPE_CUSTOM_ALERT = 'custom_alert';

    protected $table = 'social_proof_events';

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_simulated' => 'boolean',
            'impressions_count' => 'integer',
            'clicks_count' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * Scope active records.
     *
     * @param  Builder<SocialProofEvent>  $query
     * @return Builder<SocialProofEvent>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope events within the past N days.
     *
     * @param  Builder<SocialProofEvent>  $query
     * @return Builder<SocialProofEvent>
     */
    public function scopeRecent(Builder $query, int $days): Builder
    {
        return $query->where('occurred_at', '>=', now()->subDays($days));
    }

    /**
     * Mask customer name according to privacy mode.
     */
    public function maskedCustomerName(string $privacyMode = 'first_name_initial'): string
    {
        $raw = trim((string) $this->customer_name);
        if ($raw === '' || $privacyMode === 'anonymous') {
            return 'Someone';
        }

        $parts = preg_split('/\s+/', $raw);
        if ($parts === false) {
            return 'Someone';
        }

        $first = Str::title($parts[0]);

        if ($privacyMode === 'first_name_only') {
            return $first;
        }

        // first_name_initial
        if (isset($parts[1]) && ! empty($parts[1])) {
            $initial = Str::upper(mb_substr($parts[1], 0, 1));

            return "{$first} {$initial}.";
        }

        return $first;
    }

    /**
     * Format location string (City, Country).
     */
    public function formattedLocation(): ?string
    {
        $city = trim((string) $this->customer_city);
        $country = trim((string) $this->customer_country);

        if ($city !== '' && $country !== '') {
            return "{$city}, {$country}";
        }

        return $city !== '' ? $city : ($country !== '' ? $country : null);
    }

    /**
     * Time ago display string.
     */
    public function timeAgo(): string
    {
        if ($this->occurred_at === null) {
            return 'Just now';
        }

        return $this->occurred_at->diffForHumans([
            'syntax' => Carbon::DIFF_RELATIVE_TO_NOW,
            'options' => Carbon::JUST_NOW | Carbon::ONE_DAY_WORDS,
        ]);
    }

    /**
     * Formatted payload for storefront toast popup.
     *
     * @return array<string, mixed>
     */
    public function toToastPayload(string $namePrivacy = 'first_name_initial', bool $showLocation = true): array
    {
        $name = $this->maskedCustomerName($namePrivacy);
        $location = $showLocation ? $this->formattedLocation() : null;

        $headline = match ($this->event_type) {
            self::TYPE_RECENT_PURCHASE => $location ? "{$name} in {$location}" : $name,
            self::TYPE_ADDED_TO_CART => $location ? "{$name} from {$location}" : $name,
            self::TYPE_LOW_STOCK => 'High Demand Alert',
            self::TYPE_PRODUCT_VIEWERS => 'Trending Now',
            self::TYPE_RECENT_REVIEW => $location ? "Verified Review by {$name}" : "Verified Review",
            default => 'Special Highlight',
        };

        $subtitle = match ($this->event_type) {
            self::TYPE_RECENT_PURCHASE => "purchased {$this->product_name}",
            self::TYPE_ADDED_TO_CART => "added {$this->product_name} to cart",
            self::TYPE_LOW_STOCK => $this->message ?: "Hurry, limited stock remaining for {$this->product_name}",
            self::TYPE_PRODUCT_VIEWERS => $this->message ?: "Shoppers are viewing this product right now",
            self::TYPE_RECENT_REVIEW => $this->message ?: "5-star rating for {$this->product_name}",
            default => $this->message ?: $this->product_name,
        };

        return [
            'id' => $this->id,
            'type' => $this->event_type,
            'headline' => $headline,
            'subtitle' => $subtitle,
            'productName' => $this->product_name,
            'productImageUrl' => $this->product_image_url,
            'productUrl' => $this->product_url ?: ($this->product_id ? "/p/{$this->product_id}" : null),
            'timeAgo' => $this->timeAgo(),
            'isVerified' => ! $this->is_simulated,
        ];
    }
}
