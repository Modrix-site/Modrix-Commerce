<?php

namespace App\Domains\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $product_id
 * @property string $locale
 * @property string|null $name
 * @property string|null $short_description
 * @property string|null $description
 * @property string|null $meta_title
 * @property string|null $meta_description
 */
class ProductTranslation extends Model
{
    protected $fillable = ['locale', 'name', 'short_description', 'description', 'meta_title', 'meta_description'];

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
