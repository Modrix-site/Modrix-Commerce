<?php

namespace App\Core\Extensions\Models;

use App\Core\Extensions\Manifest;
use App\Core\Extensions\ModuleState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The merchant-side record of a package found on disk.
 *
 * @property int $id
 * @property string $name
 * @property string $type
 * @property string $display_name
 * @property string|null $description
 * @property ModuleState $state
 * @property string|null $version
 * @property string|null $available_version
 * @property string $path
 * @property array<string, mixed>|null $manifest
 * @property array<string, mixed>|null $applied_manifest theme snapshot the merchant applied
 * @property Carbon|null $applied_at
 * @property list<string>|null $errors
 * @property bool $enabled_before_update
 * @property string|null $failure
 * @property Carbon|null $installed_at
 * @property Carbon|null $enabled_at
 * @property Carbon|null $failed_at
 * @property Carbon|null $updated_at
 */
class Extension extends Model
{
    protected $guarded = ['*'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'state' => ModuleState::class,
            'manifest' => 'array',
            'applied_manifest' => 'array',
            'applied_at' => 'datetime',
            'errors' => 'array',
            'enabled_before_update' => 'boolean',
            'installed_at' => 'datetime',
            'enabled_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function isInstalled(): bool
    {
        return $this->version !== null;
    }

    /**
     * The validated manifest of the files on disk, if any.
     */
    public function manifest(): ?Manifest
    {
        return $this->manifest === null ? null : Manifest::fromArray($this->manifest);
    }

    public function getRouteKeyName(): string
    {
        return 'id';
    }
}
