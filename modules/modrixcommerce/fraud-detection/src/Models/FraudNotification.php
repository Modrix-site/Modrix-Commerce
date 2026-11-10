<?php

namespace EcoModular\FraudDetection\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $fraud_score_id
 * @property string $channel
 * @property string|null $recipient
 * @property string $status
 * @property string|null $error_message
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read FraudScore $fraudScore
 */
class FraudNotification extends Model
{
    protected $table = 'fraud_notifications';

    protected $guarded = ['id'];

    /**
     * @return BelongsTo<FraudScore, $this>
     */
    public function fraudScore(): BelongsTo
    {
        return $this->belongsTo(FraudScore::class);
    }
}
