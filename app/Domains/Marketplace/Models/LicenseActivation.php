<?php

namespace App\Domains\Marketplace\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A store installation using a license. Active while deactivated_at is null.
 *
 * @property int $id
 * @property int $license_id
 * @property string $instance_id
 * @property string|null $domain
 * @property string|null $platform_version
 * @property Carbon $activated_at
 * @property Carbon|null $last_seen_at
 * @property Carbon|null $deactivated_at
 */
class LicenseActivation extends Model
{
    public $timestamps = false;

    protected $guarded = ['*'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['activated_at' => 'datetime', 'last_seen_at' => 'datetime', 'deactivated_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<License, $this>
     */
    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }
}
