<?php

namespace EcoModular\MultiVendor\Services;

use App\Core\Settings\StoreSettings;
use App\Domains\Catalog\Actions\SaveProduct;
use App\Domains\Catalog\Enums\ProductStatus;
use App\Domains\Catalog\Enums\ProductType;
use App\Domains\Catalog\Models\Brand;
use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\StorefrontCatalog;
use App\Domains\Orders\Models\Order;
use App\Models\User;
use EcoModular\MultiVendor\Models\MarketplaceVendor;
use EcoModular\MultiVendor\Models\MarketplaceVendorOrder;
use EcoModular\MultiVendor\Models\MarketplaceVendorPayout;
use EcoModular\MultiVendor\Models\MarketplaceVendorProduct;
use EcoModular\MultiVendor\Support\MultiVendorSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;

class MultiVendorService
{
    /** @var array<int, array{vendor_id: int, store_name: string, slug: string, is_verified: bool}|null>|null */
    private ?array $productVendorMap = null;

    public function __construct(
        private readonly MultiVendorSettings $settings,
    ) {}

    public function forgetCache(): void
    {
        $this->productVendorMap = null;
    }

    /**
     * Fast lookup of vendor badge data for a given product ID.
     *
     * @return array{vendor_id: int, store_name: string, slug: string, is_verified: bool}|null
     */
    public function vendorSummaryForProduct(int $productId): ?array
    {
        if (! $this->settings->enabled()) {
            return null;
        }

        if ($this->productVendorMap === null) {
            $rows = MarketplaceVendorProduct::query()
                ->where('approval_status', MarketplaceVendorProduct::STATUS_APPROVED)
                ->with('vendor')
                ->get();

            $this->productVendorMap = [];
            foreach ($rows as $row) {
                if ($row->vendor instanceof MarketplaceVendor && $row->vendor->isApproved()) {
                    $this->productVendorMap[$row->product_id] = [
                        'vendor_id' => $row->vendor->id,
                        'store_name' => $row->vendor->store_name,
                        'slug' => $row->vendor->slug,
                        'is_verified' => $row->vendor->isVerified(),
                    ];
                }
            }
        }

        return $this->productVendorMap[$productId] ?? null;
    }

    /**
     * Registers a new vendor application with optional KYB business verification details
     * and automatic seller User account creation when password is provided.
     *
     * @param  array<string, mixed>  $data
     */
    public function registerVendor(array $data, ?User $user = null): MarketplaceVendor
    {
        $this->forgetCache();

        $email = Str::lower(trim((string) ($data['email'] ?? $user?->email ?? '')));
        $accountName = trim((string) ($data['account_name'] ?? $data['store_name'] ?? 'Seller'));

        if ($user === null && $email !== '') {
            $existingUser = User::query()->where('email', $email)->first();
            if ($existingUser instanceof User) {
                $user = $existingUser;
                if (filled($data['password'] ?? null) && ! empty($data['update_existing_password'])) {
                    $user->password = (string) $data['password'];
                    $user->save();
                }
            } elseif (filled($data['password'] ?? null)) {
                $user = new User([
                    'name' => $accountName !== '' ? $accountName : 'Marketplace Seller',
                    'email' => $email,
                    'password' => (string) $data['password'],
                ]);
                $user->email_verified_at = now();
                $user->save();
            }
        }

        $slug = $this->uniqueSlug((string) ($data['slug'] ?? $data['store_name']));
        $autoApprove = $this->settings->autoApproveVendors();

        $hasKybDetails = filled($data['trade_license_number'] ?? null)
            || filled($data['tax_id'] ?? null)
            || filled($data['legal_business_name'] ?? null);

        /** @var MarketplaceVendor $vendor */
        $vendor = MarketplaceVendor::query()->create([
            'user_id' => $user?->id ?? ($data['user_id'] ?? null),
            'store_name' => trim((string) $data['store_name']),
            'slug' => $slug,
            'tagline' => isset($data['tagline']) ? trim((string) $data['tagline']) : null,
            'description' => isset($data['description']) ? trim((string) $data['description']) : null,
            'email' => $email,
            'phone' => isset($data['phone']) ? trim((string) $data['phone']) : null,
            'logo_url' => $data['logo_url'] ?? null,
            'banner_url' => $data['banner_url'] ?? null,
            'status' => $autoApprove ? MarketplaceVendor::STATUS_APPROVED : MarketplaceVendor::STATUS_PENDING,
            'approved_at' => $autoApprove ? now() : null,
            'verification_status' => $hasKybDetails
                ? MarketplaceVendor::VERIFICATION_PENDING
                : MarketplaceVendor::VERIFICATION_UNVERIFIED,
            'business_type' => (string) ($data['business_type'] ?? 'llc'),
            'legal_business_name' => $data['legal_business_name'] ?? null,
            'tax_id' => $data['tax_id'] ?? null,
            'trade_license_number' => $data['trade_license_number'] ?? null,
            'identity_document_ref' => $data['identity_document_ref'] ?? null,
            'business_address' => $data['business_address'] ?? null,
            'city' => $data['city'] ?? null,
            'country_code' => strtoupper((string) ($data['country_code'] ?? 'AE')),
            'commission_rate' => isset($data['commission_rate']) && $data['commission_rate'] !== ''
                ? (float) $data['commission_rate']
                : null,
            'payout_method' => (string) ($data['payout_method'] ?? 'bank_transfer'),
            'auto_payout_enabled' => (bool) ($data['auto_payout_enabled'] ?? true),
            'bank_name' => $data['bank_name'] ?? null,
            'bank_account_name' => $data['bank_account_name'] ?? null,
            'bank_account_number' => $data['bank_account_number'] ?? null,
            'bank_swift_code' => $data['bank_swift_code'] ?? null,
            'payout_email' => $data['payout_email'] ?? null,
        ]);

        return $vendor;
    }

