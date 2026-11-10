<?php

namespace EcoModular\FraudDetection\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $fraud_score_id
 * @property int $user_id
 * @property string $action
 * @property string|null $comment
 * @property string|null $previous_status
 * @property string|null $new_status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read FraudScore $fraudScore
 * @property-read User $user
 */
class FraudReview extends Model
{
    protected $table = 'fraud_reviews';

    protected $guarded = ['id'];

    /**
     * @return BelongsTo<FraudScore, $this>
     */
    public function fraudScore(): BelongsTo
    {
        return $this->belongsTo(FraudScore::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
