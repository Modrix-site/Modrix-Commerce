<?php

namespace App\Http\Controllers\Marketplace;

use App\Core\Audit\AuditLogger;
use App\Core\Settings\Countries;
use App\Core\Settings\StoreSettings;
use App\Domains\Marketplace\Models\LedgerEntry;
use App\Domains\Marketplace\Models\MarketplaceProduct;
use App\Domains\Marketplace\Models\MarketplaceRelease;
use App\Domains\Marketplace\Models\Payout;
use App\Domains\Marketplace\Models\Publisher;
use App\Domains\Marketplace\Models\PublisherMember;
use App\Domains\Marketplace\Models\SellerApplication;
use App\Domains\Marketplace\ReleasePublisher;
use App\Domains\Marketplace\SellerAccounts;
use App\Domains\Marketplace\SellerLedger;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The seller portal (marketplace server): apply to sell, then manage own
 * products and releases and see sales, commission and payouts. Every query
 * is scoped to the seller's publisher; other sellers' records are 404.
 * Products are listed and releases offered only after the owner's review.
 */
class VendorController extends Controller
{
    public function index(Request $request, SellerLedger $ledger): Response
    {
        $user = $this->user($request);
        $publisher = PublisherMember::publisherFor($user);

        if ($publisher === null) {
            $application = SellerApplication::query()->where('user_id', $user->id)->latest('id')->first();

            return Inertia::render('storefront/vendor/apply', [
                'application' => $application === null ? null : [
                    'status' => $application->status,
                    'publisher_name' => $application->publisher_name,
                    'review_notes' => $application->review_notes,
                    'created_at' => $application->created_at?->toDateString(),
                ],
                'countries' => Countries::options(),
            ]);
        }

        return Inertia::render('storefront/vendor/dashboard', [
            'publisher' => [
                'name' => $publisher->name,
                'slug' => $publisher->slug,
                'status' => $publisher->status,
                'commission_percent' => $publisher->commission_percent,
            ],
            'totals' => collect($ledger->totals($publisher))->map(fn (array $t, string $currency) => ['currency' => $currency, ...$t])->values(),
            'products' => $publisher->products()->withCount(['releases as pending_count' => fn ($q) => $q->where('status', MarketplaceRelease::PENDING_REVIEW)])
                ->orderBy('name')->get()->map(fn (MarketplaceProduct $p) => [
                    'slug' => $p->slug,
                    'name' => $p->name,
                    'status' => $p->status,
                    'price' => $p->money(),
                    'latest_version' => $p->latestPublishedRelease()?->version,
                    'pending_count' => (int) $p->getAttribute('pending_count'),
                ]),
            'ledger' => LedgerEntry::query()->where('publisher_id', $publisher->id)->latest('id')->limit(50)->get()->map(fn (LedgerEntry $e) => [
                'id' => $e->id,
                'type' => $e->type,
                'description' => $e->description,
                'currency' => $e->currency,
                'gross' => $e->gross,
                'commission' => $e->commission,
                'net' => $e->net,
                'created_at' => $e->created_at->toDateString(),
            ]),
            'payouts' => Payout::query()->where('publisher_id', $publisher->id)->latest('paid_at')->limit(20)->get()->map(fn (Payout $p) => [
                'id' => $p->id,
                'amount' => $p->money(),
                'reference' => $p->reference,
                'paid_at' => $p->paid_at->toDateString(),
            ]),
        ]);
    }

