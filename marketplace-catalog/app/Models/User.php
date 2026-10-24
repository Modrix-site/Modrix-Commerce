<?php

namespace Marketplace\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * A customer or publisher; `is_admin` users manage the marketplace.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property bool $is_admin
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['password' => 'hashed', 'is_admin' => 'boolean'];
    }

    /** Stored lowercase so sign-in and uniqueness ignore case on every database. */
    protected function email(): Attribute
    {
        return Attribute::make(set: fn (string $value) => mb_strtolower(trim($value)));
    }

    public function licenses(): HasMany
    {
        return $this->hasMany(License::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }
}
