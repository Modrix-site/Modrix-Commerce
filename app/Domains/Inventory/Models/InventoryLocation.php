<?php

namespace App\Domains\Inventory\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property bool $is_default
 * @property bool $is_active
 */
#[Fillable(['code', 'name', 'is_default', 'is_active'])]
class InventoryLocation extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_default' => 'boolean', 'is_active' => 'boolean'];
    }
}
