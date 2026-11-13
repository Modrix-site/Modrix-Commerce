<?php

namespace EcoModular\MultiVendor\Models;

use App\Domains\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceVendorProduct extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $table = 'marketplace_vendor_products';

    protected $fillable = [
        'vendor_id',
        'product_id',
        'vendor_sku',
        'approval_status',
        'commission_override',
        'rejection_note',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'vendor_id' => 'integer',
            'product_id' => 'integer',
            'commission_override' => 'decimal:2',
            'approved_at' => 'datetime',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(MarketplaceVendor::class, 'vendor_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
