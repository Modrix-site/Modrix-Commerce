<?php

namespace EcoModular\MultiVendor\Models;

use App\Domains\Catalog\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketplaceVendor extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_REJECTED = 'rejected';

    public const VERIFICATION_UNVERIFIED = 'unverified';
    public const VERIFICATION_PENDING = 'pending_review';
    public const VERIFICATION_VERIFIED = 'verified';
    public const VERIFICATION_REJECTED = 'rejected';
    public const VERIFICATION_MORE_INFO = 'more_info_required';

    public const BUSINESS_TYPES = [
        'individual' => 'Individual / Sole Trader',
        'sole_proprietorship' => 'Sole Proprietorship',
        'llc' => 'Limited Liability Company (LLC)',
        'corporation' => 'Corporation / Joint Stock',
        'partnership' => 'Registered Partnership',
    ];

    public const PAYOUT_METHODS = [
        'bank_transfer' => 'Bank Wire / IBAN Transfer',
        'paypal' => 'PayPal Payouts',
        'stripe_connect' => 'Stripe Connect Express',
        'wise' => 'Wise Business Transfer',
        'manual' => 'Manual Store Settlement',
    ];

    protected $table = 'marketplace_vendors';

    protected $fillable = [
        'user_id',
        'store_name',
        'slug',
        'tagline',
        'description',
        'email',
        'phone',
        'logo_url',
        'banner_url',
        'status',
        'verification_status',
        'business_type',
        'legal_business_name',
        'tax_id',
        'trade_license_number',
        'identity_document_ref',
        'business_address',
        'city',
        'country_code',
        'verification_notes',
        'rejection_reason',
        'verified_at',
        'verified_by',
        'commission_rate',
        'payout_method',
        'auto_payout_enabled',
        'bank_name',
        'bank_account_name',
        'bank_account_number',
        'bank_swift_code',
        'payout_email',
        'is_featured',
        'rating',
        'total_sales_amount',
        'total_commission_amount',
        'available_balance',
        'paid_out_total',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'verified_by' => 'integer',
            'commission_rate' => 'decimal:2',
            'auto_payout_enabled' => 'boolean',
            'is_featured' => 'boolean',
            'rating' => 'decimal:2',
            'total_sales_amount' => 'decimal:4',
            'total_commission_amount' => 'decimal:4',
            'available_balance' => 'decimal:4',
            'paid_out_total' => 'decimal:4',
            'verified_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function vendorProducts(): HasMany
    {
        return $this->hasMany(MarketplaceVendorProduct::class, 'vendor_id');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(
            Product::class,
            'marketplace_vendor_products',
            'vendor_id',
            'product_id'
        )->withPivot(['vendor_sku', 'approval_status', 'commission_override', 'approved_at']);
    }

    public function vendorOrders(): HasMany
    {
        return $this->hasMany(MarketplaceVendorOrder::class, 'vendor_id');
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(MarketplaceVendorPayout::class, 'vendor_id');
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isVerified(): bool
    {
        return $this->verification_status === self::VERIFICATION_VERIFIED;
    }

    public function payoutDestinationSummary(): string
    {
        return match ($this->payout_method) {
            'bank_transfer' => trim(($this->bank_name ?: 'Bank').' '.($this->bank_account_number ? '••••'.substr($this->bank_account_number, -4) : '')),
            'paypal' => 'PayPal: '.($this->payout_email ?: $this->email),
            'stripe_connect' => 'Stripe: '.($this->payout_email ?: 'Connected Account'),
            'wise' => 'Wise: '.($this->payout_email ?: $this->email),
            default => 'Manual Settlement ('.$this->email.')',
        };
    }
}
