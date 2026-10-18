<?php

namespace EcoModular\CrossSellUpsell;

use App\Core\Extensions\ModuleSettingsSchema;
use App\Core\Extensions\StorefrontSlots;
use App\Domains\Cart\CartManager;
use App\Domains\Cart\Models\Cart;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Catalog\StorefrontCatalog;
use App\Domains\Checkout\Pricing\CartPricer;
use App\Domains\Checkout\Pricing\PricedCart;
use App\Domains\Orders\Events\CartCheckedOut;
use App\Models\User;
use EcoModular\CrossSellUpsell\Models\Offer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

/**
 * Wires cross-sell and upsell offers into the platform. Everything shoppers
 * see comes through shared places and the global overlay, so it shows in
 * every theme:
 *  - product page block (default under "add to cart");
 *  - add-to-cart pop-up (storefront.overlay, after a regular add to cart);
 *  - cart block (default under the items);
 *  - checkout add-on (default above "Place order").
 * Offer prices come from a pricing step that runs before coupons; orders are
 * attributed to offers when the cart is checked out.
 */
class CrossSellUpsellServiceProvider extends ServiceProvider
{
    public const MODULE = 'ecomodular/cross-sell-upsell';

    public const ROUTE = 'ecomodular.cross-sell-upsell.';

    public const ADMIN_ROUTE = 'admin.ecomodular.cross-sell-upsell.';

    /** Most add-ons offered at checkout. */
    public const BUMP_MAX = 2;

    public function register(): void
    {
        $this->app->scoped(OfferFinder::class);
        $this->app->singleton(OfferPricing::class);
    }

