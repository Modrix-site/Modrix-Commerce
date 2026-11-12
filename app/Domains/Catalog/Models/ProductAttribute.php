<?php

namespace App\Domains\Catalog\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An option type such as "Color" or "Size" used to define variants.
 *
 * @property int $id
 * @property string $name
 * @property string $display_type
 * @property string $slug
 * @property int $position
 */
#[Fillable(['name', 'slug', 'position', 'display_type'])]
class ProductAttribute extends Model
{
    /**
     * @return HasMany<ProductAttributeValue, $this>
     */
    public function values(): HasMany
    {
        return $this->hasMany(ProductAttributeValue::class, 'attribute_id')->orderBy('position')->orderBy('id');
    }
}