    /**
     * Creates or updates the login User account for a MarketplaceVendor and links user_id.
     */
    public function provisionVendorUserAccount(
        MarketplaceVendor $vendor,
        string $email,
        string $password,
        ?string $name = null
    ): User {
        $normalizedEmail = Str::lower(trim($email));
        $displayName = trim($name ?: $vendor->store_name);

        $user = $vendor->user;
        if (! $user instanceof User) {
            $user = User::query()->where('email', $normalizedEmail)->first();
        }

        if ($user instanceof User) {
            $user->name = $displayName ?: $user->name;
            $user->email = $normalizedEmail;
            $user->password = $password;
            $user->email_verified_at ??= now();
            $user->save();
        } else {
            $user = new User([
                'name' => $displayName ?: 'Marketplace Seller',
                'email' => $normalizedEmail,
                'password' => $password,
            ]);
            $user->email_verified_at = now();
            $user->save();
        }

        $vendor->user_id = $user->id;
        $vendor->email = $normalizedEmail;
        $vendor->save();

        return $user;
    }

    /**
     * Submits or updates a vendor's KYB business verification credentials.
     *
     * @param  array<string, mixed>  $data
     */
    public function submitBusinessVerification(MarketplaceVendor $vendor, array $data): MarketplaceVendor
    {
        $vendor->fill([
            'business_type' => (string) ($data['business_type'] ?? $vendor->business_type),
            'legal_business_name' => $data['legal_business_name'] ?? $vendor->legal_business_name,
            'tax_id' => $data['tax_id'] ?? $vendor->tax_id,
            'trade_license_number' => $data['trade_license_number'] ?? $vendor->trade_license_number,
            'identity_document_ref' => $data['identity_document_ref'] ?? $vendor->identity_document_ref,
            'business_address' => $data['business_address'] ?? $vendor->business_address,
            'city' => $data['city'] ?? $vendor->city,
            'country_code' => strtoupper((string) ($data['country_code'] ?? $vendor->country_code ?: 'AE')),
            'verification_status' => MarketplaceVendor::VERIFICATION_PENDING,
            'rejection_reason' => null,
        ])->save();

        return $vendor;
    }

    /**
     * Admin review of a vendor's KYB business verification.
     */
    public function reviewBusinessVerification(
        MarketplaceVendor $vendor,
        string $verificationStatus,
        ?string $notes = null,
        ?string $rejectionReason = null,
        ?int $actorId = null
    ): MarketplaceVendor {
        $this->forgetCache();

        $updates = [
            'verification_status' => $verificationStatus,
            'verification_notes' => $notes ?? $vendor->verification_notes,
            'rejection_reason' => $verificationStatus === MarketplaceVendor::VERIFICATION_REJECTED
                || $verificationStatus === MarketplaceVendor::VERIFICATION_MORE_INFO
                ? $rejectionReason
                : null,
        ];

        if ($verificationStatus === MarketplaceVendor::VERIFICATION_VERIFIED) {
            $updates['verified_at'] = now();
            $updates['verified_by'] = $actorId;

            if ($vendor->status === MarketplaceVendor::STATUS_PENDING) {
                $updates['status'] = MarketplaceVendor::STATUS_APPROVED;
                $updates['approved_at'] = now();
            }
        }

        $vendor->fill($updates)->save();

        return $vendor;
    }

    /**
     * Assigns or updates vendor ownership for a core Product.
     */
    public function assignProductToVendor(
        Product $product,
        ?int $vendorId,
        ?float $commissionOverride = null,
        string $approvalStatus = MarketplaceVendorProduct::STATUS_APPROVED,
        ?string $vendorSku = null
    ): ?MarketplaceVendorProduct {
        $this->forgetCache();

        if ($vendorId === null) {
            MarketplaceVendorProduct::query()->where('product_id', $product->id)->delete();

            return null;
        }

        /** @var MarketplaceVendorProduct $vp */
        $vp = MarketplaceVendorProduct::query()->updateOrCreate(
            ['product_id' => $product->id],
            [
                'vendor_id' => $vendorId,
                'vendor_sku' => $vendorSku,
                'approval_status' => $approvalStatus,
                'commission_override' => $commissionOverride,
                'approved_at' => $approvalStatus === MarketplaceVendorProduct::STATUS_APPROVED ? now() : null,
            ]
        );

        return $vp;
    }

    /**
     * Creates a new Product in the core catalog on behalf of a Vendor.
     *
     * @param  array{name: string, sku: string, price: string|float, stock?: int|null, short_description?: string|null, description?: string|null, category_ids?: list<int>}  $data
     */
    public function createVendorProduct(MarketplaceVendor $vendor, array $data, ?User $actor = null): MarketplaceVendorProduct
    {
        $this->forgetCache();

        $autoApprove = $this->settings->autoApproveProducts()
            && (! $this->settings->requireBusinessVerification() || $vendor->isVerified());

        $currency = app(StoreSettings::class)->currency();
        $sku = trim((string) ($data['sku'] ?? 'VND-'.$vendor->id.'-'.Str::upper(Str::random(5))));

        $product = app(SaveProduct::class)->handle(
            null,
            [
                'type' => ProductType::Simple->value,
                'name' => trim((string) $data['name']),
                'status' => $autoApprove ? ProductStatus::Published->value : ProductStatus::Draft->value,
                'short_description' => $data['short_description'] ?? null,
                'description' => $data['description'] ?? null,
                'category_ids' => $data['category_ids'] ?? [],
                'variants' => [
                    [
                        'sku' => $sku,
                        'price' => number_format((float) $data['price'], 2, '.', ''),
                        'track_inventory' => isset($data['stock']),
                        'initial_stock' => isset($data['stock']) ? (int) $data['stock'] : 10,
                        'is_active' => true,
                    ],
                ],
            ],
            $currency,
            $actor
        );

        /** @var MarketplaceVendorProduct $vp */
        $vp = MarketplaceVendorProduct::query()->create([
            'vendor_id' => $vendor->id,
            'product_id' => $product->id,
            'vendor_sku' => $sku,
            'approval_status' => $autoApprove
                ? MarketplaceVendorProduct::STATUS_APPROVED
                : MarketplaceVendorProduct::STATUS_PENDING,
            'approved_at' => $autoApprove ? now() : null,
        ]);

        return $vp;
    }

