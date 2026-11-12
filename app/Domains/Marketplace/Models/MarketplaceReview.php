<?php

namespace App\Domains\Marketplace\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A license holder's review of a marketplace product, shown after moderation.
 *
 * @property int $id
 * @property int $product_id
 * @property int $user_id
 * @property int $rating
 * @property string|null $title
 * @property string $body
 * @property string $status
 * @property int|null $moderated_by
 * @property Carbon|null $moderated_at
 * @property Carbon|null $created_at
 * @property-read MarketplaceProduct $product
 * @property-read User $user
 */
class MarketplaceReview extends Model
{
    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    protected $table = 'marketplace_reviews';

    protected $guarded = ['*'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['rating' => 'integer', 'moderated_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<MarketplaceProduct, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(MarketplaceProduct::class, 'product_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
