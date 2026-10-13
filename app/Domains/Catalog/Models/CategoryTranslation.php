<?php

namespace App\Domains\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $category_id
 * @property string $locale
 * @property string|null $name
 * @property string|null $description
 * @property string|null $meta_title
 * @property string|null $meta_description
 */
class CategoryTranslation extends Model
{
    protected $fillable = ['locale', 'name', 'description', 'meta_title', 'meta_description'];

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
