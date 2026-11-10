<?php

namespace EcoModular\CartAbandonmentRecovery\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $email
 * @property string|null $reason
 * @property Carbon $created_at
 */
class AbandonedCartUnsubscribe extends Model
{
    public $timestamps = false;

    protected $table = 'abandoned_cart_unsubscribes';

    protected $guarded = ['id'];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public static function isUnsubscribed(string $email): bool
    {
        return self::query()->where('email', strtolower(trim($email)))->exists();
    }
}
