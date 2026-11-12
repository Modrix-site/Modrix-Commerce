<?php

namespace App\Domains\Catalog\Models;

use App\Domains\Catalog\Concerns\HasTranslations;
use App\Domains\Catalog\Enums\ProductStatus;
use App\Domains\Catalog\Enums\ProductType;
use App\Domains\Catalog\ProductRelations;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property ProductType $type
 * @property string $name
 * @property string $slug
 * @property string|null $short_description
 * @property string|null $description
 * @property ProductStatus $status
 * @property Carbon|null $published_at
 * @property string|null $meta_title
 * @property string|null $meta_description
 * @property int|null $brand_id
 * @property bool $is_featured
 * @property bool $is_digital
 * @property bool|null $show_buy_button
 * @property-read Brand|null $brand
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable(['type', 'name', 'slug', 'short_description', 'description', 'status', 'published_at', 'meta_title', 'meta_description', 'brand_id', 'is_featured', 'is_digital', 'show_buy_button'])]
#[UseFactory(ProductFactory::class)]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, SoftDeletes;

    /** @use HasTranslations<ProductTranslation> */
    use HasTranslations;

    protected static function translationModel(): string
    {
        return ProductTranslation::class;
    }

    /**
     * @return list<string>
     */
    public static function translatableFields(): array
    {
        return ['name', 'short_description', 'description', 'meta_title', 'meta_description'];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ProductType::class,
            'status' => ProductStatus::class,
            'published_at' => 'datetime',
            'is_featured' => 'boolean',
            'is_digital' => 'boolean',
            'show_buy_button' => 'boolean',
        ];
    }

    /**
     * @return HasMany<ProductVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('position')->orderBy('id');
    }

    /**
     * @return BelongsToMany<Category, $this>
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'product_categories');
    }

    /**
     * @return BelongsTo<Brand, $this>
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * @return HasMany<ProductTag, $this>
     */
    public function tags(): HasMany
    {
        return $this->hasMany(ProductTag::class);
    }

    /**
     * @return list<string>
     */
    public function tagNames(): array
    {
        return array_values(array_map('strval', $this->tags()->orderBy('tag')->pluck('tag')->all()));
    }

    /**
     * Merchant-picked related products ("You may also like"), in order.
     *
     * @return BelongsToMany<Product, $this>
     */
    public function relatedProducts(): BelongsToMany
    {
        return $this->relationsOfType(ProductRelations::RELATED);
    }

    /**
     * Merchant-picked upsells ("Customers also bought"), in order.
     *
     * @return BelongsToMany<Product, $this>
     */
    public function upsellProducts(): BelongsToMany
    {
        return $this->relationsOfType(ProductRelations::UPSELL);
    }

    /**
     * @return BelongsToMany<Product, $this>
     */
    private function relationsOfType(string $type): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_relations', 'product_id', 'related_product_id')
            ->wherePivot('type', $type)
            ->orderByPivot('position');
    }

    /**
     * Files a digital product delivers, in order.
     *
     * @return HasMany<ProductFile, $this>
     */
    public function files(): HasMany
    {
        return $this->hasMany(ProductFile::class)->orderBy('position')->orderBy('id');
    }

    /**
     * @return HasMany<ProductImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('position')->orderBy('id');
    }

    /**
     * Visible on the storefront: published, publish date reached, not deleted.
     *
     * @param  Builder<Product>  $query
     */
    public function scopeVisible(Builder $query): void
    {
        $query->where('status', ProductStatus::Published->value)
            ->where(fn ($q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()));
    }

    public function isVisible(): bool
    {
        return $this->status === ProductStatus::Published
            && ($this->published_at === null || $this->published_at->lte(now()))
            && ! $this->trashed();
    }
}
