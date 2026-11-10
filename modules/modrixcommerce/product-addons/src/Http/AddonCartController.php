<?php

namespace EcoModular\ProductAddons\Http;

use App\Domains\Cart\CartManager;
use App\Domains\Catalog\Models\Product;
use App\Models\User;
use EcoModular\ProductAddons\AddonPricing;
use EcoModular\ProductAddons\Models\ProductAddon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddonCartController
{
    public function toggle(Request $request, CartManager $carts): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'addon_id' => ['required', 'integer', 'exists:product_addons,id'],
        ]);

        $productId = (int) $data['product_id'];
        $addonId = (int) $data['addon_id'];

        $product = Product::query()->with('categories:id')->findOrFail($productId);
        $addon = ProductAddon::query()->active()->findOrFail($addonId);

        $categoryIds = $product->categories->pluck('id')->map(fn ($id) => (int) $id)->all();
        abort_unless($addon->matchesProduct($productId, $categoryIds), 422, 'This addon does not apply to the selected product.');

        /** @var array<int|string, list<int>> $sessionMap */
        $sessionMap = $request->session()->get(AddonPricing::SESSION_KEY, []);
        $currentIds = array_map('intval', $sessionMap[$productId] ?? $sessionMap[(string) $productId] ?? []);

        $user = $request->user();
        $cart = $carts->current($request->session(), $user instanceof User ? $user : null);
        if ($cart && Schema::hasTable('product_addon_cart_items')) {
            $dbIds = DB::table('product_addon_cart_items')
                ->where('cart_id', $cart->id)
                ->where('product_id', $productId)
                ->pluck('addon_id')
                ->map(fn ($id) => (int) $id)
                ->all();
            $currentIds = array_values(array_unique([...$currentIds, ...$dbIds]));
        }

        $isSelected = in_array($addonId, $currentIds, true);

        if ($isSelected) {
            $updatedIds = array_values(array_filter($currentIds, fn (int $id) => $id !== $addonId));
            if ($cart && Schema::hasTable('product_addon_cart_items')) {
                DB::table('product_addon_cart_items')
                    ->where('cart_id', $cart->id)
                    ->where('product_id', $productId)
                    ->where('addon_id', $addonId)
                    ->delete();
            }
        } else {
            $updatedIds = [...$currentIds, $addonId];
            if ($cart && Schema::hasTable('product_addon_cart_items')) {
                DB::table('product_addon_cart_items')->updateOrInsert(
                    [
                        'cart_id' => $cart->id,
                        'product_id' => $productId,
                        'addon_id' => $addonId,
                    ],
                    [
                        'updated_at' => now(),
                        'created_at' => now(),
                    ],
                );
            }
        }

        $sessionMap[$productId] = $updatedIds;
        $request->session()->put(AddonPricing::SESSION_KEY, $sessionMap);

        return back();
    }
}
