<?php

namespace App\Domains\Marketplace\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An account that works for a seller (one publisher per account).
 *
 * @property int $id
 * @property int $publisher_id
 * @property int $user_id
 * @property string $role
 * @property-read Publisher $publisher
 * @property-read User $user
 */
class PublisherMember extends Model
{
    protected $table = 'marketplace_publisher_members';

    protected $guarded = ['*'];

    /**
     * @return BelongsTo<Publisher, $this>
     */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(Publisher::class, 'publisher_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function publisherFor(User $user): ?Publisher
    {
        return self::query()->where('user_id', $user->id)->with('publisher')->first()?->publisher;
    }
}