    public function boot(StorefrontSlots $slots, ModuleSettingsSchema $settings, CartPricer $pricer): void
    {
        if (! $pricer->hasStep(OfferPricing::class)) {
            $pricer->addStep($this->app->make(OfferPricing::class), CartPricer::LINE_ADJUSTMENTS);
        }

        Event::listen(CartCheckedOut::class, fn (CartCheckedOut $event) => $this->app->make(OfferStats::class)->recordOrder($event->cartId, $event->orderId));

        $slots->block(self::MODULE, 'product-offers', 'Cross-sell & upsell offers', 'OfferProducts', ['product.after_add_to_cart', 'product.after_price', 'product.after_details', 'product.after_description'], 'product.after_add_to_cart', function (array $context) use ($settings) {
            $product = $context['product'] ?? null;

            if (! $product instanceof Product) {
                return null;
            }

            $found = $this->finder()->forProduct($product, 'product', $this->cartProductIds(), $this->limit($settings, 'product_max', 4, 8));

            if ($found === null) {
                return null;
            }

            $this->stats()->viewed($found['offer'], 'product');

            return [
                'heading' => $found['offer']->label(),
                'type' => $found['offer']->type,
                'items' => $found['items'],
                'place' => 'product',
                'addUrl' => route(self::ROUTE.'add'),
            ];
        }, 40);

        $slots->register('storefront.overlay', self::MODULE, 'AddedPopup', fn () => $this->popupProps($settings), 20);

        $slots->block(self::MODULE, 'cart-offers', 'Cross-sell offers in the cart', 'CartOffers', ['cart.after_lines', 'cart.after_totals'], 'cart.after_lines', function (array $context) use ($settings) {
            $priced = $context['cart'] ?? null;

            if (! $priced instanceof PricedCart || $priced->isEmpty()) {
                return null;
            }

            $products = $this->productsOf($priced);
            $items = $this->finder()->forProducts($products, 'cart', array_map(fn (Product $p) => $p->id, $products), $this->limit($settings, 'cart_max', 4, 8));

            if ($items === []) {
                return null;
            }

            $this->countViews($items, 'cart');

            return [
                'heading' => (string) ($settings->of(self::MODULE, 'cart_heading', '') ?: st('Complete your order')),
                'items' => $items,
                'place' => 'cart',
                'addUrl' => route(self::ROUTE.'add'),
            ];
        }, 40);

        $slots->block(self::MODULE, 'checkout-bump', 'Checkout add-on', 'CheckoutBump', ['checkout.before_place_order', 'checkout.after_summary'], 'checkout.before_place_order', fn (array $context) => $this->bumpProps($context['cart'] ?? null, $settings), 40);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function popupProps(ModuleSettingsSchema $settings): ?array
    {
        if (! (bool) $settings->of(self::MODULE, 'popup_enabled', true) || ! request()->hasSession()) {
            return null;
        }

        $added = request()->session()->get('cart.just_added');
        $variant = is_array($added) && isset($added['variant_id'])
            ? ProductVariant::query()->whereKey((int) $added['variant_id'])->whereHas('product', fn ($q) => $q->visible())->with(['product.images', 'product.variants'])->first()
            : null;

        if (! $variant instanceof ProductVariant) {
            return null;
        }

        $product = $variant->product;
        $found = $this->finder()->forProduct($product, 'popup', $this->cartProductIds(), $this->limit($settings, 'popup_max', 3, 4));

        if ($found === null) {
            return null;
        }

        $this->stats()->viewed($found['offer'], 'popup');
        $image = $product->images->firstWhere('id', $variant->image_id) ?? $product->images->first();

        return [
            // Changes with every add, so the pop-up opens again.
            'token' => (string) Str::uuid(),
            'added' => [
                'name' => $product->localized('name'),
                'variant_name' => $product->variants->count() > 1 ? $variant->name : null,
                'quantity' => (int) ($added['quantity'] ?? 1),
                'image' => $image ? ['url' => $image->url(), 'alt' => $image->alt] : null,
            ],
            'heading' => $found['offer']->label(),
            'items' => $found['items'],
            'place' => 'popup',
            'addUrl' => route(self::ROUTE.'add'),
            'cartUrl' => route('cart.show'),
            'checkoutUrl' => route('checkout.show'),
        ];
    }

    /**
     * Products added from checkout offers (ticked) first, then new suggestions.
     *
     * @return array<string, mixed>|null
     */
    private function bumpProps(mixed $priced, ModuleSettingsSchema $settings): ?array
    {
        if (! $priced instanceof PricedCart || $priced->isEmpty()) {
            return null;
        }

        $finder = $this->finder();
        $catalog = $this->app->make(StorefrontCatalog::class);
        $items = [];
        $cart = $this->cart();

        if ($cart !== null) {
            $rows = DB::table('cross_sell_cart_lines')->where('cart_id', $cart->id)->get(['variant_id', 'offer_id']);
            $offers = $finder->liveIn('checkout')->keyBy('id');

            foreach ($rows as $row) {
                $offer = $offers->get((int) $row->offer_id);
                $line = collect($priced->lines)->first(fn ($l) => $l->variant->id === (int) $row->variant_id);

                if (! $offer instanceof Offer || $line === null || count($items) >= self::BUMP_MAX) {
                    continue;
                }

                $card = $catalog->cardsFor([$line->variant->product_id], $priced->currency, 1)[0] ?? null;

                if ($card !== null) {
                    $items[] = [...$finder->withOffer($card, $offer), 'variant_id' => $line->variant->id, 'added' => true];
                }
            }
        }

        $products = $this->productsOf($priced);
        $exclude = array_map(fn (Product $p) => $p->id, $products);

        foreach ($finder->forProducts($products, 'checkout', $exclude, 8) as $item) {
            // A tick box needs one variant; products with options are skipped.
            if (count($items) < self::BUMP_MAX && $item['variant_id'] !== null) {
                $items[] = [...$item, 'added' => false];
            }
        }

        if ($items === []) {
            return null;
        }

        $this->countViews(array_filter($items, fn (array $i) => ! $i['added']), 'checkout');

        return [
            'heading' => (string) ($settings->of(self::MODULE, 'bump_heading', '') ?: st('Add to your order')),
            'items' => $items,
            'addUrl' => route(self::ROUTE.'add'),
            'removeUrl' => route(self::ROUTE.'remove'),
        ];
    }

    /**
     * @param  iterable<array<string, mixed>>  $items
     */
    private function countViews(iterable $items, string $place): void
    {
        $ids = [];
        foreach ($items as $item) {
            $ids[(int) $item['offer_id']] = true;
        }

        foreach (Offer::query()->whereIn('id', array_keys($ids))->get() as $offer) {
            $this->stats()->viewed($offer, $place);
        }
    }

    /**
     * @return list<Product>
     */
    private function productsOf(PricedCart $priced): array
    {
        $products = [];
        foreach ($priced->lines as $line) {
            $products[$line->variant->product_id] = $line->variant->product;
        }

        return array_values($products);
    }

    /**
     * Products in the shopper's cart (never suggested again).
     *
     * @return list<int>
     */
    private function cartProductIds(): array
    {
        $cart = $this->cart();

        return $cart === null ? [] : array_values(array_map('intval', DB::table('cart_items')
            ->join('product_variants', 'product_variants.id', '=', 'cart_items.variant_id')
            ->where('cart_items.cart_id', $cart->id)
            ->pluck('product_variants.product_id')
            ->all()));
    }

    private function cart(): ?Cart
    {
        if (! request()->hasSession()) {
            return null;
        }

        $user = request()->user();

        return $this->app->make(CartManager::class)->current(request()->session(), $user instanceof User ? $user : null);
    }

    private function limit(ModuleSettingsSchema $settings, string $key, int $default, int $max): int
    {
        return max(1, min($max, (int) $settings->of(self::MODULE, $key, $default)));
    }

    private function finder(): OfferFinder
    {
        return $this->app->make(OfferFinder::class);
    }

    private function stats(): OfferStats
    {
        return $this->app->make(OfferStats::class);
    }
}
