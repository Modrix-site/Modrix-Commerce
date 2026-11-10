<?php

namespace EcoModular\FraudDetection\Models;

use App\Domains\Customers\Models\Customer;
use App\Domains\Orders\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $order_id
 * @property int|null $customer_id
 * @property int $total_score
 * @property string $risk_level
 * @property string $review_status
 * @property array<int, array<string, mixed>> $indicators
 * @property string|null $ip_address
 * @property string|null $customer_email
 * @property string|null $customer_country
 * @property string|null $payment_country
 * @property bool $is_vpn
 * @property bool $is_proxy
 * @property bool $is_disposable_email
 * @property bool $address_mismatch
 * @property int $failed_payment_attempts
 * @property string $order_total
 * @property string $currency
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property string|null $review_notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Order $order
 * @property-read Customer|null $customer
 * @property-read User|null $reviewer
 * @property-read \Illuminate\Database\Eloquent\Collection<int, FraudReview> $reviews
 * @property-read \Illuminate\Database\Eloquent\Collection<int, FraudNotification> $notifications
 */
class FraudScore extends Model
{
    public const RISK_LOW = 'low';

    public const RISK_MEDIUM = 'medium';

    public const RISK_HIGH = 'high';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_UNDER_REVIEW = 'under_review';

    protected $table = 'fraud_scores';

    protected $guarded = ['id'];

    protected $casts = [
        'indicators' => 'array',
        'total_score' => 'integer',
        'is_vpn' => 'boolean',
        'is_proxy' => 'boolean',
        'is_disposable_email' => 'boolean',
        'address_mismatch' => 'boolean',
        'failed_payment_attempts' => 'integer',
        'reviewed_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @return HasMany<FraudReview, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(FraudReview::class, 'fraud_score_id')->orderByDesc('created_at');
    }

    /**
     * @return HasMany<FraudNotification, $this>
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(FraudNotification::class, 'fraud_score_id');
    }

    public function isHighRisk(): bool
    {
        return $this->risk_level === self::RISK_HIGH;
    }

    public function isPending(): bool
    {
        return $this->review_status === self::STATUS_PENDING;
    }

    public function markAsApproved(User $user, ?string $notes = null): void
    {
        $previousStatus = $this->review_status;
        $this->update([
            'review_status' => self::STATUS_APPROVED,
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
            'review_notes' => $notes,
        ]);

        $this->reviews()->create([
            'user_id' => $user->id,
            'action' => 'approved',
            'comment' => $notes,
            'previous_status' => $previousStatus,
            'new_status' => self::STATUS_APPROVED,
        ]);
    }

    public function markAsRejected(User $user, ?string $notes = null): void
    {
        $previousStatus = $this->review_status;
        $this->update([
            'review_status' => self::STATUS_REJECTED,
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
            'review_notes' => $notes,
        ]);

        $this->reviews()->create([
            'user_id' => $user->id,
            'action' => 'rejected',
            'comment' => $notes,
            'previous_status' => $previousStatus,
            'new_status' => self::STATUS_REJECTED,
        ]);
    }

    public function markAsUnderReview(User $user, ?string $notes = null): void
    {
        $previousStatus = $this->review_status;
        $this->update([
            'review_status' => self::STATUS_UNDER_REVIEW,
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
            'review_notes' => $notes,
        ]);

        $this->reviews()->create([
            'user_id' => $user->id,
            'action' => 'under_review',
            'comment' => $notes,
            'previous_status' => $previousStatus,
            'new_status' => self::STATUS_UNDER_REVIEW,
        ]);
    }

    public function addNote(User $user, string $comment): void
    {
        $this->reviews()->create([
            'user_id' => $user->id,
            'action' => 'note',
            'comment' => $comment,
            'previous_status' => $this->review_status,
            'new_status' => $this->review_status,
        ]);
    }
}
