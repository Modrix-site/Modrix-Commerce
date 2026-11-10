<?php

namespace EcoModular\OrderReturns\Models;

use App\Domains\Orders\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A customer's request to send items back. Approving it records the decision
 * only: it does not refund the order or restock the items.
 *
 * @property int $id
 * @property int $order_id
 * @property int $user_id
 * @property string $status
 * @property string $customer_comment
 * @property string|null $staff_comment
 * @property int|null $decided_by
 * @property Carbon|null $decided_at
 * @property Carbon|null $created_at
 */
class OrderReturnRequest extends Model
{
    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    protected $table = 'order_return_requests';

    protected $guarded = ['*'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'decided_at' => 'datetime',
        ];
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
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return HasMany<OrderReturnItem, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(OrderReturnItem::class);
    }
}
