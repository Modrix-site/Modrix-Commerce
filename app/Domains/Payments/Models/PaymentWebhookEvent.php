<?php

namespace App\Domains\Payments\Models;

use App\Domains\Orders\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A verified webhook event from a payment provider. Stored before it is
 * processed; the unique (provider, event_id) key makes redelivery harmless.
 *
 * @property int $id
 * @property string $provider
 * @property string $event_id
 * @property string $type
 * @property array<string, mixed> $payload
 * @property string $status
 * @property int $attempts
 * @property string|null $error
 * @property int|null $order_id
 * @property Carbon $received_at
 * @property Carbon|null $processed_at
 */
class PaymentWebhookEvent extends Model
{
    public const STATUS_RECEIVED = 'received';

    public const STATUS_PROCESSED = 'processed';

    public const STATUS_IGNORED = 'ignored';

    public const STATUS_FAILED = 'failed';

    public $timestamps = false;

    protected $guarded = ['*'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['payload' => 'array', 'received_at' => 'datetime', 'processed_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function isFinished(): bool
    {
        return in_array($this->status, [self::STATUS_PROCESSED, self::STATUS_IGNORED], true);
    }
}
