<?php

namespace App\Http\Controllers\Admin;

use App\Core\Audit\AuditLogger;
use App\Core\Settings\StoreSettings;
use App\Domains\Marketplace\LicenseManager;
use App\Domains\Marketplace\MarketplaceCheckout;
use App\Domains\Marketplace\MarketplaceSigner;
use App\Domains\Marketplace\Models\License;
use App\Domains\Marketplace\Models\LicenseEvent;
use App\Domains\Marketplace\Models\MarketplaceOrder;
use App\Domains\Marketplace\Models\MarketplaceProduct;
use App\Domains\Marketplace\Models\MarketplaceRelease;
use App\Domains\Marketplace\Models\Publisher;
use App\Domains\Marketplace\ReleasePublisher;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Like;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Marketplace administration for the platform owner (server role only):
 * products, releases and their review, orders and refunds, licenses.
 */
class MarketplaceAdminController extends Controller
{
    public function products(MarketplaceSigner $signer, MarketplaceCheckout $checkout): Response
    {
        return Inertia::render('admin/marketplace/products', [
            'products' => MarketplaceProduct::query()->withCount(['releases as pending_count' => fn ($q) => $q->where('status', MarketplaceRelease::PENDING_REVIEW)])
                ->orderBy('name')->get()->map(fn (MarketplaceProduct $p) => [
                    'slug' => $p->slug,
                    'name' => $p->name,
                    'type' => $p->type,
                    'status' => $p->status,
                    'price' => $p->money(),
                    'latest_version' => $p->latestPublishedRelease()?->version,
                    'pending_count' => (int) $p->getAttribute('pending_count'),
                ]),
            'setup' => [
                'signing' => $signer->configured(),
                'signing_public_key' => $signer->configured() ? $signer->publicKey() : null,
                'payments' => $checkout->configured(),
            ],
        ]);
    }

    public function create(StoreSettings $store): Response
    {
        return Inertia::render('admin/marketplace/product-form', ['product' => null, 'currency' => $store->currency(), 'releases' => []]);
    }

    public function store(Request $request, StoreSettings $store, AuditLogger $audit): RedirectResponse
    {
        $data = $this->validateProduct($request, null);
        $product = new MarketplaceProduct;
        $product->forceFill([...$data, 'publisher_id' => Publisher::platform()->id, 'currency' => $store->currency()])->save();
        $audit->record('marketplace.product_created', null, ['slug' => $product->slug]);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Product created. Upload a release to publish it.']);

        return redirect()->route('admin.marketplace.products.edit', $product);
    }

    public function edit(MarketplaceProduct $product): Response
    {
        return Inertia::render('admin/marketplace/product-form', [
            'product' => [
                'slug' => $product->slug,
                'name' => $product->name,
                'extension_name' => $product->extension_name,
                'type' => $product->type,
                'summary' => $product->summary,
                'description' => $product->description,
                'documentation_url' => $product->documentation_url,
                'price' => $product->money()->toDisplayDecimal(),
                'status' => $product->status,
                'updates_months' => $product->updates_months,
                'activation_limit' => $product->activation_limit,
            ],
            'currency' => $product->currency,
            'releases' => $product->releases()->with('reviewer:id,email')->orderByDesc('id')->get()->map(fn (MarketplaceRelease $r) => [
                'id' => $r->id,
                'version' => $r->version,
                'platform_version' => $r->platform_version,
                'status' => $r->status,
                'sha256' => $r->sha256,
                'size' => $r->size,
                'changelog' => $r->changelog,
                'review_notes' => $r->review_notes,
                'reviewer' => $r->reviewer?->email,
                'created_at' => $r->created_at?->toIso8601String(),
                'published_at' => $r->published_at?->toIso8601String(),
                'capabilities' => (array) ($r->manifest['capabilities'] ?? []),
                'scan' => $r->scan,
            ]),
        ]);
    }

    public function update(Request $request, MarketplaceProduct $product, AuditLogger $audit): RedirectResponse
    {
        $data = $this->validateProduct($request, $product);

        if ($data['status'] === MarketplaceProduct::PUBLISHED && $product->latestPublishedRelease() === null) {
            return back()->withErrors(['status' => 'Publish a release before publishing the product.']);
        }

        $product->forceFill($data)->save();
        $audit->record('marketplace.product_updated', null, ['slug' => $product->slug, 'status' => $product->status]);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Product saved.']);

        return redirect()->route('admin.marketplace.products.edit', $product);
    }

    public function upload(Request $request, MarketplaceProduct $product, ReleasePublisher $publisher): RedirectResponse
    {
        $data = $request->validate([
            'package' => ['required', 'file', 'mimes:zip', 'max:'.((int) config('ecomodular.marketplace.server.max_package_mb', 50) * 1024)],
            'changelog' => ['nullable', 'string', 'max:5000'],
        ]);
        $file = $request->file('package');
        abort_unless($file !== null && ! is_array($file), 422);

        $release = $publisher->upload($product, $file, $data['changelog'] ?? null, $this->actor($request));
        Inertia::flash('toast', ['type' => 'success', 'message' => "Version {$release->version} passed validation and is waiting for review."]);

        return back();
    }

