<?php

namespace EcoModular\Wishlist\Models;

use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Customer or guest wishlist.
 */
class Wishlist extends Model
{
    protected $table = 'wishlists';

    protected $guarded = [];

    protected $casts = [
        'is_public' => 'boolean',
    ];

    public static function generateShareToken(): string
    {
        return Str::random(24);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(WishlistItem::class);
    }

    public function hasProduct(Product $product, ?ProductVariant $variant = null): bool
    {
        return $this->items()
            ->where('product_id', $product->id)
            ->when($variant !== null, fn ($q) => $q->where('variant_id', $variant->id))
            ->exists();
    }
}
