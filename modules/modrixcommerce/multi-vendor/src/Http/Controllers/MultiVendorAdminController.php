<?php

namespace EcoModular\MultiVendor\Http\Controllers;

use App\Core\Settings\StoreSettings;
use App\Domains\Catalog\Models\Product;
use App\Http\Controllers\Controller;
use EcoModular\MultiVendor\Models\MarketplaceVendor;
use EcoModular\MultiVendor\Models\MarketplaceVendorOrder;
use EcoModular\MultiVendor\Models\MarketplaceVendorPayout;
use EcoModular\MultiVendor\Models\MarketplaceVendorProduct;
use EcoModular\MultiVendor\Services\MultiVendorService;
use EcoModular\MultiVendor\Support\MultiVendorSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MultiVendorAdminController extends Controller
{
    public function __construct(
        private readonly MultiVendorSettings $settings,
        private readonly MultiVendorService $service,
    ) {}

    public function index(Request $request, StoreSettings $store): Response
    {
        $currency = $store->currency();

        $vendors = MarketplaceVendor::query()
            ->withCount(['vendorProducts', 'vendorOrders', 'payouts'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (MarketplaceVendor $v) => [
                'id' => $v->id,
                'user_id' => $v->user_id,
                'has_user_account' => $v->user_id !== null,
                'store_name' => $v->store_name,
                'slug' => $v->slug,
                'tagline' => $v->tagline,
                'description' => $v->description,
                'email' => $v->email,
                'phone' => $v->phone,
                'status' => $v->status,
                'verification_status' => $v->verification_status,
                'business_type' => $v->business_type,
                'business_type_label' => MarketplaceVendor::BUSINESS_TYPES[$v->business_type] ?? $v->business_type,
                'legal_business_name' => $v->legal_business_name,
                'tax_id' => $v->tax_id,
                'trade_license_number' => $v->trade_license_number,
                'identity_document_ref' => $v->identity_document_ref,
                'business_address' => $v->business_address,
                'city' => $v->city,
                'country_code' => $v->country_code,
                'verification_notes' => $v->verification_notes,
                'rejection_reason' => $v->rejection_reason,
                'verified_at' => $v->verified_at?->toIso8601String(),
                'commission_rate' => $v->commission_rate !== null ? (float) $v->commission_rate : null,
                'effective_commission_rate' => $v->commission_rate !== null
                    ? (float) $v->commission_rate
                    : $this->settings->defaultCommissionRate(),
                'payout_method' => $v->payout_method,
                'payout_method_label' => MarketplaceVendor::PAYOUT_METHODS[$v->payout_method] ?? $v->payout_method,
                'payout_destination' => $v->payoutDestinationSummary(),
                'auto_payout_enabled' => $v->auto_payout_enabled,
                'is_featured' => $v->is_featured,
                'rating' => (float) $v->rating,
                'total_sales_amount' => (float) $v->total_sales_amount,
                'total_commission_amount' => (float) $v->total_commission_amount,
                'available_balance' => (float) $v->available_balance,
                'paid_out_total' => (float) $v->paid_out_total,
                'products_count' => $v->vendor_products_count,
                'orders_count' => $v->vendor_orders_count,
                'storefront_url' => route('modrixcommerce.multi-vendor.vendors.show', $v->slug),
            ])
            ->values()
            ->all();

        $vendorProducts = MarketplaceVendorProduct::query()
            ->with(['vendor', 'product' => fn ($q) => $q->withCurrentTranslation()->with('variants.prices')])
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(function (MarketplaceVendorProduct $vp) use ($currency) {
                $product = $vp->product;
                $variant = $product?->variants->first();
                $price = $variant?->priceIn($currency);

                return [
                    'id' => $vp->id,
                    'vendor_id' => $vp->vendor_id,
                    'vendor_name' => $vp->vendor?->store_name ?? 'Unknown Vendor',
                    'vendor_verified' => $vp->vendor?->isVerified() ?? false,
                    'product_id' => $vp->product_id,
                    'product_name' => $product?->localized('name') ?: ($product?->name ?? 'Deleted Product'),
                    'product_slug' => $product?->slug,
                    'product_status' => $product?->status?->value ?? 'draft',
                    'vendor_sku' => $vp->vendor_sku ?: ($variant?->sku ?? ''),
                    'price_formatted' => $price?->format() ?? $currency.' 0.00',
                    'approval_status' => $vp->approval_status,
                    'commission_override' => $vp->commission_override !== null ? (float) $vp->commission_override : null,
                    'rejection_note' => $vp->rejection_note,
                ];
            })
            ->values()
            ->all();

        $vendorOrders = MarketplaceVendorOrder::query()
            ->with('vendor')
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn (MarketplaceVendorOrder $o) => [
                'id' => $o->id,
                'vendor_id' => $o->vendor_id,
                'vendor_name' => $o->vendor?->store_name ?? 'Vendor',
                'order_id' => $o->order_id,
                'order_number' => $o->order_number ?: '#'.$o->order_id,
                'product_name' => $o->product_name,
                'quantity' => $o->quantity,
                'currency' => $o->currency,
                'gross_amount' => (float) $o->gross_amount,
                'commission_rate' => (float) $o->commission_rate,
                'commission_amount' => (float) $o->commission_amount,
                'vendor_earning' => (float) $o->vendor_earning,
                'fulfillment_status' => $o->fulfillment_status,
                'payout_status' => $o->payout_status,
                'tracking_number' => $o->tracking_number,
                'created_at' => $o->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        $payouts = MarketplaceVendorPayout::query()
            ->with('vendor')
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn (MarketplaceVendorPayout $p) => [
                'id' => $p->id,
                'vendor_id' => $p->vendor_id,
                'vendor_name' => $p->vendor?->store_name ?? 'Vendor',
                'reference' => $p->reference,
                'currency' => $p->currency,
                'amount' => (float) $p->amount,
                'fee_amount' => (float) $p->fee_amount,
                'net_amount' => (float) $p->net_amount,
                'payout_method' => $p->payout_method,
                'payout_method_label' => MarketplaceVendor::PAYOUT_METHODS[$p->payout_method] ?? $p->payout_method,
                'destination_summary' => $p->destination_summary,
                'status' => $p->status,
                'requested_by_vendor' => $p->requested_by_vendor,
                'transaction_reference' => $p->transaction_reference,
                'notes' => $p->notes,
                'processed_at' => $p->processed_at?->toIso8601String(),
                'created_at' => $p->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        $catalogProducts = Product::query()
            ->withCurrentTranslation()
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->localized('name') ?: $p->name,
            ])
            ->values()
            ->all();

        return Inertia::render('module/modrixcommerce/multi-vendor/admin/index', [
            'settings' => $this->settings->toArray(),
            'stats' => $this->service->stats(),
            'vendors' => $vendors,
            'vendorProducts' => $vendorProducts,
            'vendorOrders' => $vendorOrders,
            'payouts' => $payouts,
            'catalogProducts' => $catalogProducts,
            'businessTypes' => MarketplaceVendor::BUSINESS_TYPES,
            'payoutMethods' => MarketplaceVendor::PAYOUT_METHODS,
            'currency' => $currency,
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'allow_vendor_registration' => ['required', 'boolean'],
            'auto_approve_vendors' => ['required', 'boolean'],
            'require_business_verification' => ['required', 'boolean'],
            'auto_approve_products' => ['required', 'boolean'],
            'default_commission_rate' => ['required', 'integer', 'min:0', 'max:90'],
            'payout_mode' => ['required', 'string', 'in:vendor_request,automatic_on_order,admin_batch'],
            'min_payout_amount' => ['required', 'integer', 'min:1', 'max:100000'],
            'payout_fee_percent' => ['required', 'integer', 'min:0', 'max:20'],
            'show_vendor_on_cards' => ['required', 'boolean'],
            'show_vendor_box_on_product' => ['required', 'boolean'],
        ]);

        $this->settings->saveSettings($validated);
        $this->service->forgetCache();

        return back()->with('success', 'Multi-Vendor Marketplace settings saved.');
    }

    public function storeVendor(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'account_name' => ['nullable', 'string', 'max:191'],
            'store_name' => ['required', 'string', 'max:191'],
            'email' => ['required', 'email', 'max:191'],
            'password' => ['nullable', 'string', 'min:8'],
            'phone' => ['nullable', 'string', 'max:64'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'business_type' => ['required', 'string', 'in:'.implode(',', array_keys(MarketplaceVendor::BUSINESS_TYPES))],
            'legal_business_name' => ['nullable', 'string', 'max:191'],
            'tax_id' => ['nullable', 'string', 'max:120'],
            'trade_license_number' => ['nullable', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:120'],
            'country_code' => ['nullable', 'string', 'max:8'],
            'commission_rate' => ['nullable', 'numeric', 'min:0', 'max:90'],
            'payout_method' => ['required', 'string', 'in:'.implode(',', array_keys(MarketplaceVendor::PAYOUT_METHODS))],
            'auto_payout_enabled' => ['nullable', 'boolean'],
            'bank_name' => ['nullable', 'string', 'max:191'],
            'bank_account_number' => ['nullable', 'string', 'max:191'],
            'payout_email' => ['nullable', 'string', 'max:191'],
            'approve_immediately' => ['nullable', 'boolean'],
            'verify_immediately' => ['nullable', 'boolean'],
        ]);

        $validated['update_existing_password'] = true;
        $vendor = $this->service->registerVendor($validated);

        if ($request->boolean('approve_immediately', true)) {
            $vendor->status = MarketplaceVendor::STATUS_APPROVED;
            $vendor->approved_at = now();
        }

        if ($request->boolean('verify_immediately', false)) {
            $vendor->verification_status = MarketplaceVendor::VERIFICATION_VERIFIED;
            $vendor->verified_at = now();
            $vendor->verified_by = $request->user()?->id;
        }

        $vendor->save();
        $this->service->forgetCache();

        return back()->with('success', "Vendor \"{$vendor->store_name}\" created.");
    }

    public function updateVendorCredentials(Request $request, MarketplaceVendor $vendor): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:191'],
            'password' => ['required', 'string', 'min:8'],
            'account_name' => ['nullable', 'string', 'max:191'],
        ]);

        $this->service->provisionVendorUserAccount(
            vendor: $vendor,
            email: $validated['email'],
            password: $validated['password'],
            name: $validated['account_name'] ?? $vendor->store_name,
        );

        return back()->with('success', "Seller login account credentials updated for \"{$vendor->store_name}\".");
    }

    public function updateVendorStatus(Request $request, MarketplaceVendor $vendor): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:pending,approved,suspended,rejected'],
            'is_featured' => ['nullable', 'boolean'],
            'commission_rate' => ['nullable', 'numeric', 'min:0', 'max:90'],
        ]);

        $vendor->status = $validated['status'];
        if ($validated['status'] === MarketplaceVendor::STATUS_APPROVED && $vendor->approved_at === null) {
            $vendor->approved_at = now();
        }
        if (array_key_exists('is_featured', $validated) && $validated['is_featured'] !== null) {
            $vendor->is_featured = (bool) $validated['is_featured'];
        }
        if (array_key_exists('commission_rate', $validated)) {
            $vendor->commission_rate = $validated['commission_rate'] !== null && $validated['commission_rate'] !== ''
                ? (float) $validated['commission_rate']
                : null;
        }

        $vendor->save();
        $this->service->forgetCache();

        return back()->with('success', "Vendor \"{$vendor->store_name}\" updated.");
    }

    public function reviewVerification(Request $request, MarketplaceVendor $vendor): RedirectResponse
    {
        $validated = $request->validate([
            'verification_status' => ['required', 'string', 'in:verified,rejected,more_info_required,pending_review'],
            'verification_notes' => ['nullable', 'string', 'max:1000'],
            'rejection_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $this->service->reviewBusinessVerification(
            vendor: $vendor,
            verificationStatus: $validated['verification_status'],
            notes: $validated['verification_notes'] ?? null,
            rejectionReason: $validated['rejection_reason'] ?? null,
            actorId: $request->user()?->id,
        );

        return back()->with('success', "Business verification for \"{$vendor->store_name}\" updated to {$validated['verification_status']}.");
    }

    public function assignProduct(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'vendor_id' => ['nullable', 'integer', 'exists:marketplace_vendors,id'],
            'commission_override' => ['nullable', 'numeric', 'min:0', 'max:90'],
            'approval_status' => ['nullable', 'string', 'in:pending,approved,rejected'],
            'vendor_sku' => ['nullable', 'string', 'max:120'],
        ]);

        $product = Product::query()->findOrFail((int) $validated['product_id']);
        $vendorId = isset($validated['vendor_id']) && $validated['vendor_id'] !== ''
            ? (int) $validated['vendor_id']
            : null;
        $commission = isset($validated['commission_override']) && $validated['commission_override'] !== ''
            ? (float) $validated['commission_override']
            : null;

        $this->service->assignProductToVendor(
            product: $product,
            vendorId: $vendorId,
            commissionOverride: $commission,
            approvalStatus: (string) ($validated['approval_status'] ?? MarketplaceVendorProduct::STATUS_APPROVED),
            vendorSku: $validated['vendor_sku'] ?? null,
        );

        return back()->with('success', $vendorId === null ? 'Product vendor assignment removed.' : 'Product assigned to vendor.');
    }

    public function reviewProduct(Request $request, MarketplaceVendorProduct $vendorProduct): RedirectResponse
    {
        $validated = $request->validate([
            'approval_status' => ['required', 'string', 'in:approved,rejected,pending'],
            'rejection_note' => ['nullable', 'string', 'max:500'],
        ]);

        $this->service->reviewVendorProduct(
            vp: $vendorProduct,
            approvalStatus: $validated['approval_status'],
            rejectionNote: $validated['rejection_note'] ?? null,
        );

        return back()->with('success', "Vendor product status updated to {$validated['approval_status']}.");
    }

    public function processPayout(Request $request, MarketplaceVendorPayout $payout): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:approved,processing,paid,rejected'],
            'transaction_reference' => ['nullable', 'string', 'max:191'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->service->processPayout(
            payout: $payout,
            status: $validated['status'],
            transactionReference: $validated['transaction_reference'] ?? null,
            notes: $validated['notes'] ?? null,
            actorId: $request->user()?->id,
        );

        return back()->with('success', "Payout {$payout->reference} marked as {$validated['status']}.");
    }

    public function batchPayouts(): RedirectResponse
    {
        $count = $this->service->generateBatchPayouts();

        return back()->with('success', "Generated {$count} batch payout(s) for eligible vendors.");
    }
}
