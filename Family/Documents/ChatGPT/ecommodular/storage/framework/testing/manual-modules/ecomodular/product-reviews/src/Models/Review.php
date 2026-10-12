<?php

namespace EcoModular\ProductReviews\Models;

use App\Domains\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $product_id
 * @property int|null $user_id
 * @property string $author_name
 * @property int $rating
 * @property string|null $title
 * @property string $body
 * @property string $status
 * @property bool $verified_purchase
 * @property int|null $moderated_by
 * @property Carbon|null $moderated_at
 * @property Carbon|null $created_at
 * @property-read Product $product
 */
class Review extends Model
{
    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    protected $table = 'product_reviews';

    protected $guarded = ['*'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['rating' => 'integer', 'verified_purchase' => 'boolean', 'moderated_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @param  Builder<Review>  $query
     */
    public function scopeApproved(Builder $query): void
    {
        $query->where('status', self::APPROVED);
    }
}
