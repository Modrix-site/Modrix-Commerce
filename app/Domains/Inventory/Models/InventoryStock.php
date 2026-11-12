<?php

namespace App\Domains\Inventory\Models;

use App\Domains\Catalog\Models\ProductVariant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Quantity on hand of one variant at one location.
 * Only InventoryService changes `on_hand`, always together with a movement row.
 *
 * @property int $id
 * @property int $variant_id
 * @property int $location_id
 * @property int $on_hand
 */
class InventoryStock extends Model
{
    protected $guarded = ['id', 'on_hand'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['on_hand' => 'integer'];
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id')->withTrashed();
    }

    /**
     * @return BelongsTo<InventoryLocation, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'location_id');
    }
}
