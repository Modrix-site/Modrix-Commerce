<?php

namespace EcoModular\ProductAddons;

use App\Core\Extensions\ModuleSettingsSchema;
use App\Core\Extensions\StorefrontSlots;
use App\Core\Settings\StoreSettings;
use App\Domains\Cart\CartManager;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Catalog\Pricing\SalePrices;
use App\Domains\Checkout\Pricing\CartPricer;
use App\Domains\Orders\Events\CartCheckedOut;
use App\Domains\Pricing\Money;
use App\Models\User;
use EcoModular\ProductAddons\Models\ProductAddon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class ProductAddonsServiceProvider extends ServiceProvider
{
    public const MODULE = 'modrixcommerce/product-addons';

    public const ROUTE = 'modrixcommerce.product-addons.';

    public function register(): void
    {
        $this->app->singleton(AddonPricing::class);
    }

    public function boot(
        StorefrontSlots $slots,
        ModuleSettingsSchema $settings,
        CartPricer $pricer,
        SalePrices $salePrices,
        AddonPricing $addonPricing,
    ): void {
        if (! $pricer->hasStep(AddonPricing::class)) {
            $pricer->addStep($addonPricing, CartPricer::LINE_ADJUSTMENTS);
        }

        $salePrices->addVariantAdjuster(
            fn (ProductVariant $variant, Money $price) => $addonPricing->adjustVariantPrice($variant, $price),
        );

        Event::listen(CartCheckedOut::class, [$addonPricing, 'recordOrder']);

        $slots->block(
            self::MODULE,
            'product-addons',
            'Product Addons & Warranties',
            'ProductAddons',
            ['product.after_add_to_cart', 'product.after_price', 'product.after_details'],
            'product.after_add_to_cart',
            fn (array $context) => $this->resolveProductProps($context, $settings, $addonPricing),
            18,
        );
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>|null
     */
    private function resolveProductProps(
        array $context,
        ModuleSettingsSchema $settings,
        AddonPricing $addonPricing,
    ): ?array {
        if (! (bool) $settings->of(self::MODULE, 'enabled', true)) {
            return null;
        }

        $product = $context['product'] ?? null;
        if (is_array($product) && isset($product['id'])) {
            $product = Product::query()->with(['categories:id', 'variants.prices'])->find((int) $product['id']);
        }

        if (! $product instanceof Product || ! Schema::hasTable('product_addons')) {
            return null;
        }

        $addons = ProductAddon::forProduct($product);
        if ($addons->isEmpty()) {
            return null;
        }

        $productId = (int) $product->id;
        $currency = $this->app->make(StoreSettings::class)->currency();
        $cart = null;

        if (request()->hasSession()) {
            $user = request()->user();
            $cart = $this->app->make(CartManager::class)->current(
                request()->session(),
                $user instanceof User ? $user : null,
            );
        }

        $selectedByProduct = $addonPricing->selectedAddonIdsByProduct($cart?->id, [$productId]);
        $selectedIds = $selectedByProduct[$productId] ?? [];

        $defaultVariant = $product->relationLoaded('variants')
            ? $product->variants->first()
            : $product->variants()->with('prices')->first();
        $baseMoney = $defaultVariant?->priceIn($currency);
        if ($baseMoney !== null) {
            $sale = $this->app->make(SalePrices::class)->apply($productId, $baseMoney);
            if ($sale !== null) {
                $baseMoney = $sale['price'];
            }
        }

        $addonsMoney = Money::zero($currency);
        $addonItems = $addons->map(function (ProductAddon $addon) use ($addonPricing, $currency, $selectedIds, &$addonsMoney) {
            $money = $addonPricing->addonMoney($addon, $currency);
            $selected = in_array((int) $addon->id, $selectedIds, true);
            if ($selected) {
                $addonsMoney = $addonsMoney->plus($money);
            }

            return [
                'id' => $addon->id,
                'name' => $addon->name,
                'type' => $addon->type,
                'badge' => $addon->badge,
                'description' => $addon->description,
                'price' => $money->jsonSerialize(),
                'selected' => $selected,
            ];
        })->values()->all();

        $totalMoney = $baseMoney?->plus($addonsMoney);

        return [
            'productId' => $productId,
            'heading' => (string) ($settings->of(self::MODULE, 'heading', '') ?: 'Protection Plans & Extra Features'),
            'subtext' => (string) ($settings->of(self::MODULE, 'subtext', '') ?: 'Customize your order with extended warranty coverage or optional upgrades.'),
            'toggleUrl' => route(self::ROUTE.'toggle'),
            'basePrice' => $baseMoney?->jsonSerialize(),
            'addonsTotal' => $addonsMoney->jsonSerialize(),
            'totalPrice' => $totalMoney?->jsonSerialize(),
            'addons' => $addonItems,
        ];
    }
}
