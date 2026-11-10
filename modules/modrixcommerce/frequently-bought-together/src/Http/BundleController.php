<?php

namespace EcoModular\FrequentlyBoughtTogether\Http;

use App\Core\Extensions\ModuleSettingsSchema;
use App\Core\Settings\StoreSettings;
use App\Domains\Cart\CartManager;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Catalog\VariantAvailability;
use App\Http\Controllers\Controller;
use App\Models\User;
use EcoModular\FrequentlyBoughtTogether\FrequentlyBoughtTogetherServiceProvider as Module;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Storefront: adds the shopper's chosen bundle to the cart in one request.
 * Each variant is re-checked for visibility and availability here; the page
 * only offers what the recommender suggested, but the request is never
 * trusted to respect that.
 */
class BundleController extends Controller
{
    public function add(Request $request, CartManager $carts, ModuleSettingsSchema $settings): RedirectResponse
    {
        if (! (bool) $settings->of(Module::MODULE, 'enabled', true)) {
            abort(404);
        }

        $data = $request->validate([
            'product_id' => ['required', 'integer'],
            'current_variant_id' => ['nullable', 'integer'],
            'variant_ids' => ['required', 'array', 'min:1', 'max:5'],
            'variant_ids.*' => ['required', 'integer', 'distinct'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:'.CartManager::MAX_LINE_QUANTITY],
        ]);

        // The product being viewed must still be visible; the extras must belong
        // to visible products and be active variants.
        $current = Product::query()->visible()->whereKey($data['product_id'])->exists();
        abort_unless($current, 404);

        $variantIds = $data['variant_ids'];
        if (isset($data['current_variant_id'])) {
            abort_unless(ProductVariant::query()->whereKey($data['current_variant_id'])->where('product_id', $data['product_id'])->exists(), 404);
            $variantIds = array_values(array_unique([...$variantIds, (int) $data['current_variant_id']]));
        }

        $variants = ProductVariant::query()
            ->whereIn('id', $variantIds)
            ->where('is_active', true)
            ->whereHas('product', fn ($q) => $q->visible())
            ->with(['product', 'prices', 'stocks'])
            ->get();

        if ($variants->count() !== count($variantIds)) {
            abort(404);
        }

        $quantity = (int) ($data['quantity'] ?? 1);
        $currency = $this->currency($variants->first());
        $unavailable = $variants->first(fn (ProductVariant $v) => ! VariantAvailability::of($v, $currency)->allows($quantity));

        if ($unavailable instanceof ProductVariant) {
            throw ValidationException::withMessages([
                'bundle' => VariantAvailability::of($unavailable, $currency)->message() ?? 'One of the products is unavailable.',
            ]);
        }

        $cart = $carts->current($request->session(), $this->user($request), create: true);
        abort_if($cart === null, 500);

        foreach ($variants as $variant) {
            $carts->add($cart, $variant, $quantity);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => st('The bundle was added to your cart.')]);

        return back();
    }

    private function currency(ProductVariant $variant): string
    {
        $currency = $variant->prices->first()?->currency;

        return is_string($currency) ? $currency : app(StoreSettings::class)->currency();
    }

    private function user(Request $request): ?User
    {
        $user = $request->user();

        return $user instanceof User ? $user : null;
    }
}
