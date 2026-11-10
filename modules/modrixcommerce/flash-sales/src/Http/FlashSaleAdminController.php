<?php

namespace EcoModular\FlashSales\Http;

use App\Core\Audit\AuditLogger;
use App\Core\Settings\StoreSettings;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Catalog\Pricing\SaleOffer;
use App\Http\Controllers\Controller;
use App\Support\Like;
use EcoModular\FlashSales\FlashSalesServiceProvider;
use EcoModular\FlashSales\Models\FlashSale;
use EcoModular\FlashSales\Models\FlashSaleProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin → Marketing → Flash Sales (`can:flash_sales.manage`).
 */
class FlashSaleAdminController extends Controller
{
    private const ROUTE = FlashSalesServiceProvider::ROUTE;

    private const PAGE = 'module/modrixcommerce/flash-sales/admin/';

    public function __construct(private readonly AuditLogger $audit, private readonly StoreSettings $store) {}

    public function index(): Response
    {
        $sales = FlashSale::query()->withCount('items')->orderByDesc('starts_at')->get();

        return Inertia::render(self::PAGE.'index', [
            'currency' => $this->store->currency(),
            'sales' => $sales->map(fn (FlashSale $s) => [
                ...$this->summary($s),
                'products_count' => $s->items_count,
                'edit_url' => route(self::ROUTE.'edit', $s),
                'delete_url' => route(self::ROUTE.'destroy', $s),
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
        $sale = FlashSale::query()->create($this->validated($request));
        $this->audit->record('flash_sale.created', $sale, ['name' => $sale->name]);

        return $this->done(route(self::ROUTE.'edit', $sale), __('Flash sale created. Now add its products.'));
    }

    public function edit(FlashSale $sale): Response
    {
        return Inertia::render(self::PAGE.'form', $this->formProps($sale));
    }

    public function update(Request $request, FlashSale $sale): RedirectResponse
    {
        $sale->update($this->validated($request));
        $this->audit->record('flash_sale.updated', $sale, ['name' => $sale->name]);

        return $this->done(route(self::ROUTE.'edit', $sale), __('Flash sale saved.'));
    }

    public function destroy(FlashSale $sale): RedirectResponse
    {
        $this->audit->record('flash_sale.deleted', $sale, ['name' => $sale->name]);
        $sale->delete();

        return $this->done(route(self::ROUTE.'index'), __('Flash sale deleted.'));
    }

    public function addProducts(Request $request, FlashSale $sale): RedirectResponse
    {
        $data = $request->validate([
            'product_ids' => ['required', 'array', 'min:1', 'max:200'],
            'product_ids.*' => ['integer', Rule::exists('products', 'id')->whereNull('deleted_at')],
        ]);

        $added = 0;
        foreach (array_unique(array_map('intval', $data['product_ids'])) as $productId) {
            $added += FlashSaleProduct::query()->firstOrCreate(['flash_sale_id' => $sale->id, 'product_id' => $productId])->wasRecentlyCreated ? 1 : 0;
        }

        $this->audit->record('flash_sale.products_changed', $sale, ['added' => $added]);

        return $this->done(route(self::ROUTE.'edit', $sale), trans_choice(':count product added.|:count products added.', $added));
    }

    public function updateItem(Request $request, FlashSale $sale, FlashSaleProduct $item): RedirectResponse
    {
        abort_unless($item->flash_sale_id === $sale->id, 404);
        $item->update($this->override($request));
        $this->audit->record('flash_sale.products_changed', $sale, ['product_id' => $item->product_id, 'override' => $item->discount_type !== null]);

        return $this->done(route(self::ROUTE.'edit', $sale), __('Product discount saved.'));
    }

    public function removeItem(FlashSale $sale, FlashSaleProduct $item): RedirectResponse
    {
        abort_unless($item->flash_sale_id === $sale->id, 404);
        $item->delete();
        $this->audit->record('flash_sale.products_changed', $sale, ['removed' => $item->product_id]);

        return $this->done(route(self::ROUTE.'edit', $sale), __('Product removed from the sale.'));
    }

    /** Products for the picker (name or SKU). */
    public function searchProducts(Request $request): JsonResponse
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:120'], 'sale' => ['nullable', 'integer']]);
        $q = trim((string) ($data['q'] ?? ''));
        $inSale = isset($data['sale']) ? FlashSaleProduct::query()->where('flash_sale_id', (int) $data['sale'])->pluck('product_id')->all() : [];
        $currency = $this->store->currency();

        $products = Product::query()
            ->with(['variants.prices', 'images' => fn ($i) => $i->limit(1)])
            ->when($q !== '', fn ($query) => $query->where(function ($w) use ($q) {
                Like::where($w, 'name', $q);
                $w->orWhereHas('variants', fn ($v) => Like::where($v, 'sku', $q));
            }))
            ->orderBy('name')
            ->limit(30)
            ->get();

        return response()->json(['products' => $products->map(fn (Product $p) => [
            'id' => $p->id,
            'name' => $p->name,
            'status' => $p->status->value,
            'price' => $p->variants->map(fn (ProductVariant $v) => $v->priceIn($currency))->filter()->sortBy(fn ($m) => (string) $m->amount())->first(),
            'image' => $p->images->first()?->url(),
            'in_sale' => in_array($p->id, $inSale, true),
        ])->values()]);
    }

    /**
     * The product editor's "Flash sale" panel: put the product in a sale (or
     * take it out), with an optional discount of its own.
     */
    public function updateProduct(Request $request, Product $product): RedirectResponse
    {
        abort_unless($request->user()?->can('products.update'), 403);

        $data = $request->validate([
            // Only sales that have not ended, as offered by the panel.
            'sale_id' => ['required', 'integer', Rule::exists('flash_sales', 'id')->where(fn ($q) => $q->where('ends_at', '>', now()))],
            'include' => ['required', 'boolean'],
        ]);
        $saleId = (int) $data['sale_id'];
        $include = (bool) $data['include'];

        // Only the chosen sale changes; the product's other sales are left alone.
        if ($include) {
            FlashSaleProduct::query()->updateOrCreate(
                ['flash_sale_id' => $saleId, 'product_id' => $product->id],
                $this->override($request),
            );
        } else {
            FlashSaleProduct::query()->where('flash_sale_id', $saleId)->where('product_id', $product->id)->delete();
        }

        $this->audit->record('flash_sale.products_changed', $product, ['product_id' => $product->id, 'sale_id' => $saleId, 'included' => $include]);

        return $this->done(url()->previous(), $include ? __('Product added to the flash sale.') : __('Product removed from the flash sale.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function formProps(?FlashSale $sale): array
    {
        $timezone = $this->store->timezone();
        $sale?->load(['items.product.variants.prices', 'items.product.images']);
        $currency = $this->store->currency();

        return [
            'currency' => $currency,
            'timezone' => $timezone,
            'sale' => $sale ? [
                ...$this->summary($sale),
                'starts_at_local' => $sale->starts_at->copy()->setTimezone($timezone)->format('Y-m-d\TH:i'),
                'ends_at_local' => $sale->ends_at->copy()->setTimezone($timezone)->format('Y-m-d\TH:i'),
                'update_url' => route(self::ROUTE.'update', $sale),
                'add_products_url' => route(self::ROUTE.'products.store', $sale),
                'items' => $sale->items->sortBy(fn (FlashSaleProduct $i) => $i->product->name)->map(function (FlashSaleProduct $i) use ($currency, $sale) {
                    $price = $i->product->variants->map(fn (ProductVariant $v) => $v->priceIn($currency))->filter()->sortBy(fn ($m) => (string) $m->amount())->first();
                    // The same calculation the storefront and cart use.
                    $i->setRelation('sale', $sale);
                    $offer = new SaleOffer($i->effectiveType(), $i->effectiveValue(), $sale->name, currency: $currency);

                    return [
                        'id' => $i->id,
                        'product_id' => $i->product_id,
                        'name' => $i->product->name,
                        'image' => $i->product->images->first()?->url(),
                        'price' => $price,
                        'sale_price' => $price === null ? null : $offer->apply($price),
                        'discount_type' => $i->discount_type,
                        'discount_value' => $i->discount_value,
                        'update_url' => route(self::ROUTE.'products.update', [$sale, $i]),
                        'remove_url' => route(self::ROUTE.'products.destroy', [$sale, $i]),
                    ];
                })->values(),
            ] : null,
            'storeUrl' => route(self::ROUTE.'store'),
            'indexUrl' => route(self::ROUTE.'index'),
            'searchUrl' => route(self::ROUTE.'products.search'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(FlashSale $sale): array
    {
        return [
            'id' => $sale->id,
            'name' => $sale->name,
            'discount_type' => $sale->discount_type,
            'discount_value' => $sale->discount_value,
            'starts_at' => $sale->starts_at->toIso8601String(),
            'ends_at' => $sale->ends_at->toIso8601String(),
            'is_active' => $sale->is_active,
            'show_banner' => $sale->show_banner,
            'state' => $sale->state(),
        ];
    }

    /**
     * @return array{name: string, discount_type: string, discount_value: string, starts_at: Carbon, ends_at: Carbon, is_active: bool, show_banner: bool}
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'discount_type' => ['required', Rule::in([FlashSale::PERCENT, FlashSale::FIXED])],
            'discount_value' => ['required', 'string', 'regex:/^\d{1,13}(\.\d{1,4})?$/'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'is_active' => ['boolean'],
            'show_banner' => ['boolean'],
        ]);

        $this->checkAmount($data['discount_type'], $data['discount_value'], 'discount_value');
        $timezone = $this->store->timezone();

        return [
            'name' => $data['name'],
            'discount_type' => $data['discount_type'],
            'discount_value' => $data['discount_value'],
            // Entered in the store's time zone.
            'starts_at' => Carbon::parse($data['starts_at'], $timezone)->utc(),
            'ends_at' => Carbon::parse($data['ends_at'], $timezone)->utc(),
            'is_active' => (bool) ($data['is_active'] ?? false),
            'show_banner' => (bool) ($data['show_banner'] ?? false),
        ];
    }

    /**
     * An optional discount override (both fields, or neither).
     *
     * @return array{discount_type: string|null, discount_value: string|null}
     */
    private function override(Request $request): array
    {
        $data = $request->validate([
            'discount_type' => ['nullable', 'required_with:discount_value', Rule::in([FlashSale::PERCENT, FlashSale::FIXED])],
            'discount_value' => ['nullable', 'required_with:discount_type', 'string', 'regex:/^\d{1,13}(\.\d{1,4})?$/'],
        ]);

        if (isset($data['discount_type'], $data['discount_value'])) {
            $this->checkAmount($data['discount_type'], $data['discount_value'], 'discount_value');

            return ['discount_type' => $data['discount_type'], 'discount_value' => $data['discount_value']];
        }

        return ['discount_type' => null, 'discount_value' => null];
    }

    /** Percentages 1–90; fixed amounts above zero. */
    private function checkAmount(string $type, string $value, string $field): void
    {
        $number = (float) $value;
        $valid = $type === FlashSale::PERCENT ? $number >= 1 && $number <= 90 : $number > 0;

        if (! $valid) {
            throw ValidationException::withMessages([$field => $type === FlashSale::PERCENT ? 'A percentage discount must be between 1 and 90.' : 'Enter an amount above zero.']);
        }
    }

    private function done(string $to, string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return redirect()->to($to);
    }
}
