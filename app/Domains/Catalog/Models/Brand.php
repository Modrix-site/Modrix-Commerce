<?php

namespace App\Domains\Catalog\Models;

use App\Core\Media\Media;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A product brand or vendor (Admin → Catalog → Brands).
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property int|null $logo_media_id
 * @property Carbon|null $created_at
 * @property-read Media|null $logo
 */
#[Fillable(['name', 'slug', 'description', 'logo_media_id'])]
class Brand extends Model
{
    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * @return BelongsTo<Media, $this>
     */
    public function logo(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'logo_media_id');
    }
}
