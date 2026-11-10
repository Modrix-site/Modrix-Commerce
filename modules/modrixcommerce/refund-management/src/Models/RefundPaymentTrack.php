<?php

namespace EcoModular\RefundManagement\Models;

use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\Refund;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Refund payout tracking record for gateway and manual settlements.
 *
 * @property int $id
 * @property int $refund_id
 * @property int $order_id
 * @property string $channel
 * @property string $payout_status
 * @property string|null $reference
 * @property string|null $destination
 * @property string|null $notes
 * @property Carbon|null $processed_at
 * @property int|null $updated_by
 */
class RefundPaymentTrack extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_SETTLED = 'settled';

    public const STATUS_FAILED = 'failed';

    /** @var array<string, string> */
    public const CHANNELS = [
        'gateway' => 'Payment gateway',
        'bank_transfer' => 'Bank transfer',
        'cash' => 'Cash',
        'card_terminal' => 'Card terminal',
        'store_credit' => 'Store credit / voucher',
        'check' => 'Check',
        'other' => 'Other',
    ];

    /** @var array<string, string> */
    public const STATUSES = [
        self::STATUS_PENDING => 'Pending payout',
        self::STATUS_PROCESSING => 'Processing',
        self::STATUS_SETTLED => 'Settled',
        self::STATUS_FAILED => 'Failed',
    ];

    protected $guarded = ['*'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'processed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Refund, $this>
     */
    public function refund(): BelongsTo
    {
        return $this->belongsTo(Refund::class);
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
