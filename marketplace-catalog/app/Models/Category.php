<?php

namespace Marketplace\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string $type theme|module|all
 * @property bool $enabled
 */
class Category extends Model
{
    protected $fillable = ['slug', 'name', 'description', 'type', 'position', 'enabled'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'position' => 'integer'];
    }

    public function packages(): BelongsToMany
    {
        return $this->belongsToMany(Package::class);
    }
}