    /**
     * Approves or rejects a vendor-submitted product.
     */
    public function reviewVendorProduct(
        MarketplaceVendorProduct $vp,
        string $approvalStatus,
        ?string $rejectionNote = null
    ): MarketplaceVendorProduct {
        $this->forgetCache();

        $vp->fill([
            'approval_status' => $approvalStatus,
            'rejection_note' => $approvalStatus === MarketplaceVendorProduct::STATUS_REJECTED ? $rejectionNote : null,
            'approved_at' => $approvalStatus === MarketplaceVendorProduct::STATUS_APPROVED ? now() : null,
        ])->save();

        $product = $vp->product;
        if ($product instanceof Product) {
            if ($approvalStatus === MarketplaceVendorProduct::STATUS_APPROVED && $product->status !== ProductStatus::Published) {
                $product->status = ProductStatus::Published;
                $product->published_at ??= now();
                $product->save();
            } elseif ($approvalStatus === MarketplaceVendorProduct::STATUS_REJECTED && $product->status === ProductStatus::Published) {
                $product->status = ProductStatus::Draft;
                $product->save();
            }
        }

        return $vp;
    }

    /**
     * Splits an Order into vendor sub-orders, calculates marketplace commission
     * and net vendor earnings, credits vendor balances, and triggers auto-payouts when enabled.
     *
     * @return list<MarketplaceVendorOrder>
     */
    public function recordOrderCommissions(Order $order): array
    {
        if (! $this->settings->enabled()) {
            return [];
        }

        $order->loadMissing('items');
        $created = [];

        foreach ($order->items as $item) {
            if ($item->product_id === null) {
                continue;
            }

            // Idempotent check
            $existing = MarketplaceVendorOrder::query()->where('order_item_id', $item->id)->first();
            if ($existing instanceof MarketplaceVendorOrder) {
                $created[] = $existing;

                continue;
            }

            $vp = MarketplaceVendorProduct::query()
                ->where('product_id', $item->product_id)
                ->where('approval_status', MarketplaceVendorProduct::STATUS_APPROVED)
                ->with('vendor')
                ->first();

            if (! $vp instanceof MarketplaceVendorProduct || ! $vp->vendor instanceof MarketplaceVendor) {
                continue;
            }

            $vendor = $vp->vendor;
            if (! $vendor->isApproved()) {
                continue;
            }

            $rate = $vp->commission_override !== null
                ? (float) $vp->commission_override
                : ($vendor->commission_rate !== null
                    ? (float) $vendor->commission_rate
                    : $this->settings->defaultCommissionRate());

            $rate = max(0.0, min(95.0, $rate));
            $gross = max(0.0, (float) $item->subtotal - (float) $item->discount_total);
            if ($gross <= 0.0 && (float) $item->total > 0.0) {
                $gross = (float) $item->total;
            }

            $commission = round($gross * ($rate / 100.0), 4);
            $earning = round(max(0.0, $gross - $commission), 4);

            $vendorOrder = DB::transaction(function () use ($vendor, $order, $item, $gross, $rate, $commission, $earning) {
                /** @var MarketplaceVendorOrder $record */
                $record = MarketplaceVendorOrder::query()->create([
                    'vendor_id' => $vendor->id,
                    'order_id' => $order->id,
                    'order_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'order_number' => $order->number,
                    'product_name' => $item->product_name,
                    'quantity' => $item->quantity,
                    'currency' => $order->currency,
                    'gross_amount' => $gross,
                    'commission_rate' => $rate,
                    'commission_amount' => $commission,
                    'vendor_earning' => $earning,
                    'fulfillment_status' => 'unfulfilled',
                    'payout_status' => MarketplaceVendorOrder::PAYOUT_CLEARED,
                ]);

                $vendor->increment('total_sales_amount', $gross);
                $vendor->increment('total_commission_amount', $commission);
                $vendor->increment('available_balance', $earning);

                return $record;
            });

            $created[] = $vendorOrder;

            // Check if automatic store payout is configured and vendor balance qualifies
            $vendor->refresh();
            if ($this->settings->payoutMode() === 'automatic_on_order'
                && $vendor->auto_payout_enabled
                && (float) $vendor->available_balance >= $this->settings->minPayoutAmount()
            ) {
                $this->createPayout($vendor, (float) $vendor->available_balance, false, 'Automatic marketplace payout on order '.$order->number);
            }
        }

        return $created;
    }

    /**
     * Creates a vendor payout request or automatic store disbursement.
     */
    public function createPayout(
        MarketplaceVendor $vendor,
        ?float $requestedAmount = null,
        bool $requestedByVendor = true,
        ?string $notes = null
    ): MarketplaceVendorPayout {
        $available = (float) $vendor->available_balance;
        $amount = $requestedAmount !== null ? round($requestedAmount, 4) : $available;

        if ($amount <= 0 || $amount > $available + 0.0001) {
            throw new InvalidArgumentException('Requested payout amount exceeds available vendor balance.');
        }

        if ($requestedByVendor && $amount < $this->settings->minPayoutAmount()) {
            throw new InvalidArgumentException('Payout amount must be at least '.number_format($this->settings->minPayoutAmount(), 2).'.');
        }

        $feePercent = $this->settings->payoutFeePercent();
        $fee = round($amount * ($feePercent / 100.0), 4);
        $net = round(max(0.0, $amount - $fee), 4);
        $currency = app(StoreSettings::class)->currency();

        return DB::transaction(function () use ($vendor, $amount, $fee, $net, $currency, $requestedByVendor, $notes) {
            $reference = 'PAY-'.now()->format('Ymd').'-'. str_pad((string) (MarketplaceVendorPayout::query()->count() + 1), 4, '0', STR_PAD_LEFT).'-'.Str::upper(Str::random(3));

            /** @var MarketplaceVendorPayout $payout */
            $payout = MarketplaceVendorPayout::query()->create([
                'vendor_id' => $vendor->id,
                'reference' => $reference,
                'currency' => $currency,
                'amount' => $amount,
                'fee_amount' => $fee,
                'net_amount' => $net,
                'payout_method' => $vendor->payout_method ?: 'bank_transfer',
                'destination_summary' => $vendor->payoutDestinationSummary(),
                'status' => MarketplaceVendorPayout::STATUS_PENDING,
                'requested_by_vendor' => $requestedByVendor,
                'notes' => $notes,
            ]);

            $vendor->decrement('available_balance', $amount);

            // Link cleared vendor orders to this payout
            MarketplaceVendorOrder::query()
                ->where('vendor_id', $vendor->id)
                ->where('payout_status', MarketplaceVendorOrder::PAYOUT_CLEARED)
                ->whereNull('payout_id')
                ->update([
                    'payout_id' => $payout->id,
                ]);

            return $payout;
        });
    }

