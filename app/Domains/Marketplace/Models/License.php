<?php

namespace App\Domains\Marketplace\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * A commercial license for one marketplace product (separate from the MIT
 * core license). The key is stored encrypted and looked up by its hash.
 *
 * @property int $id
 * @property int $product_id
 * @property int $user_id
 * @property int|null $order_item_id
 * @property string $key_hash
 * @property string $key_encrypted
 * @property string $key_hint
 * @property string $status
 * @property int|null $activation_limit
 * @property Carbon|null $updates_until
 * @property Carbon|null $expires_at
 * @property string|null $status_reason
 * @property Carbon $issued_at
 * @property-read MarketplaceProduct $product
 * @property-read User $user
 */
class License extends Model
{
    public const ACTIVE = 'active';

    public const EXPIRED = 'expired';

    public const REVOKED = 'revoked';

    public const REFUNDED = 'refunded';

    public const SUSPENDED = 'suspended';

    public const STATUSES = [self::ACTIVE, self::EXPIRED, self::REVOKED, self::REFUNDED, self::SUSPENDED];

    protected $guarded = ['*'];

    protected $hidden = ['key_hash', 'key_encrypted'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activation_limit' => 'integer',
            'updates_until' => 'datetime',
            'expires_at' => 'datetime',
            'issued_at' => 'datetime',
        ];
    }

    /**
     * A new random key: "EMK-" and four groups of five characters (about 100 bits).
     */
    public static function generateKey(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $groups = [];

        for ($g = 0; $g < 4; $g++) {
            $group = '';
            for ($i = 0; $i < 5; $i++) {
                $group .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $groups[] = $group;
        }

        return 'EMK-'.implode('-', $groups);
    }

    public static function hashKey(string $key): string
    {
        return hash('sha256', Str::upper(trim($key)));
    }

    public static function findByKey(string $key): ?self
    {
        return self::query()->where('key_hash', self::hashKey($key))->first();
    }

    public function key(): string
    {
        return Crypt::decryptString($this->key_encrypted);
    }

    public function isUsable(): bool
    {
        return $this->status === self::ACTIVE && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function coversRelease(MarketplaceRelease $release): bool
    {
        return $this->updates_until === null || ($release->published_at !== null && $release->published_at->lessThanOrEqualTo($this->updates_until));
    }

    /**
     * @return BelongsTo<MarketplaceProduct, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(MarketplaceProduct::class, 'product_id')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<LicenseActivation, $this>
     */
    public function activations(): HasMany
    {
        return $this->hasMany(LicenseActivation::class);
    }

    /**
     * @return HasMany<LicenseEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(LicenseEvent::class);
    }
}
