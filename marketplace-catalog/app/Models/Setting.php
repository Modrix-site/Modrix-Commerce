<?php

namespace Marketplace\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Key/value marketplace settings. Secrets (Stripe keys, SMTP password,
 * signing key) are stored encrypted and only decrypted on read.
 */
class Setting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value', 'secret'];

    protected function casts(): array
    {
        return ['secret' => 'boolean'];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $row = static::query()->find($key);

        if ($row === null) {
            return $default;
        }

        $value = $row->secret ? Crypt::decryptString($row->value) : $row->value;

        return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
    }

    public static function put(string $key, mixed $value, bool $secret = false): void
    {
        $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        static::query()->updateOrCreate(['key' => $key], [
            'value' => $secret ? Crypt::encryptString($encoded) : $encoded,
            'secret' => $secret,
        ]);
    }

    public static function forget(string $key): void
    {
        static::query()->whereKey($key)->delete();
    }
}
