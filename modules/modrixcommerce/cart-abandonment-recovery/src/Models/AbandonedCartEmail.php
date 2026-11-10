<?php

namespace EcoModular\CartAbandonmentRecovery\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $abandoned_cart_id
 * @property int $step
 * @property string $recipient_email
 * @property string $subject
 * @property string|null $discount_code
 * @property string $status
 * @property Carbon $sent_at
 * @property Carbon|null $opened_at
 * @property Carbon|null $clicked_at
 * @property string|null $error_message
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read AbandonedCart|null $abandonedCart
 */
class AbandonedCartEmail extends Model
{
    protected $table = 'abandoned_cart_emails';

    protected $guarded = ['id'];

    protected $casts = [
        'step' => 'integer',
        'sent_at' => 'datetime',
        'opened_at' => 'datetime',
        'clicked_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<AbandonedCart, $this>
     */
    public function abandonedCart(): BelongsTo
    {
        return $this->belongsTo(AbandonedCart::class, 'abandoned_cart_id');
    }

    public function recordOpen(): void
    {
        if ($this->opened_at === null) {
            $this->update(['opened_at' => now()]);
        }
    }

    public function recordClick(): void
    {
        $this->update(['clicked_at' => now()]);
        $this->abandonedCart?->recordClick();
    }
}
