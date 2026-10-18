<?php

namespace EcoModular\CrossSellUpsell\Http;

use App\Core\Audit\AuditLogger;
use App\Core\Settings\StoreSettings;
use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Http\Controllers\Controller;
use App\Support\Like;
use EcoModular\CrossSellUpsell\CrossSellUpsellServiceProvider as Module;
use EcoModular\CrossSellUpsell\Models\Offer;
use EcoModular\CrossSellUpsell\OfferStats;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin → Marketing → Cross-sell & Upsell (`can:cross_sell.manage`).
 */
class OfferAdminController extends Controller
{
    private const ROUTE = Module::ADMIN_ROUTE;

    private const PAGE = 'module/ecomodular/cross-sell-upsell/admin/';

    public function __construct(private readonly AuditLogger $audit, private readonly StoreSettings $store) {}

    public function index(OfferStats $stats): Response
    {
        $results = $stats->results();

        return Inertia::render(self::PAGE.'index', [
            'currency' => $this->store->currency(),
            'offers' => Offer::query()->orderByDesc('priority')->orderBy('id')->get()->map(fn (Offer $o) => [
                ...$this->summary($o),
                'views' => $o->views,
                'adds' => $o->adds,
                'orders' => $results[$o->id]['orders'] ?? 0,
                'revenue' => $results[$o->id]['revenue'] ?? [],
                'edit_url' => route(self::ROUTE.'edit', $o),
                'delete_url' => route(self::ROUTE.'destroy', $o),
            ])->values(),
            'createUrl' => route(self::ROUTE.'create'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render(self::PAGE.'form', $this->formProps(null));
    }

    public function store(Request $request): RedirectResponse
    {
        $offer = Offer::query()->create($this->validated($request));
        $this->audit->record('cross_sell_offer.created', $offer, ['name' => $offer->name]);

        return $this->done(route(self::ROUTE.'index'), __('Offer created.'));
    }

    public function edit(Offer $offer): Response
    {
        return Inertia::render(self::PAGE.'form', $this->formProps($offer));
    }

    public function update(Request $request, Offer $offer): RedirectResponse
    {
        $offer->update($this->validated($request));
        $this->audit->record('cross_sell_offer.updated', $offer, ['name' => $offer->name]);

        return $this->done(route(self::ROUTE.'edit', $offer), __('Offer saved.'));
    }

    public function destroy(Offer $offer): RedirectResponse
    {
        $this->audit->record('cross_sell_offer.deleted', $offer, ['name' => $offer->name]);
        $offer->delete();

        return $this->done(route(self::ROUTE.'index'), __('Offer deleted.'));
    }

    /** Products for the pickers (name or SKU). */
    public function searchProducts(Request $request): JsonResponse
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:120'], 'ids' => ['nullable', 'array', 'max:100'], 'ids.*' => ['integer']]);
        $q = trim((string) ($data['q'] ?? ''));

        $products = Product::query()
            ->with(['variants.prices', 'images' => fn ($i) => $i->limit(1)])
            ->when(isset($data['ids']), fn ($query) => $query->whereIn('id', array_map('intval', $data['ids'])))
            ->when(! isset($data['ids']) && $q !== '', fn ($query) => $query->where(function ($w) use ($q) {
                Like::where($w, 'name', $q);
                $w->orWhereHas('variants', fn ($v) => Like::where($v, 'sku', $q));
            }))
            ->orderBy('name')
            ->limit(30)
            ->get();

        return response()->json(['products' => $products->map(fn (Product $p) => $this->productRow($p))->values()]);
    }

    /**
     * @return array<string, mixed>
     */
    private function productRow(Product $product): array
    {
        $currency = $this->store->currency();

        return [
            'id' => $product->id,
            'name' => $product->name,
            'status' => $product->status->value,
            'price' => $product->variants->map(fn (ProductVariant $v) => $v->priceIn($currency))->filter()->sortBy(fn ($m) => (string) $m->amount())->first(),
            'image' => $product->images->first()?->url(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formProps(?Offer $offer): array
    {
        $timezone = $this->store->timezone();
        $picked = fn (array $ids) => $ids === [] ? [] : Product::query()
            ->with(['variants.prices', 'images' => fn ($i) => $i->limit(1)])
            ->whereIn('id', $ids)
            ->get()
            ->sortBy(fn (Product $p) => array_search($p->id, $ids, true))
            ->map(fn (Product $p) => $this->productRow($p))
            ->values()
            ->all();

        return [
            'currency' => $this->store->currency(),
            'timezone' => $timezone,
            'categories' => Category::query()->orderBy('name')->get(['id', 'name'])->map(fn (Category $c) => ['id' => $c->id, 'name' => $c->name])->values(),
            'offer' => $offer ? [
                ...$this->summary($offer),
                'heading' => $offer->heading,
                'trigger_ids' => $offer->ids('trigger_ids'),
                'product_ids' => $offer->ids('product_ids'),
                'trigger_products' => $offer->trigger_type === Offer::TRIGGER_PRODUCTS ? $picked($offer->ids('trigger_ids')) : [],
                'products' => $picked($offer->ids('product_ids')),
                'starts_at_local' => $offer->starts_at?->copy()->setTimezone($timezone)->format('Y-m-d\TH:i'),
                'ends_at_local' => $offer->ends_at?->copy()->setTimezone($timezone)->format('Y-m-d\TH:i'),
                'update_url' => route(self::ROUTE.'update', $offer),
            ] : null,
            'storeUrl' => route(self::ROUTE.'store'),
            'indexUrl' => route(self::ROUTE.'index'),
            'searchUrl' => route(self::ROUTE.'products.search'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Offer $offer): array
    {
        return [
            'id' => $offer->id,
            'name' => $offer->name,
            'type' => $offer->type,
            'trigger_type' => $offer->trigger_type,
            'trigger_count' => count($offer->ids('trigger_ids')),
            'suggestion_mode' => $offer->suggestion_mode,
            'products_count' => count($offer->ids('product_ids')),
            'show_on_product' => $offer->show_on_product,
            'show_in_popup' => $offer->show_in_popup,
            'show_in_cart' => $offer->show_in_cart,
            'show_at_checkout' => $offer->show_at_checkout,
            'discount_type' => $offer->discount_type,
            'discount_value' => $offer->discount_value,
            'priority' => $offer->priority,
            'is_active' => $offer->is_active,
            'state' => $offer->state(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $places = array_values(Offer::PLACES);
        $triggerTable = $request->input('trigger_type') === Offer::TRIGGER_CATEGORIES ? 'categories' : 'products';

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'heading' => ['nullable', 'string', 'max:120'],
            'type' => ['required', Rule::in([Offer::UPSELL, Offer::CROSS_SELL])],
            'trigger_type' => ['required', Rule::in([Offer::TRIGGER_ANY, Offer::TRIGGER_PRODUCTS, Offer::TRIGGER_CATEGORIES])],
            'trigger_ids' => ['nullable', 'array', 'max:100', Rule::requiredIf(fn () => $request->input('trigger_type') !== Offer::TRIGGER_ANY)],
            'trigger_ids.*' => ['integer', 'distinct', Rule::exists($triggerTable, 'id')],
            'suggestion_mode' => ['required', Rule::in([Offer::MANUAL, Offer::AUTOMATIC])],
            'product_ids' => ['nullable', 'array', 'max:'.Offer::MAX_PRODUCTS, Rule::requiredIf(fn () => $request->input('suggestion_mode') === Offer::MANUAL)],
            'product_ids.*' => ['integer', 'distinct', Rule::exists('products', 'id')->whereNull('deleted_at')],
            'show_on_product' => ['boolean', function (string $attribute, mixed $value, \Closure $fail) use ($request, $places) {
                if (! collect($places)->contains(fn (string $field) => $request->boolean($field))) {
                    $fail('Choose at least one place to show the offer.');
                }
            }],
            'show_in_popup' => ['boolean'],
            'show_in_cart' => ['boolean'],
            'show_at_checkout' => ['boolean'],
            'discount_type' => ['nullable', Rule::in([Offer::PERCENT, Offer::FIXED])],
            'discount_value' => ['nullable', 'required_with:discount_type', 'string', 'regex:/^\d{1,13}(\.\d{1,4})?$/', function (string $attribute, mixed $value, \Closure $fail) use ($request) {
                $number = (float) $value;

                if ($request->input('discount_type') === Offer::PERCENT && ($number < 1 || $number > 90)) {
                    $fail('A percentage discount must be between 1 and 90.');
                } elseif ($request->input('discount_type') === Offer::FIXED && $number <= 0) {
                    $fail('Enter an amount above zero.');
                }
            }],
            'priority' => ['nullable', 'integer', 'min:-1000', 'max:1000'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'is_active' => ['boolean'],
        ], [
            'trigger_ids.required' => 'Choose at least one product or category that triggers the offer.',
            'product_ids.required' => 'Pick the products to suggest, or switch to automatic suggestions.',
        ]);

        $timezone = $this->store->timezone();
        $discount = isset($data['discount_type'], $data['discount_value']);

        return [
            'name' => $data['name'],
            'heading' => ($data['heading'] ?? '') !== '' ? $data['heading'] : null,
            'type' => $data['type'],
            'trigger_type' => $data['trigger_type'],
            'trigger_ids' => $data['trigger_type'] === Offer::TRIGGER_ANY ? [] : array_values(array_map('intval', $data['trigger_ids'] ?? [])),
            'suggestion_mode' => $data['suggestion_mode'],
            'product_ids' => $data['suggestion_mode'] === Offer::MANUAL ? array_values(array_map('intval', $data['product_ids'] ?? [])) : [],
            'show_on_product' => $request->boolean('show_on_product'),
            'show_in_popup' => $request->boolean('show_in_popup'),
            'show_in_cart' => $request->boolean('show_in_cart'),
            'show_at_checkout' => $request->boolean('show_at_checkout'),
            'discount_type' => $discount ? $data['discount_type'] : null,
            'discount_value' => $discount ? $data['discount_value'] : null,
            'priority' => (int) ($data['priority'] ?? 0),
            // Entered in the store's time zone.
            'starts_at' => isset($data['starts_at']) ? Carbon::parse($data['starts_at'], $timezone)->utc() : null,
            'ends_at' => isset($data['ends_at']) ? Carbon::parse($data['ends_at'], $timezone)->utc() : null,
            'is_active' => $request->boolean('is_active'),
        ];
    }

    private function done(string $to, string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return redirect()->to($to);
    }
}