    public function review(Request $request, MarketplaceRelease $release, ReleasePublisher $publisher): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'notes' => ['nullable', 'string', 'max:1000'],
            'reviewed' => [Rule::requiredIf($request->input('decision') === 'approve'), 'accepted_if:decision,approve'],
        ], ['reviewed.accepted_if' => 'Confirm that you reviewed this release.', 'reviewed.required' => 'Confirm that you reviewed this release.']);

        $publisher->review($release, $data['decision'] === 'approve', $data['notes'] ?? null, $this->actor($request));
        Inertia::flash('toast', ['type' => 'success', 'message' => $data['decision'] === 'approve' ? "Version {$release->version} is published and signed." : "Version {$release->version} was rejected."]);

        return back();
    }

    public function withdraw(Request $request, MarketplaceRelease $release, ReleasePublisher $publisher): RedirectResponse
    {
        abort_unless($release->status === MarketplaceRelease::PUBLISHED, 422);
        $publisher->withdraw($release, $this->actor($request));
        Inertia::flash('toast', ['type' => 'success', 'message' => "Version {$release->version} is no longer offered."]);

        return back();
    }

    public function orders(Request $request): Response
    {
        $search = (string) $request->string('search');

        return Inertia::render('admin/marketplace/orders', [
            'orders' => MarketplaceOrder::query()->with('items')
                ->when($search !== '', fn ($q) => $q->where(fn ($w) => Like::orWhere(Like::where($w, 'number', $search), 'email', $search)))
                ->latest('id')->paginate(25)->withQueryString()
                ->through(fn (MarketplaceOrder $o) => [
                    'number' => $o->number,
                    'email' => $o->email,
                    'status' => $o->status,
                    'total' => $o->money(),
                    'items' => $o->items->pluck('product_name'),
                    'created_at' => $o->created_at?->toIso8601String(),
                ]),
            'search' => $search,
        ]);
    }

    public function refund(Request $request, MarketplaceOrder $order, MarketplaceCheckout $checkout): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $checkout->refund($order, $data['reason'], $this->actor($request));
        Inertia::flash('toast', ['type' => 'success', 'message' => "Order {$order->number} refunded; its licenses can no longer download or activate."]);

        return back();
    }

    public function licenses(Request $request): Response
    {
        $filters = $request->validate(['status' => ['nullable', Rule::in(License::STATUSES)], 'search' => ['nullable', 'string', 'max:100']]);

        return Inertia::render('admin/marketplace/licenses', [
            'licenses' => License::query()->with(['product', 'user:id,email', 'events' => fn ($q) => $q->with('actor:id,email')->latest('id')->limit(20)])->withCount(['activations as active_activations' => fn ($q) => $q->whereNull('deactivated_at')])
                ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
                ->when($filters['search'] ?? null, fn ($q, $s) => $q->whereHas('user', fn ($u) => Like::where($u, 'email', $s)))
                ->latest('id')->paginate(25)->withQueryString()
                ->through(fn (License $l) => [
                    'id' => $l->id,
                    'product' => $l->product->name,
                    'owner' => $l->user->email,
                    'key_hint' => $l->key_hint,
                    'status' => $l->status,
                    'status_reason' => $l->status_reason,
                    'updates_until' => $l->updates_until?->toDateString(),
                    'activations' => (int) $l->getAttribute('active_activations'),
                    'activation_limit' => $l->activation_limit,
                    'events' => $l->events->map(fn (LicenseEvent $e) => [
                        'id' => $e->id,
                        'action' => $e->action,
                        'actor' => $e->actor?->email,
                        'instance_id' => $e->instance_id,
                        'details' => $e->details,
                        'created_at' => $e->created_at->toIso8601String(),
                    ]),
                ]),
            'filters' => ['status' => $filters['status'] ?? '', 'search' => $filters['search'] ?? ''],
            'statuses' => License::STATUSES,
        ]);
    }

    public function licenseStatus(Request $request, License $license, LicenseManager $licenses): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['suspend', 'revoke', 'reinstate', 'extend'])],
            'reason' => ['required_unless:action,extend', 'nullable', 'string', 'max:500'],
            'months' => ['required_if:action,extend', 'nullable', 'integer', 'min:1', 'max:60'],
        ]);
        $actor = $this->actor($request);

        match ($data['action']) {
            'suspend' => $licenses->changeStatus($license, License::SUSPENDED, $data['reason'], $actor),
            'revoke' => $licenses->changeStatus($license, License::REVOKED, $data['reason'], $actor),
            'reinstate' => $licenses->changeStatus($license, License::ACTIVE, $data['reason'], $actor),
            'extend' => $licenses->extendUpdates($license, (int) $data['months'], $actor),
            default => abort(422),
        };

        Inertia::flash('toast', ['type' => 'success', 'message' => 'License updated.']);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validateProduct(Request $request, ?MarketplaceProduct $product): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'slug' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('marketplace_products', 'slug')->ignore($product?->id)],
            'extension_name' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9-]+\/[a-z0-9-]+$/'],
            'type' => ['required', Rule::in(MarketplaceProduct::TYPES)],
            'summary' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:20000'],
            'documentation_url' => ['nullable', 'url:https', 'max:500'],
            'price' => ['required', 'regex:/^\d{1,9}(\.\d{1,2})?$/'],
            'status' => ['required', Rule::in([MarketplaceProduct::DRAFT, MarketplaceProduct::PUBLISHED, MarketplaceProduct::RETIRED])],
            'updates_months' => ['required', 'integer', 'min:0', 'max:120'],
            'activation_limit' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ]);

        if ($product !== null && $product->releases()->exists() && ($data['extension_name'] !== $product->extension_name || $data['type'] !== $product->type)) {
            abort(422, 'The package name and type cannot change once releases exist.');
        }

        $exists = MarketplaceProduct::withTrashed()->where('type', $data['type'])->where('extension_name', $data['extension_name'])
            ->when($product, fn ($q) => $q->whereKeyNot($product->id))->exists();
        if ($exists) {
            abort(422, 'Another product already sells this package.');
        }

        return $data;
    }

    private function actor(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
