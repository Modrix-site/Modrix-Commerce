<?php

namespace App\Domains\Marketplace\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A request to sell on the marketplace, reviewed by the platform owner.
 *
 * @property int $id
 * @property int $user_id
 * @property string $publisher_name
 * @property string $vendor_slug
 * @property string $legal_name
 * @property string $country
 * @property string|null $website
 * @property string $payout_email
 * @property string $support_email
 * @property string $description
 * @property string $status
 * @property string|null $review_notes
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property int|null $publisher_id
 * @property Carbon|null $created_at
 * @property-read User $user
 */
class SellerApplication extends Model
{
    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    protected $table = 'marketplace_seller_applications';

    protected $guarded = ['*'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
