<?php

namespace EcoModular\MultiVendor\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketplaceVendorPayout extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_PAID = 'paid';
    public const STATUS_REJECTED = 'rejected';

    protected $table = 'marketplace_vendor_payouts';

    protected $fillable = [
        'vendor_id',
        'reference',
        'currency',
        'amount',
        'fee_amount',
        'net_amount',
        'payout_method',
        'destination_summary',
        'status',
        'requested_by_vendor',
        'transaction_reference',
        'notes',
        'processed_by',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'vendor_id' => 'integer',
            'amount' => 'decimal:4',
            'fee_amount' => 'decimal:4',
            'net_amount' => 'decimal:4',
            'requested_by_vendor' => 'boolean',
            'processed_by' => 'integer',
            'processed_at' => 'datetime',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(MarketplaceVendor::class, 'vendor_id');
    }

    public function vendorOrders(): HasMany
    {
        return $this->hasMany(MarketplaceVendorOrder::class, 'payout_id');
    }
}