    /**
     * Processes a payout (Approve, Mark Paid with transaction reference, or Reject & refund balance).
     */
    public function processPayout(
        MarketplaceVendorPayout $payout,
        string $status,
        ?string $transactionReference = null,
        ?string $notes = null,
        ?int $actorId = null
    ): MarketplaceVendorPayout {
        return DB::transaction(function () use ($payout, $status, $transactionReference, $notes, $actorId) {
            $previousStatus = $payout->status;
            $vendor = $payout->vendor;

            $payout->fill([
                'status' => $status,
                'transaction_reference' => $transactionReference ?? $payout->transaction_reference,
                'notes' => $notes ?? $payout->notes,
                'processed_by' => $actorId ?? $payout->processed_by,
                'processed_at' => in_array($status, [MarketplaceVendorPayout::STATUS_PAID, MarketplaceVendorPayout::STATUS_REJECTED], true)
                    ? now()
                    : $payout->processed_at,
            ])->save();

            if ($vendor instanceof MarketplaceVendor) {
                if ($status === MarketplaceVendorPayout::STATUS_PAID && $previousStatus !== MarketplaceVendorPayout::STATUS_PAID) {
                    $vendor->increment('paid_out_total', (float) $payout->net_amount);

                    MarketplaceVendorOrder::query()
                        ->where('payout_id', $payout->id)
                        ->update(['payout_status' => MarketplaceVendorOrder::PAYOUT_PAID_OUT]);
                } elseif ($status === MarketplaceVendorPayout::STATUS_REJECTED && $previousStatus !== MarketplaceVendorPayout::STATUS_REJECTED) {
                    // Return funds back to the vendor's available balance
                    $vendor->increment('available_balance', (float) $payout->amount);

                    MarketplaceVendorOrder::query()
                        ->where('payout_id', $payout->id)
                        ->update([
                            'payout_id' => null,
                            'payout_status' => MarketplaceVendorOrder::PAYOUT_CLEARED,
                        ]);
                }
            }

            return $payout;
        });
    }

    /**
     * Generates batch payouts for all approved vendors whose balance meets the minimum threshold.
     */
    public function generateBatchPayouts(): int
    {
        $minAmount = $this->settings->minPayoutAmount();
        $vendors = MarketplaceVendor::query()
            ->where('status', MarketplaceVendor::STATUS_APPROVED)
            ->where('available_balance', '>=', $minAmount)
            ->get();

        $count = 0;
        foreach ($vendors as $vendor) {
            if ($this->settings->requireBusinessVerification() && ! $vendor->isVerified()) {
                continue;
            }

            $this->createPayout($vendor, (float) $vendor->available_balance, false, 'Scheduled marketplace batch payout');
            $count++;
        }

        return $count;
    }

    /**
     * Summary statistics for the Admin Dashboard & Multi-Vendor Control Center.
     *
     * @return array<string, mixed>
     */
    public function stats(): array
    {
        $totalVendors = MarketplaceVendor::query()->count();
        $approvedVendors = MarketplaceVendor::query()->where('status', MarketplaceVendor::STATUS_APPROVED)->count();
        $pendingVendors = MarketplaceVendor::query()->where('status', MarketplaceVendor::STATUS_PENDING)->count();
        $verifiedVendors = MarketplaceVendor::query()->where('verification_status', MarketplaceVendor::VERIFICATION_VERIFIED)->count();
        $pendingVerifications = MarketplaceVendor::query()->where('verification_status', MarketplaceVendor::VERIFICATION_PENDING)->count();
        $vendorProducts = MarketplaceVendorProduct::query()->count();
        $pendingProducts = MarketplaceVendorProduct::query()->where('approval_status', MarketplaceVendorProduct::STATUS_PENDING)->count();
        $grossSales = (float) MarketplaceVendorOrder::query()->sum('gross_amount');
        $marketplaceCommission = (float) MarketplaceVendorOrder::query()->sum('commission_amount');
        $vendorEarnings = (float) MarketplaceVendorOrder::query()->sum('vendor_earning');
        $pendingPayoutsCount = MarketplaceVendorPayout::query()->where('status', MarketplaceVendorPayout::STATUS_PENDING)->count();
        $pendingPayoutsAmount = (float) MarketplaceVendorPayout::query()->where('status', MarketplaceVendorPayout::STATUS_PENDING)->sum('amount');
        $paidPayoutsAmount = (float) MarketplaceVendorPayout::query()->where('status', MarketplaceVendorPayout::STATUS_PAID)->sum('net_amount');

        return [
            'total_vendors' => $totalVendors,
            'approved_vendors' => $approvedVendors,
            'pending_vendors' => $pendingVendors,
            'verified_vendors' => $verifiedVendors,
            'pending_verifications' => $pendingVerifications,
            'vendor_products' => $vendorProducts,
            'pending_products' => $pendingProducts,
            'gross_sales' => $grossSales,
            'marketplace_commission' => $marketplaceCommission,
            'vendor_earnings' => $vendorEarnings,
            'pending_payouts_count' => $pendingPayoutsCount,
            'pending_payouts_amount' => $pendingPayoutsAmount,
            'paid_payouts_amount' => $paidPayoutsAmount,
        ];
    }

