<?php

namespace EcoModular\BuyTogetherSave\Http;

use App\Domains\Cart\CartManager;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Http\Controllers\Controller;
use App\Models\User;
use EcoModular\BuyTogetherSave\BuyTogetherDealResolver;
use EcoModular\BuyTogetherSave\BuyTogetherSettings;
use EcoModular\BuyTogetherSave\Models\BuyTogetherDeal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * Handles storefront "Add Bundle to Cart & Save" actions.
 */
class BuyTogetherStorefrontController extends Controller
{
    public function add(Request $request, CartManager $carts, BuyTogetherSettings $settings, BuyTogetherDealResolver $resolver): RedirectResponse
    {
        if (! $settings->enabled()) {
            abort(404, 'Buy Together & Save is disabled.');
        }

        $data = $request->validate([
            'product_id' => ['required', 'integer'],
            'variant_ids' => ['required', 'array', 'min:2', 'max:6'],
            'variant_ids.*' => ['required', 'integer', 'distinct'],
            'deal_id' => ['nullable', 'integer'],
        ]);

        $mainProduct = Product::query()->visible()->find($data['product_id']);
        abort_if($mainProduct === null, 404, 'Product not found or not available.');

        $variants = ProductVariant::query()
            ->whereIn('id', $data['variant_ids'])
            ->where('is_active', true)
            ->whereHas('product', fn ($q) => $q->visible())
            ->with(['product'])
            ->get();

        abort_if($variants->count() !== count($data['variant_ids']), 422, 'One or more items in the bundle are currently unavailable.');

        // Only what the product page offers can be bundled: the main product
        // plus companions of its live deal (or the automatic bundle), one
        // variant per product. The offer, not the request, decides the deal.
        $offer = $resolver->offer($mainProduct);
        abort_if($offer === null, 422, 'This product has no bundle offer.');

        /** @var BuyTogetherDeal|null $deal */
        $deal = $offer['deal'];
        $offered = $offer['companions']->pluck('id')->map(fn ($id) => (int) $id)->push((int) $mainProduct->id);
        $productIds = $variants->pluck('product_id')->map(fn ($id) => (int) $id);

        abort_if(
            $productIds->diff($offered)->isNotEmpty()
                || $productIds->unique()->count() !== $productIds->count()
                || ! $productIds->contains((int) $mainProduct->id),
            422,
            'These items are not part of this bundle offer.',
        );

        $minimum = max(2, (int) ($deal->min_items_required ?? 2));
        abort_if($variants->count() < $minimum, 422, "Choose at least {$minimum} items for this bundle.");

        $user = $request->user();
        $shopper = $user instanceof User ? $user : null;
        $cart = $carts->current($request->session(), $shopper, create: true);
        abort_if($cart === null, 500, 'Unable to initialize shopping cart.');

        $bundleGroupId = 'bts_'.Str::random(16);
        $dealId = $deal?->id;
        $deal?->increment('conversions_count');

        DB::transaction(function () use ($carts, $variants, $cart, $bundleGroupId, $dealId, $data) {
            foreach ($variants as $variant) {
                $carts->add($cart, $variant, 1);

                DB::table('buy_together_cart_lines')->insert([
                    'cart_id' => $cart->id,
                    'variant_id' => $variant->id,
                    'deal_id' => $dealId,
                    'bundle_group_id' => $bundleGroupId,
                    'is_trigger' => $variant->product_id === (int) $data['product_id'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Bundle added to your cart with bundle savings applied!'),
        ]);

        return back(303);
    }
}
