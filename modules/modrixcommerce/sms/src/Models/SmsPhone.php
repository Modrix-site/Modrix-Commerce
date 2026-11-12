<?php

namespace ModrixCommerce\Sms\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $phone
 * @property Carbon|null $verified_at
 * @property string|null $pending_phone
 * @property bool $order_updates
 */
class SmsPhone extends Model
{
    protected $table = 'sms_phones';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['verified_at' => 'datetime', 'order_updates' => 'boolean'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The number a code is being confirmed for right now. */
    public function numberToVerify(): string
    {
        return $this->pending_phone ?? $this->phone;
    }
}