    /**
     * Builds an Amazon-style paginated, faceted marketplace product catalog using core StorefrontCatalog cards.
     * Supports filtering across all marketplace products/vendors OR scoped to a single approved vendor storefront.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function marketplaceCatalogListing(
        array $params,
        StorefrontCatalog $catalog,
        string $currency,
        ?int $vendorId = null,
        int $perPage = 20,
    ): array {
        $baseCurrency = app(StoreSettings::class)->currency();
        $priceCurrency = $this->resolvePriceCurrency($currency, $baseCurrency);

        $scopedQuery = Product::query()
            ->visible()
            ->whereHas('variants', function (Builder $vq) use ($priceCurrency) {
                $vq->where('is_active', true)
                    ->whereNull('deleted_at')
                    ->whereExists(function ($pq) use ($priceCurrency) {
                        $pq->selectRaw('1')
                            ->from('product_prices')
                            ->whereColumn('product_prices.variant_id', 'product_variants.id')
                            ->where('product_prices.currency', $priceCurrency);
                    });
            });

        if ($vendorId !== null) {
            $scopedIds = MarketplaceVendorProduct::query()
                ->where('vendor_id', $vendorId)
                ->where('approval_status', MarketplaceVendorProduct::STATUS_APPROVED)
                ->pluck('product_id')
                ->all();

            $scopedQuery->whereIn('products.id', $scopedIds === [] ? [0] : array_map('intval', $scopedIds));
        }

        // Compute facets from the scoped catalog BEFORE applying facet selections so shoppers always see available choices
        $scopedProductIds = array_map('intval', (clone $scopedQuery)->pluck('products.id')->all());
        $facets = $this->buildCatalogFacets($scopedProductIds, $priceCurrency, $vendorId === null);

        // Apply active Amazon-style filters + sorting
        $filteredQuery = clone $scopedQuery;
        $this->applyCatalogFilters($filteredQuery, $params, $priceCurrency, $vendorId === null);

        $sort = $this->normalizeSort((string) ($params['sort'] ?? 'newest'));
        $this->applyCatalogSort($filteredQuery, $sort, $priceCurrency);

        $page = max(1, (int) ($params['page'] ?? 1));
        /** @var LengthAwarePaginator<int, Product> $idPaginator */
        $idPaginator = $filteredQuery->paginate($perPage, ['products.id'], 'page', $page)
            ->appends(Arr::except($params, ['page']));
        $pageProductIds = array_values(array_map('intval', $idPaginator->getCollection()->pluck('id')->all()));

        $cardsById = collect($catalog->cardsFor($pageProductIds, $currency, count($pageProductIds)))
            ->keyBy('id');

        $orderedCards = [];
        foreach ($pageProductIds as $id) {
            if ($cardsById->has($id)) {
                $orderedCards[] = $cardsById->get($id);
            }
        }

        $productsPayload = $this->serializeProductPaginator($idPaginator, $orderedCards);
        $productMeta = $this->productMarketplaceMeta($pageProductIds);

