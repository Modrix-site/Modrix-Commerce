<?php

namespace EcoModular\MultiVendor\Http\Controllers;

use App\Core\Settings\StoreSettings;
use App\Core\Themes\Sections\SectionPresenter;
use App\Core\Themes\Sections\ThemeTemplates;
use App\Core\Themes\ThemeResolver;
use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\StorefrontCatalog;
use App\Http\Controllers\Controller;
use App\Models\User;
use EcoModular\MultiVendor\Models\MarketplaceVendor;
use EcoModular\MultiVendor\Models\MarketplaceVendorOrder;
use EcoModular\MultiVendor\Models\MarketplaceVendorPayout;
use EcoModular\MultiVendor\Models\MarketplaceVendorProduct;
use EcoModular\MultiVendor\Services\MultiVendorService;
use EcoModular\MultiVendor\Support\MultiVendorSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class MultiVendorStorefrontController extends Controller
{
    public const SESSION_VENDOR_KEY = 'marketplace.active_vendor_id';

    public function __construct(
        private readonly MultiVendorSettings $settings,
        private readonly MultiVendorService $service,
    ) {}

    /**
     * Amazon-style All Products & Multi-Vendor Marketplace Catalog rendered inside the active theme.
     */
    public function catalog(
        Request $request,
        StorefrontCatalog $catalog,
        StoreSettings $store,
        ThemeResolver $themes,
        SectionPresenter $sections,
    ): Response {
        abort_unless($this->settings->enabled(), 404);

        $currency = $store->currency();
        $listing = $this->service->marketplaceCatalogListing(
            $request->all(),
            $catalog,
            $currency,
            null,
            20,
        );

        $activeCategory = $listing['activeCategory'] ?? null;
        $title = $activeCategory
            ? "{$activeCategory['name']} — Marketplace Catalog"
            : 'Marketplace All Products Catalog';

        return Inertia::render('module/modrixcommerce/multi-vendor/storefront/catalog', [
            ...$listing,
            'title' => $title,
            'description' => 'Browse verified marketplace sellers, today\'s deals, best sellers, and full store inventory powered by your active theme.',
            'meta' => [
                'title' => $title,
                'description' => 'Shop all products across verified marketplace sellers with Amazon-style filters, deals, and seller ratings.',
            ],
            'breadcrumbs' => [
                ['name' => 'Marketplace Catalog', 'slug' => ''],
            ],
            'themeCollectionSections' => $this->themeCollectionSections($request, $themes, $sections),
            'catalogUrl' => route('modrixcommerce.multi-vendor.catalog'),
            'vendorsDirectoryUrl' => route('modrixcommerce.multi-vendor.vendors.index'),
            'allowRegistration' => $this->settings->allowVendorRegistration(),
            'registerUrl' => route('modrixcommerce.multi-vendor.register'),
            'loginUrl' => route('modrixcommerce.multi-vendor.login'),
            'dashboardUrl' => route('modrixcommerce.multi-vendor.dashboard'),
        ]);
    }

    /**
     * Public Marketplace Vendors Directory (`/m/modrixcommerce/multi-vendor/vendors`).
     */
    public function vendors(Request $request, StorefrontCatalog $catalog, StoreSettings $store): Response
    {
        abort_unless($this->settings->enabled(), 404);

        $search = trim($request->string('q')->toString());
        $verifiedOnly = $request->boolean('verified', false);
        $currency = $store->currency();

        $query = MarketplaceVendor::query()
            ->where('status', MarketplaceVendor::STATUS_APPROVED)
            ->withCount([
                'vendorProducts as approved_products_count' => fn ($q) => $q->where('approval_status', MarketplaceVendorProduct::STATUS_APPROVED),
            ])
            ->orderByDesc('is_featured')
            ->orderByDesc('id');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('store_name', 'like', "%{$search}%")
                    ->orWhere('tagline', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%");
            });
        }

        if ($verifiedOnly) {
            $query->where('verification_status', MarketplaceVendor::VERIFICATION_VERIFIED);
        }

        $vendorModels = $query->get();
        $vendorIds = $vendorModels->pluck('id')->all();

        $previewIdsByVendor = [];
        if ($vendorIds !== []) {
            $vpRows = MarketplaceVendorProduct::query()
                ->whereIn('vendor_id', $vendorIds)
                ->where('approval_status', MarketplaceVendorProduct::STATUS_APPROVED)
                ->orderByDesc('id')
                ->get(['vendor_id', 'product_id']);

            foreach ($vpRows as $row) {
                if (count($previewIdsByVendor[$row->vendor_id] ?? []) < 4) {
                    $previewIdsByVendor[$row->vendor_id][] = (int) $row->product_id;
                }
            }
        }

        $allPreviewIds = array_values(array_unique(array_merge(...array_values($previewIdsByVendor ?: [[]]))));
        $previewCards = $allPreviewIds !== []
            ? collect($catalog->cardsFor($allPreviewIds, $currency, count($allPreviewIds)))->keyBy('id')
            : collect();

        $vendors = $vendorModels->map(function (MarketplaceVendor $v) use ($previewIdsByVendor, $previewCards) {
            $previews = [];
            foreach ($previewIdsByVendor[$v->id] ?? [] as $pid) {
                if ($previewCards->has($pid)) {
                    $previews[] = $previewCards->get($pid);
                }
            }

            return [
                'id' => $v->id,
                'store_name' => $v->store_name,
                'slug' => $v->slug,
                'tagline' => $v->tagline,
                'description' => $v->description,
                'logo_url' => $v->logo_url,
                'banner_url' => $v->banner_url,
                'city' => $v->city,
                'country_code' => $v->country_code,
                'is_verified' => $v->isVerified(),
                'is_featured' => $v->is_featured,
                'rating' => (float) $v->rating,
                'products_count' => $v->approved_products_count,
                'url' => route('modrixcommerce.multi-vendor.vendors.show', $v->slug),
                'preview_products' => $previews,
            ];
        })->values()->all();

        return Inertia::render('module/modrixcommerce/multi-vendor/storefront/vendors', [
            'vendors' => $vendors,
            'filters' => [
                'q' => $search,
                'verified' => $verifiedOnly,
            ],
            'catalogUrl' => route('modrixcommerce.multi-vendor.catalog'),
            'allowRegistration' => $this->settings->allowVendorRegistration(),
            'registerUrl' => route('modrixcommerce.multi-vendor.register'),
            'loginUrl' => route('modrixcommerce.multi-vendor.login'),
            'dashboardUrl' => route('modrixcommerce.multi-vendor.dashboard'),
        ]);
    }

    /**
     * Dedicated Vendor Storefront & Theme-Native Product Catalog (`/m/modrixcommerce/multi-vendor/vendors/{slug}`).
     */
    public function vendorStore(
        string $slug,
        Request $request,
        StorefrontCatalog $catalog,
        StoreSettings $store,
        ThemeResolver $themes,
        SectionPresenter $sections,
    ): Response {
        abort_unless($this->settings->enabled(), 404);

        $vendor = MarketplaceVendor::query()
            ->where('slug', $slug)
            ->where('status', MarketplaceVendor::STATUS_APPROVED)
            ->firstOrFail();

        $currency = $store->currency();
        $listing = $this->service->marketplaceCatalogListing(
            $request->all(),
            $catalog,
            $currency,
            $vendor->id,
            20,
        );

        return Inertia::render('module/modrixcommerce/multi-vendor/storefront/vendor-store', [
            ...$listing,
            'vendor' => [
                'id' => $vendor->id,
                'store_name' => $vendor->store_name,
                'slug' => $vendor->slug,
                'tagline' => $vendor->tagline,
                'description' => $vendor->description,
                'logo_url' => $vendor->logo_url,
                'banner_url' => $vendor->banner_url,
                'city' => $vendor->city,
                'country_code' => $vendor->country_code,
                'is_verified' => $vendor->isVerified(),
                'business_type_label' => MarketplaceVendor::BUSINESS_TYPES[$vendor->business_type] ?? $vendor->business_type,
                'legal_business_name' => $vendor->legal_business_name,
                'rating' => (float) $vendor->rating,
                'joined_at' => $vendor->created_at?->format('M Y'),
            ],
            'title' => "{$vendor->store_name} Storefront",
            'description' => $vendor->tagline ?: $vendor->description,
            'meta' => [
                'title' => "{$vendor->store_name} — Marketplace Storefront",
                'description' => $vendor->tagline ?: $vendor->description,
            ],
            'breadcrumbs' => [
                ['name' => $vendor->store_name, 'slug' => ''],
            ],
            'themeCollectionSections' => $this->themeCollectionSections($request, $themes, $sections),
            'vendorsDirectoryUrl' => route('modrixcommerce.multi-vendor.vendors.index'),
            'catalogUrl' => route('modrixcommerce.multi-vendor.catalog'),
        ]);
    }

    /**
     * Resolves the active theme's `collection` sections so module catalogs render natively in every sections-v2 theme.
     *
     * @return list<array<string, mixed>>
     */
    private function themeCollectionSections(Request $request, ThemeResolver $themes, SectionPresenter $sections): array
    {
        $resolvedTheme = $themes->forRequest($request);
        if (! ThemeTemplates::supports($resolvedTheme->manifest)) {
            return [];
        }

        return $sections->present($resolvedTheme, 'template:collection');
    }

    /**
     * Vendor Registration & Business Verification Onboarding Form (`/m/modrixcommerce/multi-vendor/register`).
     */
    public function showRegistrationForm(Request $request): Response
    {
        abort_unless($this->settings->enabled() && $this->settings->allowVendorRegistration(), 404);

        $existing = $this->resolveCurrentVendor($request);
        $user = $request->user();

        return Inertia::render('module/modrixcommerce/multi-vendor/storefront/register', [
            'businessTypes' => MarketplaceVendor::BUSINESS_TYPES,
            'payoutMethods' => MarketplaceVendor::PAYOUT_METHODS,
            'defaultCommissionRate' => $this->settings->defaultCommissionRate(),
            'requireBusinessVerification' => $this->settings->requireBusinessVerification(),
            'isAuthenticated' => $user !== null,
            'authenticatedUser' => $user ? [
                'name' => $user->name,
                'email' => $user->email,
            ] : null,
            'existingVendor' => $existing ? [
                'id' => $existing->id,
                'store_name' => $existing->store_name,
                'slug' => $existing->slug,
                'status' => $existing->status,
                'verification_status' => $existing->verification_status,
            ] : null,
            'loginUrl' => route('modrixcommerce.multi-vendor.login'),
            'dashboardUrl' => route('modrixcommerce.multi-vendor.dashboard'),
            'vendorsDirectoryUrl' => route('modrixcommerce.multi-vendor.vendors.index'),
        ]);
    }

    /**
     * Submits a new Vendor Registration, creates the Seller User Account (email + password),
     * and signs the seller in so they can access their Seller Portal immediately or anytime later.
     */
    public function register(Request $request): RedirectResponse
    {
        abort_unless($this->settings->enabled() && $this->settings->allowVendorRegistration(), 404);

        $currentUser = $request->user();
        $isGuest = $currentUser === null;

        $emailRule = ['required', 'email', 'max:191'];
        if ($isGuest) {
            $emailRule[] = Rule::unique('users', 'email');
        }

        $validated = $request->validate([
            'account_name' => ['nullable', 'string', 'max:191'],
            'store_name' => ['required', 'string', 'max:191'],
            'email' => $emailRule,
            'password' => $isGuest
                ? ['required', 'string', 'min:8', 'confirmed']
                : ['nullable', 'string', 'min:8', 'confirmed'],
            'phone' => ['nullable', 'string', 'max:64'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'business_type' => ['required', 'string', 'in:'.implode(',', array_keys(MarketplaceVendor::BUSINESS_TYPES))],
            'legal_business_name' => ['required', 'string', 'max:191'],
            'tax_id' => ['nullable', 'string', 'max:120'],
            'trade_license_number' => ['required', 'string', 'max:120'],
            'identity_document_ref' => ['nullable', 'string', 'max:191'],
            'business_address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'country_code' => ['nullable', 'string', 'max:8'],
            'payout_method' => ['required', 'string', 'in:'.implode(',', array_keys(MarketplaceVendor::PAYOUT_METHODS))],
            'auto_payout_enabled' => ['nullable', 'boolean'],
            'bank_name' => ['nullable', 'string', 'max:191'],
            'bank_account_name' => ['nullable', 'string', 'max:191'],
            'bank_account_number' => ['nullable', 'string', 'max:191'],
            'bank_swift_code' => ['nullable', 'string', 'max:64'],
            'payout_email' => ['nullable', 'string', 'max:191'],
        ]);

        if ($currentUser instanceof User && filled($validated['password'] ?? null)) {
            $currentUser->password = (string) $validated['password'];
            $currentUser->save();
        }

        $vendor = $this->service->registerVendor($validated, $currentUser);

        if ($isGuest && $vendor->user instanceof User) {
            Auth::login($vendor->user);
        }

        $request->session()->put(self::SESSION_VENDOR_KEY, $vendor->id);

        return redirect()
            ->route('modrixcommerce.multi-vendor.dashboard')
            ->with('success', 'Seller account created and business verification submitted!');
    }

    /**
     * Dedicated Marketplace Seller Login Page (`/m/modrixcommerce/multi-vendor/login`).
     */
    public function showLoginForm(Request $request): Response|RedirectResponse
    {
        abort_unless($this->settings->enabled(), 404);

        $existing = $this->resolveCurrentVendor($request);

        return Inertia::render('module/modrixcommerce/multi-vendor/storefront/login', [
            'allowRegistration' => $this->settings->allowVendorRegistration(),
            'registerUrl' => route('modrixcommerce.multi-vendor.register'),
            'dashboardUrl' => route('modrixcommerce.multi-vendor.dashboard'),
            'vendorsDirectoryUrl' => route('modrixcommerce.multi-vendor.vendors.index'),
            'existingVendor' => $existing ? [
                'id' => $existing->id,
                'store_name' => $existing->store_name,
                'status' => $existing->status,
            ] : null,
        ]);
    }

    /**
     * Authenticates a Marketplace Seller with email & password and redirects to Seller Portal.
     */
    public function login(Request $request): RedirectResponse
    {
        abort_unless($this->settings->enabled(), 404);

        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ]);

        $email = Str::lower(trim((string) $validated['email']));

        if (! Auth::attempt(['email' => $email, 'password' => $validated['password']], $request->boolean('remember'))) {
            return back()
                ->withErrors(['email' => 'These seller credentials do not match our records.'])
                ->onlyInput('email');
        }

        $request->session()->regenerate();

        /** @var User $user */
        $user = Auth::user();

        $vendor = MarketplaceVendor::query()
            ->where('user_id', $user->id)
            ->orWhere('email', $email)
            ->first();

        if (! $vendor instanceof MarketplaceVendor) {
            return redirect()
                ->route('modrixcommerce.multi-vendor.register')
                ->with('error', 'Signed in, but no vendor store is linked to this email yet. Complete your seller registration below.');
        }

        if ($vendor->user_id === null) {
            $vendor->user_id = $user->id;
            $vendor->save();
        }

        $request->session()->put(self::SESSION_VENDOR_KEY, $vendor->id);

        return redirect()
            ->route('modrixcommerce.multi-vendor.dashboard')
            ->with('success', "Welcome back to {$vendor->store_name} Seller Portal!");
    }

    /**
     * Logs the seller out of the Seller Portal.
     */
    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget(self::SESSION_VENDOR_KEY);
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('modrixcommerce.multi-vendor.login')
            ->with('success', 'You have been signed out of the Seller Portal.');
    }

    /**
     * Vendor Seller Portal (`/m/modrixcommerce/multi-vendor/dashboard`).
     * Allows the vendor to manage business verification, product catalog, orders, and payouts.
     */
    public function dashboard(Request $request, StoreSettings $store): Response|RedirectResponse
    {
        abort_unless($this->settings->enabled(), 404);

        $vendor = $this->resolveCurrentVendor($request);
        if (! $vendor instanceof MarketplaceVendor) {
            return redirect()->route('modrixcommerce.multi-vendor.login');
        }

        $currency = $store->currency();

        $products = MarketplaceVendorProduct::query()
            ->where('vendor_id', $vendor->id)
            ->with(['product' => fn ($q) => $q->withCurrentTranslation()->with('variants.prices')])
            ->orderByDesc('id')
            ->get()
            ->map(function (MarketplaceVendorProduct $vp) use ($currency) {
                $product = $vp->product;
                $variant = $product?->variants->first();
                $price = $variant?->priceIn($currency);

                return [
                    'id' => $vp->id,
                    'product_id' => $vp->product_id,
                    'name' => $product?->localized('name') ?: ($product?->name ?? 'Product'),
                    'slug' => $product?->slug,
                    'sku' => $vp->vendor_sku ?: ($variant?->sku ?? ''),
                    'price_formatted' => $price?->format() ?? $currency.' 0.00',
                    'stock' => $variant ? (int) $variant->onHand() : 0,
                    'approval_status' => $vp->approval_status,
                    'rejection_note' => $vp->rejection_note,
                ];
            })
            ->values()
            ->all();

        $orders = MarketplaceVendorOrder::query()
            ->where('vendor_id', $vendor->id)
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (MarketplaceVendorOrder $o) => [
                'id' => $o->id,
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
                'created_at' => $o->created_at?->format('Y-m-d H:i'),
            ])
            ->values()
            ->all();

        $payouts = MarketplaceVendorPayout::query()
            ->where('vendor_id', $vendor->id)
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (MarketplaceVendorPayout $p) => [
                'id' => $p->id,
                'reference' => $p->reference,
                'currency' => $p->currency,
                'amount' => (float) $p->amount,
                'fee_amount' => (float) $p->fee_amount,
                'net_amount' => (float) $p->net_amount,
                'payout_method_label' => MarketplaceVendor::PAYOUT_METHODS[$p->payout_method] ?? $p->payout_method,
                'destination_summary' => $p->destination_summary,
                'status' => $p->status,
                'transaction_reference' => $p->transaction_reference,
                'created_at' => $p->created_at?->format('Y-m-d H:i'),
            ])
            ->values()
            ->all();

        $categories = Category::query()
            ->withCurrentTranslation()
            ->orderBy('position')
            ->get()
            ->map(fn (Category $c) => [
                'id' => $c->id,
                'name' => $c->localized('name') ?: $c->name,
            ])
            ->values()
            ->all();

        return Inertia::render('module/modrixcommerce/multi-vendor/storefront/dashboard', [
            'vendor' => [
                'id' => $vendor->id,
                'store_name' => $vendor->store_name,
                'slug' => $vendor->slug,
                'tagline' => $vendor->tagline,
                'email' => $vendor->email,
                'phone' => $vendor->phone,
                'status' => $vendor->status,
                'verification_status' => $vendor->verification_status,
                'business_type' => $vendor->business_type,
                'legal_business_name' => $vendor->legal_business_name,
                'tax_id' => $vendor->tax_id,
                'trade_license_number' => $vendor->trade_license_number,
                'identity_document_ref' => $vendor->identity_document_ref,
                'business_address' => $vendor->business_address,
                'city' => $vendor->city,
                'country_code' => $vendor->country_code,
                'rejection_reason' => $vendor->rejection_reason,
                'effective_commission_rate' => $vendor->commission_rate !== null
                    ? (float) $vendor->commission_rate
                    : $this->settings->defaultCommissionRate(),
                'payout_method' => $vendor->payout_method,
                'auto_payout_enabled' => $vendor->auto_payout_enabled,
                'bank_name' => $vendor->bank_name,
                'bank_account_name' => $vendor->bank_account_name,
                'bank_account_number' => $vendor->bank_account_number,
                'bank_swift_code' => $vendor->bank_swift_code,
                'payout_email' => $vendor->payout_email,
                'total_sales_amount' => (float) $vendor->total_sales_amount,
                'total_commission_amount' => (float) $vendor->total_commission_amount,
                'available_balance' => (float) $vendor->available_balance,
                'paid_out_total' => (float) $vendor->paid_out_total,
                'storefront_url' => route('modrixcommerce.multi-vendor.vendors.show', $vendor->slug),
            ],
            'products' => $products,
            'orders' => $orders,
            'payouts' => $payouts,
            'categories' => $categories,
            'businessTypes' => MarketplaceVendor::BUSINESS_TYPES,
            'payoutMethods' => MarketplaceVendor::PAYOUT_METHODS,
            'minPayoutAmount' => $this->settings->minPayoutAmount(),
            'payoutMode' => $this->settings->payoutMode(),
            'currency' => $currency,
            'logoutUrl' => route('modrixcommerce.multi-vendor.logout'),
        ]);
    }

    public function submitVerification(Request $request): RedirectResponse
    {
        $vendor = $this->resolveCurrentVendor($request);
        abort_unless($vendor instanceof MarketplaceVendor, 403);

        $validated = $request->validate([
            'business_type' => ['required', 'string', 'in:'.implode(',', array_keys(MarketplaceVendor::BUSINESS_TYPES))],
            'legal_business_name' => ['required', 'string', 'max:191'],
            'tax_id' => ['nullable', 'string', 'max:120'],
            'trade_license_number' => ['required', 'string', 'max:120'],
            'identity_document_ref' => ['nullable', 'string', 'max:191'],
            'business_address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'country_code' => ['nullable', 'string', 'max:8'],
        ]);

        $this->service->submitBusinessVerification($vendor, $validated);

        return back()->with('success', 'Business verification credentials submitted for review.');
    }

    public function updatePayoutSettings(Request $request): RedirectResponse
    {
        $vendor = $this->resolveCurrentVendor($request);
        abort_unless($vendor instanceof MarketplaceVendor, 403);

        $validated = $request->validate([
            'payout_method' => ['required', 'string', 'in:'.implode(',', array_keys(MarketplaceVendor::PAYOUT_METHODS))],
            'auto_payout_enabled' => ['required', 'boolean'],
            'bank_name' => ['nullable', 'string', 'max:191'],
            'bank_account_name' => ['nullable', 'string', 'max:191'],
            'bank_account_number' => ['nullable', 'string', 'max:191'],
            'bank_swift_code' => ['nullable', 'string', 'max:64'],
            'payout_email' => ['nullable', 'string', 'max:191'],
        ]);

        $vendor->fill($validated)->save();

        return back()->with('success', 'Vendor payout preferences updated.');
    }

    public function storeProduct(Request $request): RedirectResponse
    {
        $vendor = $this->resolveCurrentVendor($request);
        abort_unless($vendor instanceof MarketplaceVendor, 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'sku' => ['required', 'string', 'max:100'],
            'price' => ['required', 'numeric', 'min:0.01'],
            'stock' => ['nullable', 'integer', 'min:0'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
        ]);

        $this->service->createVendorProduct($vendor, $validated, $request->user());

        return back()->with('success', 'Product added to your vendor catalog.');
    }

    public function updateOrderFulfillment(Request $request, MarketplaceVendorOrder $vendorOrder): RedirectResponse
    {
        $vendor = $this->resolveCurrentVendor($request);
        abort_unless($vendor instanceof MarketplaceVendor && $vendorOrder->vendor_id === $vendor->id, 403);

        $validated = $request->validate([
            'fulfillment_status' => ['required', 'string', 'in:unfulfilled,processing,shipped,delivered'],
            'tracking_number' => ['nullable', 'string', 'max:120'],
        ]);

        $vendorOrder->fill($validated)->save();

        return back()->with('success', 'Order item fulfillment updated.');
    }

    public function requestPayout(Request $request): RedirectResponse
    {
        $vendor = $this->resolveCurrentVendor($request);
        abort_unless($vendor instanceof MarketplaceVendor, 403);

        $validated = $request->validate([
            'amount' => ['nullable', 'numeric', 'min:1'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $payout = $this->service->createPayout(
                vendor: $vendor,
                requestedAmount: isset($validated['amount']) ? (float) $validated['amount'] : null,
                requestedByVendor: true,
                notes: $validated['notes'] ?? null,
            );

            return back()->with('success', "Payout request {$payout->reference} submitted.");
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    private function resolveCurrentVendor(Request $request): ?MarketplaceVendor
    {
        $user = $request->user();
        if ($user !== null) {
            $byUser = MarketplaceVendor::query()->where('user_id', $user->id)->first();
            if ($byUser instanceof MarketplaceVendor) {
                return $byUser;
            }

            $byEmail = MarketplaceVendor::query()
                ->where('email', Str::lower($user->email))
                ->first();
            if ($byEmail instanceof MarketplaceVendor) {
                if ($byEmail->user_id === null) {
                    $byEmail->user_id = $user->id;
                    $byEmail->save();
                }

                return $byEmail;
            }
        }

        $sessionVendorId = $request->session()->get(self::SESSION_VENDOR_KEY);
        if (is_numeric($sessionVendorId)) {
            return MarketplaceVendor::query()->find((int) $sessionVendorId);
        }

        if ($request->filled('vendor_id') && $user !== null && $user->can('multi_vendor.manage')) {
            return MarketplaceVendor::query()->find((int) $request->input('vendor_id'));
        }

        return null;
    }
}