    public function apply(Request $request, SellerAccounts $sellers): RedirectResponse
    {
        $data = $request->validate([
            'publisher_name' => ['required', 'string', 'max:120'],
            'vendor_slug' => ['required', 'string', 'min:3', 'max:40', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'legal_name' => ['required', 'string', 'max:160'],
            'country' => ['required', Rule::in(Countries::codes())],
            'website' => ['nullable', 'url:https', 'max:255'],
            'payout_email' => ['required', 'email', 'max:191'],
            'support_email' => ['required', 'email', 'max:191'],
            'description' => ['required', 'string', 'min:30', 'max:3000'],
            'terms' => ['accepted'],
        ], ['terms.accepted' => 'Accept the seller terms to apply.']);
        unset($data['terms']);

        /** @var array{publisher_name: string, vendor_slug: string, legal_name: string, country: string, website?: string|null, payout_email: string, support_email: string, description: string} $data */
        $sellers->apply($this->user($request), $data);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Application sent. We review every seller before they can publish.']);

        return redirect()->route('vendor.index');
    }

    public function create(Request $request): Response
    {
        $publisher = $this->publisher($request, write: true);

        return Inertia::render('storefront/vendor/product', ['product' => null, 'releases' => [], 'vendor' => $publisher->slug]);
    }

    public function store(Request $request, AuditLogger $audit, StoreSettings $store): RedirectResponse
    {
        $publisher = $this->publisher($request, write: true);
        $data = $this->validateProduct($request, $publisher, null);

        $product = new MarketplaceProduct;
        $product->forceFill([...$data, 'publisher_id' => $publisher->id, 'currency' => $store->currency(), 'status' => MarketplaceProduct::DRAFT])->save();
        $audit->record('marketplace.vendor_product_created', null, ['publisher' => $publisher->slug, 'slug' => $product->slug], $this->user($request));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Product created. Upload a release; it is listed after review.']);

        return redirect()->route('vendor.products.edit', $product);
    }

    public function edit(Request $request, MarketplaceProduct $product): Response
    {
        $publisher = $this->publisher($request);
        $this->ensureOwn($product, $publisher);

        return Inertia::render('storefront/vendor/product', [
            'vendor' => $publisher->slug,
            'product' => [
                'slug' => $product->slug,
                'name' => $product->name,
                'extension_name' => $product->extension_name,
                'type' => $product->type,
                'summary' => $product->summary,
                'description' => $product->description,
                'documentation_url' => $product->documentation_url,
                'support_email' => $product->support_email,
                'support_url' => $product->support_url,
                'price' => $product->money()->toDisplayDecimal(),
                'status' => $product->status,
                'updates_months' => $product->updates_months,
                'activation_limit' => $product->activation_limit,
            ],
            'releases' => $product->releases()->orderByDesc('id')->get()->map(fn (MarketplaceRelease $r) => [
                'id' => $r->id,
                'version' => $r->version,
                'status' => $r->status,
                'review_notes' => $r->review_notes,
                'created_at' => $r->created_at?->toDateString(),
                'scan' => $r->scan['summary'] ?? null,
            ]),
        ]);
    }

    public function update(Request $request, MarketplaceProduct $product, AuditLogger $audit): RedirectResponse
    {
        $publisher = $this->publisher($request, write: true);
        $this->ensureOwn($product, $publisher);
        $data = $this->validateProduct($request, $publisher, $product);

        // Sellers can retire a product or bring it back to draft; listing it is the owner's decision.
        $status = $request->validate(['status' => ['required', Rule::in([MarketplaceProduct::DRAFT, MarketplaceProduct::RETIRED, $product->status])]])['status'];
        $product->forceFill([...$data, 'status' => $status])->save();
        $audit->record('marketplace.vendor_product_updated', null, ['publisher' => $publisher->slug, 'slug' => $product->slug], $this->user($request));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Product saved.']);

        return redirect()->route('vendor.products.edit', $product);
    }

    public function upload(Request $request, MarketplaceProduct $product, ReleasePublisher $publisherService): RedirectResponse
    {
        $publisher = $this->publisher($request, write: true);
        $this->ensureOwn($product, $publisher);

        $data = $request->validate([
            'package' => ['required', 'file', 'mimes:zip', 'max:'.((int) config('ecomodular.marketplace.server.max_package_mb', 50) * 1024)],
            'changelog' => ['nullable', 'string', 'max:5000'],
        ]);
        $file = $request->file('package');
        abort_unless($file !== null && ! is_array($file), 422);

        $release = $publisherService->upload($product, $file, $data['changelog'] ?? null, $this->user($request));
        Inertia::flash('toast', ['type' => 'success', 'message' => "Version {$release->version} passed validation and is waiting for review."]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validateProduct(Request $request, Publisher $publisher, ?MarketplaceProduct $product): array
    {
        $vendor = preg_quote($publisher->slug, '/');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'slug' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('marketplace_products', 'slug')->ignore($product?->id)],
            'extension_name' => ['required', 'string', 'max:100', "regex:/^{$vendor}\\/[a-z0-9-]+$/"],
            'type' => ['required', Rule::in(MarketplaceProduct::TYPES)],
            'summary' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:20000'],
            'documentation_url' => ['nullable', 'url:https', 'max:500'],
            'support_email' => ['nullable', 'email', 'max:191'],
            'support_url' => ['nullable', 'url:https', 'max:500'],
            'price' => ['required', 'regex:/^\d{1,9}(\.\d{1,2})?$/'],
            'updates_months' => ['required', 'integer', 'min:0', 'max:120'],
            'activation_limit' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ], ['extension_name.regex' => "Package names must start with your vendor name: {$publisher->slug}/your-package."]);

        if ($product !== null && $product->releases()->exists() && ($data['extension_name'] !== $product->extension_name || $data['type'] !== $product->type)) {
            abort(422, 'The package name and type cannot change once releases exist.');
        }

        $exists = MarketplaceProduct::withTrashed()->where('type', $data['type'])->where('extension_name', $data['extension_name'])
            ->when($product, fn ($q) => $q->whereKeyNot($product->id))->exists();
        abort_if($exists, 422, 'Another product already sells this package.');

        return $data;
    }

    private function publisher(Request $request, bool $write = false): Publisher
    {
        $publisher = PublisherMember::publisherFor($this->user($request));
        abort_if($publisher === null, 403);
        abort_if($write && ! $publisher->isActive(), 403, 'Your seller account is suspended.');

        return $publisher;
    }

    private function ensureOwn(MarketplaceProduct $product, Publisher $publisher): void
    {
        abort_unless($product->publisher_id === $publisher->id, 404);
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
