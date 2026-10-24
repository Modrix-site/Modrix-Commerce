<?php

namespace Marketplace\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $license_id
 * @property string $instance_id
 * @property string|null $domain
 * @property bool $active
 */
class LicenseActivation extends Model
{
    protected $fillable = ['license_id', 'instance_id', 'domain', 'active', 'activated_at', 'deactivated_at'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'activated_at' => 'datetime', 'deactivated_at' => 'datetime'];
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }
}
