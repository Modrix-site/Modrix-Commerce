<?php

namespace EcoModular\ProductAddons;

use App\Core\Settings\StorefrontCurrencies;
use App\Core\Settings\StoreSettings;
use App\Domains\Cart\CartManager;
use App\Domains\Cart\Models\Cart;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Checkout\Pricing\PricedCart;
use App\Domains\Checkout\Pricing\PricingContext;
use App\Domains\Checkout\Pricing\PricingStep;
use App\Domains\Orders\Events\CartCheckedOut;
use App\Domains\Orders\Models\Order;
use App\Domains\Pricing\Money;
use App\Models\User;
use EcoModular\ProductAddons\Models\ProductAddon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddonPricing implements PricingStep
{
    public const SESSION_KEY = 'product_addons.selected';

    public function __construct(
        private readonly StoreSettings $store,
        private readonly StorefrontCurrencies $currencies,
    ) {}

    public function apply(PricedCart $priced, Cart $cart, PricingContext $context): void
    {
        if ($priced->lines === [] || ! Schema::hasTable('product_addons')) {
            return;
        }

        $cartId = $cart->exists ? (int) $cart->id : null;
        $productIds = $this->cartProductIds($priced);

        if ($cartId !== null) {
            $this->syncSessionSelectionsToCart($cartId, $productIds);
        }

        $selectionsByProduct = $this->selectedAddonIdsByProduct($cartId, $productIds);

        if ($selectionsByProduct === []) {
            return;
        }

        $allAddonIds = array_values(array_unique(array_merge(...array_values($selectionsByProduct))));

        if ($allAddonIds === []) {
            return;
        }

        /** @var Collection<int, ProductAddon> $addons */
        $addons = ProductAddon::query()
            ->active()
            ->whereIn('id', $allAddonIds)
            ->orderByDesc('priority')
            ->orderBy('id')
            ->get()
            ->keyBy('id');

        if ($addons->isEmpty()) {
            return;
        }

        foreach ($priced->lines as $line) {
            if ($line->unitPrice === null) {
                continue;
            }

            $product = $line->variant->product;
            $productId = (int) $product->id;
            $selectedIds = $selectionsByProduct[$productId] ?? [];

            if ($selectedIds === []) {
                continue;
            }

            $categoryIds = $product->relationLoaded('categories')
                ? $product->categories->pluck('id')->map(fn ($id) => (int) $id)->all()
                : $product->categories()->pluck('categories.id')->map(fn ($id) => (int) $id)->all();

            $appliedLabels = [];
            $appliedAddons = [];
            $addonUnitTotal = Money::zero($priced->currency);

            foreach ($selectedIds as $addonId) {
                $addon = $addons->get((int) $addonId);
                if (! $addon || ! $addon->matchesProduct($productId, $categoryIds)) {
                    continue;
                }

                $price = $this->addonMoney($addon, $priced->currency);
                $addonUnitTotal = $addonUnitTotal->plus($price);
                $appliedLabels[] = sprintf('%s (+%s)', $addon->name, $price->format());
                $appliedAddons[] = [
                    'id' => $addon->id,
                    'name' => $addon->name,
                    'unit_price' => $price,
                    'subtotal' => $price->multipliedBy($line->quantity)->rounded(),
                ];
            }

            if ($appliedLabels === []) {
                continue;
            }

            $line->baseUnitPrice = $line->unitPrice;
            $line->addons = $appliedAddons;
            $line->unitPrice = $line->unitPrice->plus($addonUnitTotal);
            // Keep catalog seen_unit_price intact so CartPricer::acknowledgePrices()
            // does not overwrite the base variant price with the addon-inflated price.
            $line->priceSeen = true;

            $baseVariantName = $line->variant->name;
            $addonSummary = implode(', ', $appliedLabels);
            $line->variant->name = $baseVariantName !== null && $baseVariantName !== ''
                ? "{$baseVariantName} + {$addonSummary}"
                : $addonSummary;
        }
    }

    /**
     * Adjusts a product variant's price on the storefront product page when addons are selected.
     */
    public function adjustVariantPrice(ProductVariant $variant, Money $price): Money
    {
        if (! Schema::hasTable('product_addons')) {
            return $price;
        }

        $productId = (int) $variant->product_id;
        if ($productId <= 0) {
            return $price;
        }

        $cart = $this->currentCart();
        $selectedByProduct = $this->selectedAddonIdsByProduct($cart?->id, [$productId]);
        $selectedIds = $selectedByProduct[$productId] ?? [];

        if ($selectedIds === []) {
            return $price;
        }

        $product = $variant->relationLoaded('product') && $variant->product instanceof Product
            ? $variant->product
            : Product::query()->with('categories:id')->find($productId);

        if (! $product) {
            return $price;
        }

        $categoryIds = $product->relationLoaded('categories')
            ? $product->categories->pluck('id')->map(fn ($id) => (int) $id)->all()
            : $product->categories()->pluck('categories.id')->map(fn ($id) => (int) $id)->all();

        $addons = ProductAddon::query()
            ->active()
            ->whereIn('id', $selectedIds)
            ->get();

        foreach ($addons as $addon) {
            if ($addon->matchesProduct($productId, $categoryIds)) {
                $price = $price->plus($this->addonMoney($addon, $price->currency()));
            }
        }

        return $price;
    }

    /**
     * Record purchased addons on the order and clean up cart rows.
     */
    public function recordOrder(CartCheckedOut $event): void
    {
        if (! Schema::hasTable('product_addon_orders') || ! Schema::hasTable('product_addon_cart_items')) {
            return;
        }

        $order = Order::query()->with('items')->find($event->orderId);
        if (! $order) {
            return;
        }

        $productQuantities = [];
        foreach ($order->items as $item) {
            $productId = (int) ($item->product_id ?? 0);
            if ($productId > 0) {
                $productQuantities[$productId] = ($productQuantities[$productId] ?? 0) + (int) $item->quantity;
            }
        }

        if ($productQuantities === []) {
            return;
        }

        $selections = $this->selectedAddonIdsByProduct($event->cartId, array_keys($productQuantities));

        foreach ($selections as $productId => $addonIds) {
            $qty = $productQuantities[$productId] ?? 0;
            if ($qty <= 0 || $addonIds === []) {
                continue;
            }

            $addons = ProductAddon::query()->active()->whereIn('id', $addonIds)->get();
            foreach ($addons as $addon) {
                $unit = $this->addonMoney($addon, $order->currency);
                $total = $unit->multipliedBy($qty)->rounded();

                DB::table('product_addon_orders')->insert([
                    'addon_id' => $addon->id,
                    'order_id' => $order->id,
                    'product_id' => $productId,
                    'addon_name' => $addon->name,
                    'addon_type' => $addon->type,
                    'quantity' => $qty,
                    'unit_price' => $unit->toDecimal(),
                    'total_price' => $total->toDecimal(),
                    'currency' => $order->currency,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        DB::table('product_addon_cart_items')
            ->where('cart_id', $event->cartId)
            ->delete();

        if (request()->hasSession()) {
            request()->session()->forget(self::SESSION_KEY);
        }
    }

    public function addonMoney(ProductAddon $addon, string $currency): Money
    {
        $baseCurrency = $this->store->currency();
        $baseMoney = Money::of((string) $addon->price, $baseCurrency);

        return ($this->currencies->convert($baseMoney, $currency) ?? Money::of((string) $addon->price, $currency))->rounded();
    }

    /**
     * @param  list<int>  $productIds
     * @return array<int, list<int>>
     */
    public function selectedAddonIdsByProduct(?int $cartId, array $productIds): array
    {
        $result = [];

        if ($cartId !== null && $productIds !== [] && Schema::hasTable('product_addon_cart_items')) {
            $rows = DB::table('product_addon_cart_items')
                ->where('cart_id', $cartId)
                ->whereIn('product_id', $productIds)
                ->get(['product_id', 'addon_id']);

            foreach ($rows as $row) {
                $pid = (int) $row->product_id;
                $aid = (int) $row->addon_id;
                $result[$pid] ??= [];
                if (! in_array($aid, $result[$pid], true)) {
                    $result[$pid][] = $aid;
                }
            }
        }

        if (request()->hasSession()) {
            /** @var array<int|string, list<int>> $sessionMap */
            $sessionMap = request()->session()->get(self::SESSION_KEY, []);
            foreach ($productIds as $pid) {
                $sessionIds = array_map('intval', $sessionMap[$pid] ?? $sessionMap[(string) $pid] ?? []);
                foreach ($sessionIds as $aid) {
                    $result[$pid] ??= [];
                    if (! in_array($aid, $result[$pid], true)) {
                        $result[$pid][] = $aid;
                    }
                }
            }
        }

        return $result;
    }

    private function currentCart(): ?Cart
    {
        if (! app()->bound('request') || ! request()->hasSession()) {
            return null;
        }

        $user = request()->user();

        return app(CartManager::class)->current(
            request()->session(),
            $user instanceof User ? $user : null,
        );
    }

    /**
     * @param  list<int>  $productIds
     */
    private function syncSessionSelectionsToCart(int $cartId, array $productIds): void
    {
        if (! request()->hasSession() || $productIds === [] || ! Schema::hasTable('product_addon_cart_items')) {
            return;
        }

        /** @var array<int|string, list<int>> $sessionMap */
        $sessionMap = request()->session()->get(self::SESSION_KEY, []);
        if ($sessionMap === []) {
            return;
        }

        foreach ($productIds as $pid) {
            $addonIds = array_map('intval', $sessionMap[$pid] ?? $sessionMap[(string) $pid] ?? []);
            if ($addonIds === []) {
                continue;
            }

            foreach ($addonIds as $addonId) {
                DB::table('product_addon_cart_items')->updateOrInsert(
                    [
                        'cart_id' => $cartId,
                        'product_id' => $pid,
                        'addon_id' => $addonId,
                    ],
                    [
                        'updated_at' => now(),
                        'created_at' => now(),
                    ],
                );
            }
        }
    }

    /**
     * @return list<int>
     */
    private function cartProductIds(PricedCart $cart): array
    {
        $ids = [];
        foreach ($cart->lines as $line) {
            $ids[] = (int) $line->variant->product_id;
        }

        return array_values(array_unique($ids));
    }
}
