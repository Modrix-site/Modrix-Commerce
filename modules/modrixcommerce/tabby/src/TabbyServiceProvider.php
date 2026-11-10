<?php

namespace EcoModular\Tabby;

use App\Core\Extensions\StorefrontSlots;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Checkout\Pricing\PricedCart;
use App\Domains\Payments\PaymentMethods;
use App\Domains\Pricing\Money;
use Illuminate\Support\ServiceProvider;

/**
 * Registers Tabby as a payment gateway and places its snippets in the
 * storefront: product page, cart total and the Tabby payment method at
 * checkout. Every snippet is hidden unless Tabby is ready to take payments
 * for the store currency and the amount.
 */
class TabbyServiceProvider extends ServiceProvider
{
    public const MODULE = 'modrixcommerce/tabby';

    public function register(): void
    {
        $this->app->singleton(TabbySettings::class);
        $this->app->singleton(TabbyGateway::class);
    }

    public function boot(PaymentMethods $methods, StorefrontSlots $slots): void
    {
        $methods->register($this->app->make(TabbyGateway::class));

        $slots->block(self::MODULE, 'promo', 'Tabby instalments', 'TabbyPromo', ['product.after_details', 'product.after_price', 'product.after_add_to_cart'], 'product.after_details', function (array $context) {
            $product = $context['product'] ?? null;

            return $product instanceof Product && $this->settings()->option('product_snippet', true)
                ? $this->snippet($this->lowestPrice($product), 'product')
                : null;
        }, order: 10);

        $slots->register('cart.after_totals', self::MODULE, 'TabbyPromo', function (array $context) {
            $cart = $context['cart'] ?? null;

            return $cart instanceof PricedCart && $this->settings()->option('cart_snippet', true)
                ? $this->snippet($cart->grandTotal(), 'cart')
                : null;
        });

        $slots->register('checkout.payment_method', self::MODULE, 'TabbyCheckout', function (array $context) {
            $cart = $context['cart'] ?? null;
            $props = $cart instanceof PricedCart ? $this->snippet($cart->grandTotal(), 'checkout') : null;

            return $props === null ? null : [
                ...$props,
                'method' => TabbyGateway::CODE,
                'showCard' => (bool) $this->settings()->option('checkout_card', true),
                'eligibilityUrl' => $this->settings()->option('eligibility_check', true) ? route('modrixcommerce.tabby.eligibility') : null,
            ];
        });
    }

    /**
     * Props shared by every snippet: only public values (public key, merchant code).
     *
     * @return array<string, mixed>|null
     */
    private function snippet(?Money $price, string $source): ?array
    {
        $settings = $this->settings();
        $market = $settings->market();

        if ($price === null || $market === null || ! $settings->ready() || $price->currency() !== $market->currency || ! $settings->allowsAmount($price)) {
            return null;
        }

        return [
            'source' => $source,
            'price' => $price->toDisplayDecimal(),
            'currency' => $market->currency,
            'lang' => TabbyCopy::lang(),
            'publicKey' => $settings->publicKey(),
            'merchantCode' => $settings->merchantCode(),
            'inheritBackground' => (bool) $settings->option('inherit_background', false),
            'popupUrl' => $market->checkoutHost().'/promos/product-page/installments/'.TabbyCopy::lang().'/?'.http_build_query([
                'price' => $price->toDisplayDecimal(),
                'currency' => $market->currency,
                'merchant_code' => $settings->merchantCode(),
                'public_key' => $settings->publicKey(),
            ]),
        ];
    }

    private function lowestPrice(Product $product): ?Money
    {
        $currency = $this->settings()->market()?->currency;

        if ($currency === null) {
            return null;
        }

        return $product->variants
            ->map(fn (ProductVariant $variant) => $variant->priceIn($currency))
            ->filter()
            ->sort(fn (Money $a, Money $b) => $a->compareTo($b))
            ->first();
    }

    private function settings(): TabbySettings
    {
        return $this->app->make(TabbySettings::class);
    }
}
