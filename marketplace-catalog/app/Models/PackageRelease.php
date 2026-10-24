<?php

namespace Marketplace\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A signed, downloadable version of a package.
 *
 * @property int $id
 * @property int $package_id
 * @property string $version
 * @property string $path
 * @property string $sha256
 * @property int $size
 * @property string $platform_version
 * @property array<string, mixed> $signed_metadata
 * @property string $signature
 */
class PackageRelease extends Model
{
    protected $fillable = ['package_id', 'version', 'path', 'sha256', 'size', 'platform_version', 'signed_metadata', 'signature', 'published_at'];

    protected function casts(): array
    {
        return ['signed_metadata' => 'array', 'size' => 'integer', 'published_at' => 'datetime'];
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }
}
