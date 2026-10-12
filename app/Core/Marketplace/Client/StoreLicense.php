<?php

namespace App\Core\Marketplace\Client;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;

/**
 * A marketplace license activated on this store (client side).
 *
 * @property int $id
 * @property string $product_slug
 * @property string $product_name
 * @property string $extension_name
 * @property string $type
 * @property string $key_encrypted
 * @property string $key_hint
 * @property string $status
 * @property Carbon|null $updates_until
 * @property array<string, mixed>|null $token
 * @property Carbon|null $token_valid_until
 * @property array<string, mixed>|null $available_release
 * @property Carbon|null $last_checked_at
 * @property string|null $last_error
 */
class StoreLicense extends Model
{
    protected $table = 'marketplace_licenses';

    protected $guarded = ['*'];

    protected $hidden = ['key_encrypted'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'updates_until' => 'datetime',
            'token' => 'array',
            'token_valid_until' => 'datetime',
            'available_release' => 'array',
            'last_checked_at' => 'datetime',
        ];
    }

    public function key(): string
    {
        return Crypt::decryptString($this->key_encrypted);
    }

    /**
     * True when the last verified token has expired and the grace period
     * has passed without a successful check. Shown as a warning only:
     * licensing never disables installed extensions or the storefront.
     */
    public function needsVerification(): bool
    {
        $grace = max(0, (int) config('ecomodular.marketplace.grace_days', 14));

        return $this->token_valid_until === null || $this->token_valid_until->copy()->addDays($grace)->isPast();
    }
}