        return [
            'products' => $productsPayload,
            'productVendors' => $productMeta['vendors'],
            'productStats' => $productMeta['stats'],
            'sort' => $sort,
            'query' => trim((string) ($params['q'] ?? '')) ?: null,
            'activeCategory' => $facets['activeCategoryMap'][trim((string) ($params['category'] ?? ''))] ?? null,
            'filters' => array_filter([
                'category' => ($c = trim((string) ($params['category'] ?? ''))) !== '' ? $c : null,
                'vendor' => ($v = trim((string) ($params['vendor'] ?? ''))) !== '' ? $v : null,
                'verified_only' => $this->boolParam($params['verified_only'] ?? null) ? 1 : null,
                'in_stock' => $this->boolParam($params['in_stock'] ?? null) ? 1 : null,
                'on_sale' => $this->boolParam($params['on_sale'] ?? null) ? 1 : null,
                'min_rating' => is_numeric($params['min_rating'] ?? null) && (int) $params['min_rating'] > 0 ? (int) $params['min_rating'] : null,
                'brand_id' => is_numeric($params['brand_id'] ?? null) && (int) $params['brand_id'] > 0 ? (int) $params['brand_id'] : null,
                'min_price' => is_numeric($params['min_price'] ?? null) ? (string) $params['min_price'] : null,
                'max_price' => is_numeric($params['max_price'] ?? null) ? (string) $params['max_price'] : null,
                'options' => ($opts = $this->intArrayParam($params['options'] ?? null)) !== [] ? $opts : null,
            ], fn ($v) => $v !== null),
            'departments' => $facets['departments'],
            'subcategories' => $facets['subcategories'],
            'filterBrands' => $facets['brands'],
            'filterVendors' => $facets['vendors'],
            'filterOptions' => $facets['options'],
            'filterTags' => $facets['tags'],
            'catalogStats' => $facets['stats'],
        ];
    }

    private function resolvePriceCurrency(string $displayCurrency, string $baseCurrency): string
    {
        $display = strtoupper(trim($displayCurrency));
        $base = strtoupper(trim($baseCurrency));

        if ($display !== '' && $display !== $base && DB::table('product_prices')->where('currency', $display)->exists()) {
            return $display;
        }

        return $base !== '' ? $base : 'USD';
    }

    private function boolParam(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * @return list<int>
     */
    private function intArrayParam(mixed $value): array
    {
        if (! is_array($value)) {
            if (is_string($value) && trim($value) !== '') {
                $value = explode(',', $value);
            } else {
                return [];
            }
        }

        return array_values(array_filter(array_map('intval', $value), fn (int $v) => $v > 0));
    }

    private function normalizeSort(string $sort): string
    {
        $allowed = ['newest', 'best_selling', 'rating', 'price_asc', 'price_desc', 'name'];

        return in_array($sort, $allowed, true) ? $sort : 'newest';
    }

    /**
     * @param  Builder<Product>  $query
     * @param  array<string, mixed>  $params
     */
    private function applyCatalogFilters(Builder $query, array $params, string $currency, bool $allowVendorFilter): void
    {
        $q = trim((string) ($params['q'] ?? ''));
        if ($q !== '') {
            $like = '%'.$q.'%';
            $query->where(function (Builder $sub) use ($like) {
                $sub->where('products.name', 'like', $like)
                    ->orWhere('products.slug', 'like', $like)
                    ->orWhere('products.short_description', 'like', $like)
                    ->orWhereHas('variants', fn (Builder $vq) => $vq->where('sku', 'like', $like))
                    ->orWhereHas('brand', fn (Builder $bq) => $bq->where('name', 'like', $like))
                    ->orWhereIn('products.id', function ($vp) use ($like) {
                        $vp->select('marketplace_vendor_products.product_id')
                            ->from('marketplace_vendor_products')
                            ->join('marketplace_vendors', 'marketplace_vendors.id', '=', 'marketplace_vendor_products.vendor_id')
                            ->where('marketplace_vendor_products.approval_status', MarketplaceVendorProduct::STATUS_APPROVED)
                            ->where('marketplace_vendors.status', MarketplaceVendor::STATUS_APPROVED)
                            ->where('marketplace_vendors.store_name', 'like', $like);
                    });
            });
        }

        $categorySlug = trim((string) ($params['category'] ?? ''));
        if ($categorySlug !== '') {
            $catIds = $this->categoryDescendantIds($categorySlug);
            if ($catIds !== []) {
                $query->whereHas('categories', fn (Builder $cq) => $cq->whereIn('categories.id', $catIds));
            }
        }

        if ($allowVendorFilter) {
            $vendorParam = trim((string) ($params['vendor'] ?? ''));
            if ($vendorParam === 'direct') {
                $query->whereNotIn('products.id', function ($sub) {
                    $sub->select('product_id')
                        ->from('marketplace_vendor_products')
                        ->where('approval_status', MarketplaceVendorProduct::STATUS_APPROVED);
                });
            } elseif ($vendorParam === 'marketplace') {
                $query->whereIn('products.id', function ($sub) {
                    $sub->select('marketplace_vendor_products.product_id')
                        ->from('marketplace_vendor_products')
                        ->join('marketplace_vendors', 'marketplace_vendors.id', '=', 'marketplace_vendor_products.vendor_id')
                        ->where('marketplace_vendor_products.approval_status', MarketplaceVendorProduct::STATUS_APPROVED)
                        ->where('marketplace_vendors.status', MarketplaceVendor::STATUS_APPROVED);
                });
            } elseif ($vendorParam !== '') {
                $query->whereIn('products.id', function ($sub) use ($vendorParam) {
                    $sub->select('marketplace_vendor_products.product_id')
                        ->from('marketplace_vendor_products')
                        ->join('marketplace_vendors', 'marketplace_vendors.id', '=', 'marketplace_vendor_products.vendor_id')
                        ->where('marketplace_vendor_products.approval_status', MarketplaceVendorProduct::STATUS_APPROVED)
                        ->where('marketplace_vendors.status', MarketplaceVendor::STATUS_APPROVED)
                        ->where('marketplace_vendors.slug', $vendorParam);
                });
            }
        }

        if ($this->boolParam($params['verified_only'] ?? null)) {
            $query->whereIn('products.id', function ($sub) {
                $sub->select('marketplace_vendor_products.product_id')
                    ->from('marketplace_vendor_products')
                    ->join('marketplace_vendors', 'marketplace_vendors.id', '=', 'marketplace_vendor_products.vendor_id')
                    ->where('marketplace_vendor_products.approval_status', MarketplaceVendorProduct::STATUS_APPROVED)
                    ->where('marketplace_vendors.status', MarketplaceVendor::STATUS_APPROVED)
                    ->where('marketplace_vendors.verification_status', MarketplaceVendor::VERIFICATION_VERIFIED);
            });
        }

        if (is_numeric($params['min_rating'] ?? null) && (int) $params['min_rating'] > 0) {
            $minRating = (float) $params['min_rating'];
            $query->whereIn('products.id', function ($sub) use ($minRating) {
                $sub->select('marketplace_vendor_products.product_id')
                    ->from('marketplace_vendor_products')
                    ->join('marketplace_vendors', 'marketplace_vendors.id', '=', 'marketplace_vendor_products.vendor_id')
                    ->where('marketplace_vendor_products.approval_status', MarketplaceVendorProduct::STATUS_APPROVED)
                    ->where('marketplace_vendors.status', MarketplaceVendor::STATUS_APPROVED)
                    ->where('marketplace_vendors.rating', '>=', $minRating);
            });
        }

        if (is_numeric($params['brand_id'] ?? null) && (int) $params['brand_id'] > 0) {
            $query->where('products.brand_id', (int) $params['brand_id']);
        }

        if ($this->boolParam($params['in_stock'] ?? null)) {
            $query->whereHas('variants', function (Builder $vq) {
                $vq->where('is_active', true)
                    ->whereNull('deleted_at')
                    ->where(function (Builder $sq) {
                        $sq->where('track_inventory', false)
                            ->orWhere('allow_backorder', true)
                            ->orWhereRaw('COALESCE((SELECT SUM(on_hand) FROM inventory_stocks WHERE inventory_stocks.variant_id = product_variants.id), 0) > 0');
                    });
            });
        }

        if ($this->boolParam($params['on_sale'] ?? null)) {
            $query->whereHas('variants', function (Builder $vq) use ($currency) {
                $vq->where('is_active', true)
                    ->whereNull('deleted_at')
                    ->whereExists(function ($pq) use ($currency) {
                        $pq->selectRaw('1')
                            ->from('product_prices')
                            ->whereColumn('product_prices.variant_id', 'product_variants.id')
                            ->where('product_prices.currency', $currency)
                            ->whereNotNull('product_prices.compare_at_amount')
                            ->whereColumn('product_prices.compare_at_amount', '>', 'product_prices.amount');
                    });
            });
        }

        $minPrice = is_numeric($params['min_price'] ?? null) ? (float) $params['min_price'] : null;
        $maxPrice = is_numeric($params['max_price'] ?? null) ? (float) $params['max_price'] : null;
        if ($minPrice !== null || $maxPrice !== null) {
            $query->whereHas('variants', function (Builder $vq) use ($currency, $minPrice, $maxPrice) {
                $vq->where('is_active', true)
                    ->whereNull('deleted_at')
                    ->whereExists(function ($pq) use ($currency, $minPrice, $maxPrice) {
                        $pq->selectRaw('1')
                            ->from('product_prices')
                            ->whereColumn('product_prices.variant_id', 'product_variants.id')
                            ->where('product_prices.currency', $currency);
                        if ($minPrice !== null) {
                            $pq->where('product_prices.amount', '>=', $minPrice);
                        }
                        if ($maxPrice !== null) {
                            $pq->where('product_prices.amount', '<=', $maxPrice);
                        }
                    });
            });
        }

        $options = $this->intArrayParam($params['options'] ?? null);
        if ($options !== []) {
            $query->whereHas('variants', function (Builder $vq) use ($options) {
                $vq->where('is_active', true)
                    ->whereNull('deleted_at')
                    ->whereExists(function ($oq) use ($options) {
                        $oq->selectRaw('1')
                            ->from('product_variant_options')
                            ->whereColumn('product_variant_options.variant_id', 'product_variants.id')
                            ->whereIn('product_variant_options.attribute_value_id', $options);
                    });
            });
        }
    }

    /**
     * @param  Builder<Product>  $query
     */
    private function applyCatalogSort(Builder $query, string $sort, string $currency): void
    {
        $minPriceSql = "(SELECT MIN(pp.amount) FROM product_prices pp INNER JOIN product_variants pv ON pv.id = pp.variant_id WHERE pv.product_id = products.id AND pv.is_active = 1 AND pv.deleted_at IS NULL AND pp.currency = ?)";

        if ($sort === 'price_asc') {
            $query->orderByRaw("{$minPriceSql} ASC", [$currency])->orderByDesc('products.id');
        } elseif ($sort === 'price_desc') {
            $query->orderByRaw("{$minPriceSql} DESC", [$currency])->orderByDesc('products.id');
        } elseif ($sort === 'name') {
            $query->orderBy('products.name')->orderByDesc('products.id');
        } elseif ($sort === 'best_selling') {
            $salesSql = '(SELECT COALESCE(SUM(oi.quantity), 0) FROM order_items oi WHERE oi.product_id = products.id)';
            $query->orderByRaw("{$salesSql} DESC")
                ->orderByDesc('products.is_featured')
                ->orderByDesc('products.id');
        } elseif ($sort === 'rating') {
            $hasReviews = Schema::hasTable('product_reviews');
            if ($hasReviews) {
                $ratingSql = "(SELECT COALESCE(AVG(pr.rating), 0) FROM product_reviews pr WHERE pr.product_id = products.id AND pr.status = 'approved')";
                $query->orderByRaw("{$ratingSql} DESC");
            }
            $vendorRatingSql = '(SELECT COALESCE(MAX(mv.rating), 4.5) FROM marketplace_vendor_products mvp INNER JOIN marketplace_vendors mv ON mv.id = mvp.vendor_id WHERE mvp.product_id = products.id AND mvp.approval_status = ? AND mv.status = ?)';
            $query->orderByRaw("{$vendorRatingSql} DESC", [
                MarketplaceVendorProduct::STATUS_APPROVED,
                MarketplaceVendor::STATUS_APPROVED,
            ])->orderByDesc('products.id');
        } else {
            $query->orderByDesc('products.is_featured')->orderByDesc('products.id');
        }
    }

    /**
     * @param  list<int>  $productIds
     * @return array<string, mixed>
     */
    private function buildCatalogFacets(array $productIds, string $currency, bool $includeVendors): array
    {
        if ($productIds === []) {
            return [
                'departments' => [],
                'activeCategoryMap' => [],
                'subcategories' => [],
                'brands' => [],
                'vendors' => [],
                'options' => [],
                'tags' => [],
                'stats' => [
                    'total_products' => 0,
                    'deals_count' => 0,
                    'verified_seller_products_count' => 0,
                    'sellers_count' => 0,
                    'direct_count' => 0,
                ],
            ];
        }

        // Departments / Categories present in this catalog
        $categoryRows = DB::table('product_categories')
            ->join('categories', 'categories.id', '=', 'product_categories.category_id')
            ->whereIn('product_categories.product_id', $productIds)
            ->where('categories.is_active', true)
            ->selectRaw('categories.id, categories.name, categories.slug, COUNT(DISTINCT product_categories.product_id) as products_count')
            ->groupBy('categories.id', 'categories.name', 'categories.slug', 'categories.position')
            ->orderByDesc('products_count')
            ->orderBy('categories.position')
            ->get();

        $departments = [];
        $activeCategoryMap = [];
        $subcategories = [];
        foreach ($categoryRows as $row) {
            $entry = [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'slug' => (string) $row->slug,
                'count' => (int) $row->products_count,
                'image_url' => null,
            ];
            $departments[] = $entry;
            $activeCategoryMap[(string) $row->slug] = $entry;
            $subcategories[] = [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'slug' => (string) $row->slug,
                'image_url' => null,
            ];
        }

        // Brands
        $brands = Brand::query()
            ->whereIn('id', function ($sub) use ($productIds) {
                $sub->select('brand_id')
                    ->from('products')
                    ->whereIn('id', $productIds)
                    ->whereNotNull('brand_id');
            })
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Brand $b) => ['id' => $b->id, 'name' => $b->name])
            ->values()
            ->all();

        // Attribute Options (Color, Size, etc.)
        $attrRows = DB::table('product_variant_options')
            ->join('product_variants', 'product_variants.id', '=', 'product_variant_options.variant_id')
            ->join('product_attribute_values', 'product_attribute_values.id', '=', 'product_variant_options.attribute_value_id')
            ->join('product_attributes', 'product_attributes.id', '=', 'product_attribute_values.attribute_id')
            ->whereIn('product_variants.product_id', $productIds)
            ->where('product_variants.is_active', true)
            ->whereNull('product_variants.deleted_at')
            ->select([
                'product_attributes.id as attr_id',
                'product_attributes.name as attr_name',
                'product_attributes.position as attr_pos',
                'product_attribute_values.id as val_id',
                'product_attribute_values.value as val_name',
                'product_attribute_values.position as val_pos',
            ])
            ->distinct()
            ->orderBy('product_attributes.position')
            ->orderBy('product_attribute_values.position')
            ->get();

        $groupedOptions = [];
        foreach ($attrRows as $row) {
            $aid = (int) $row->attr_id;
            if (! isset($groupedOptions[$aid])) {
                $groupedOptions[$aid] = [
                    'id' => $aid,
                    'name' => (string) $row->attr_name,
                    'values' => [],
                ];
            }
            $groupedOptions[$aid]['values'][] = [
                'id' => (int) $row->val_id,
                'value' => (string) $row->val_name,
            ];
        }

        // Sellers facet
        $vendorRows = DB::table('marketplace_vendor_products')
            ->join('marketplace_vendors', 'marketplace_vendors.id', '=', 'marketplace_vendor_products.vendor_id')
            ->whereIn('marketplace_vendor_products.product_id', $productIds)
            ->where('marketplace_vendor_products.approval_status', MarketplaceVendorProduct::STATUS_APPROVED)
            ->where('marketplace_vendors.status', MarketplaceVendor::STATUS_APPROVED)
            ->selectRaw('marketplace_vendors.id, marketplace_vendors.store_name, marketplace_vendors.slug, marketplace_vendors.verification_status, marketplace_vendors.rating, COUNT(DISTINCT marketplace_vendor_products.product_id) as products_count')
            ->groupBy('marketplace_vendors.id', 'marketplace_vendors.store_name', 'marketplace_vendors.slug', 'marketplace_vendors.verification_status', 'marketplace_vendors.rating')
            ->orderByDesc('products_count')
            ->get();

        $vendorOwnedCount = 0;
        $verifiedProductsCount = 0;
        $vendorsList = [];
        foreach ($vendorRows as $row) {
            $count = (int) $row->products_count;
            $isVerified = $row->verification_status === MarketplaceVendor::VERIFICATION_VERIFIED;
            $vendorOwnedCount += $count;
            if ($isVerified) {
                $verifiedProductsCount += $count;
            }
            if ($includeVendors) {
                $vendorsList[] = [
                    'id' => (int) $row->id,
                    'store_name' => (string) $row->store_name,
                    'slug' => (string) $row->slug,
                    'is_verified' => $isVerified,
                    'rating' => round((float) $row->rating, 2),
                    'products_count' => $count,
                    'url' => route('modrixcommerce.multi-vendor.vendors.show', $row->slug),
                ];
            }
        }

        $dealsCount = (int) DB::table('product_variants')
            ->join('product_prices', 'product_prices.variant_id', '=', 'product_variants.id')
            ->whereIn('product_variants.product_id', $productIds)
            ->where('product_variants.is_active', true)
            ->whereNull('product_variants.deleted_at')
            ->where('product_prices.currency', $currency)
            ->whereNotNull('product_prices.compare_at_amount')
            ->whereColumn('product_prices.compare_at_amount', '>', 'product_prices.amount')
            ->distinct('product_variants.product_id')
            ->count('product_variants.product_id');

        // Tags
        $tags = [];
        if (Schema::hasTable('product_tags')) {
            $tags = DB::table('product_tags')
                ->whereIn('product_id', $productIds)
                ->selectRaw('tag, COUNT(DISTINCT product_id) as cnt')
                ->groupBy('tag')
                ->orderByDesc('cnt')
                ->limit(24)
                ->get()
                ->map(fn ($r) => [
                    'name' => (string) $r->tag,
                    'label' => Str::title(str_replace(['-', '_'], ' ', (string) $r->tag)),
                    'count' => (int) $r->cnt,
                ])
                ->all();
        }

        return [
            'departments' => $departments,
            'activeCategoryMap' => $activeCategoryMap,
            'subcategories' => $subcategories,
            'brands' => $brands,
            'vendors' => $vendorsList,
            'options' => array_values($groupedOptions),
            'tags' => $tags,
            'stats' => [
                'total_products' => count($productIds),
                'deals_count' => $dealsCount,
                'verified_seller_products_count' => $verifiedProductsCount,
                'sellers_count' => count($vendorRows),
                'direct_count' => max(0, count($productIds) - $vendorOwnedCount),
            ],
        ];
    }

    /**
     * @return list<int>
     */
    private function categoryDescendantIds(string $slug): array
    {
        $root = Category::query()->where('slug', $slug)->where('is_active', true)->first();
        if (! $root instanceof Category) {
            return [];
        }

        $allCategories = Category::query()->where('is_active', true)->get(['id', 'parent_id']);
        $childrenByParent = [];
        foreach ($allCategories as $cat) {
            if ($cat->parent_id !== null) {
                $childrenByParent[(int) $cat->parent_id][] = (int) $cat->id;
            }
        }

        $collected = [$root->id];
        $queue = [$root->id];
        while ($queue !== []) {
            $current = array_shift($queue);
            foreach ($childrenByParent[$current] ?? [] as $childId) {
                if (! in_array($childId, $collected, true)) {
                    $collected[] = $childId;
                    $queue[] = $childId;
                }
            }
        }

        return $collected;
    }

    /**
     * @param  LengthAwarePaginator<int, Product>  $paginator
     * @param  list<array<string, mixed>>  $cards
     * @return array<string, mixed>
     */
    private function serializeProductPaginator(LengthAwarePaginator $paginator, array $cards): array
    {
        $array = $paginator->toArray();
        $array['data'] = $cards;

        return $array;
    }

    /**
     * @param  list<int>  $productIds
     * @return array{vendors: array<int, array<string, mixed>>, stats: array<int, array<string, mixed>>}
     */
    public function productMarketplaceMeta(array $productIds): array
    {
        if ($productIds === []) {
            return ['vendors' => [], 'stats' => []];
        }

        $vendors = [];
        $rows = MarketplaceVendorProduct::query()
            ->whereIn('product_id', $productIds)
            ->where('approval_status', MarketplaceVendorProduct::STATUS_APPROVED)
            ->with('vendor')
            ->get();

        foreach ($rows as $row) {
            if ($row->vendor instanceof MarketplaceVendor && $row->vendor->isApproved()) {
                $vendors[$row->product_id] = [
                    'vendor_id' => $row->vendor->id,
                    'store_name' => $row->vendor->store_name,
                    'slug' => $row->vendor->slug,
                    'is_verified' => $row->vendor->isVerified(),
                    'rating' => round((float) $row->vendor->rating, 2),
                    'store_url' => route('modrixcommerce.multi-vendor.vendors.show', $row->vendor->slug),
                ];
            }
        }

        $sales = DB::table('order_items')
            ->whereIn('product_id', $productIds)
            ->selectRaw('product_id, COALESCE(SUM(quantity), 0) as units_sold')
            ->groupBy('product_id')
            ->pluck('units_sold', 'product_id');

        $reviews = collect();
        if (Schema::hasTable('product_reviews')) {
            $reviews = DB::table('product_reviews')
                ->whereIn('product_id', $productIds)
                ->where('status', 'approved')
                ->selectRaw('product_id, COUNT(*) as review_count, AVG(rating) as avg_rating')
                ->groupBy('product_id')
                ->get()
                ->keyBy('product_id');
        }

        $stats = [];
        foreach ($productIds as $pid) {
            $rev = $reviews->get($pid);
            $stats[$pid] = [
                'units_sold' => (int) ($sales->get($pid) ?? 0),
                'review_count' => $rev ? (int) $rev->review_count : 0,
                'avg_rating' => $rev ? round((float) $rev->avg_rating, 1) : null,
            ];
        }

        return ['vendors' => $vendors, 'stats' => $stats];
    }

    private function uniqueSlug(string $input): string
    {
        $base = Str::slug($input) ?: 'vendor-store';
        $slug = $base;
        $i = 2;

        while (MarketplaceVendor::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
