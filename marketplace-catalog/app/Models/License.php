<?php

namespace Marketplace\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One stable key per customer and package; active on one store at a time.
 *
 * @property int $id
 * @property int $user_id
 * @property int $package_id
 * @property string $key
 * @property string $type free|paid
 * @property string $status active|revoked|refunded
 */
class License extends Model
{
    protected $fillable = ['user_id', 'package_id', 'key', 'type', 'status'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function activations(): HasMany
    {
        return $this->hasMany(LicenseActivation::class);
    }

    public function activeActivation(): ?LicenseActivation
    {
        return $this->activations()->where('active', true)->first();
    }
}
