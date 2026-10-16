<?php

namespace EcoModular\FlashSales;

use App\Core\Extensions\ModuleSettingsSchema;
use App\Core\Extensions\StorefrontSlots;
use App\Core\Settings\StoreSettings;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Pricing\SalePrices;
use App\Domains\Catalog\StorefrontCatalog;
use App\Models\User;
use EcoModular\FlashSales\Models\FlashSale;
use EcoModular\FlashSales\Models\FlashSaleProduct;
use Illuminate\Support\ServiceProvider;

/**
 * Wires flash sales into the platform:
 *  - catalog sale prices (shown price = cart price = order price);
 *  - the "Flash sale" panel in the admin product editor;
 *  - the countdown on product pages and the home page sale banner.
 */
class FlashSalesServiceProvider extends ServiceProvider
{
    public const MODULE = 'ecomodular/flash-sales';

    public const ROUTE = 'admin.ecomodular.flash-sales.';

    public function boot(SalePrices $prices, StorefrontSlots $slots, ModuleSettingsSchema $settings): void
    {
        $prices->addProvider($this->app->make(FlashSaleOffers::class));

        $slots->register('admin.product.sidebar', self::MODULE, 'ProductFlashSalePanel', function (array $context) {
            $product = $context['product'] ?? null;
            $user = auth()->user();

            // Saving needs both permissions, so the panel does too.
            if (! $product instanceof Product || ! $user instanceof User || ! $user->can('flash_sales.manage') || ! $user->can('products.update')) {
                return null;
            }

            return $this->panelProps($product);
        });

        $slots->register('product.after_details', self::MODULE, 'FlashSaleCountdown', function (array $context) use ($settings) {
            $product = $context['product'] ?? null;

            if (! $product instanceof Product || ! (bool) $settings->of(self::MODULE, 'show_countdown', true)) {
                return null;
            }

            // Count down to the end of the sale that sets the price shown (the best offer).
            $currency = $this->app->make(StoreSettings::class)->currency();
            $prices = $this->app->make(SalePrices::class);

            foreach ($product->variants as $variant) {
                $offer = $prices->forVariant($variant, $currency)['offer'] ?? null;

                if ($offer !== null && $offer->endsAt !== null) {
                    return ['name' => $offer->name, 'endsAt' => $offer->endsAt->toIso8601String()];
                }
            }

            return null;
        }, 10);

        $slots->register('home.after_featured', self::MODULE, 'FlashSaleBanner', function () use ($settings) {
            $sale = FlashSale::query()->live()->where('show_banner', true)->orderBy('ends_at')->first();

            if ($sale === null) {
                return null;
            }

            $limit = (int) $settings->of(self::MODULE, 'banner_products', 8);
            $catalog = $this->app->make(StorefrontCatalog::class);
            $currency = $this->app->make(StoreSettings::class)->currency();
            $ids = array_values(array_map('intval', $sale->items()->pluck('product_id')->all()));
            $products = $catalog->cardsFor($ids, $currency, $limit);

            return $products === [] ? null : [
                'name' => $sale->name,
                'endsAt' => $sale->ends_at->toIso8601String(),
                'products' => $products,
            ];
        }, 10);
    }

    /**
     * @return array<string, mixed>
     */
    private function panelProps(Product $product): array
    {
        // The product's place in each upcoming or live sale (it may be in several).
        $memberships = FlashSaleProduct::query()->where('product_id', $product->id)->get()->keyBy('flash_sale_id');

        return [
            'productId' => $product->id,
            'updateUrl' => route(self::ROUTE.'product.update', $product->id),
            'salesUrl' => route(self::ROUTE.'index'),
            'sales' => FlashSale::query()->where('ends_at', '>', now())->orderBy('starts_at')->get()->map(fn (FlashSale $s) => [
                'membership' => $memberships->has($s->id) ? [
                    'discount_type' => $memberships->get($s->id)->discount_type,
                    'discount_value' => $memberships->get($s->id)->discount_value,
                ] : null,
                'id' => $s->id,
                'name' => $s->name,
                'state' => $s->state(),
                'discount' => $s->discount_type === FlashSale::PERCENT
                    ? rtrim(rtrim(number_format((float) $s->discount_value, 2, '.', ''), '0'), '.').'%'
                    : $s->discount_value,
                'starts_at' => $s->starts_at->toIso8601String(),
                'ends_at' => $s->ends_at->toIso8601String(),
            ])->values()->all(),
        ];
    }
}
