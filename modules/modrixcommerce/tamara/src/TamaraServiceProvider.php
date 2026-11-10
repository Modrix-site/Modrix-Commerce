<?php

namespace EcoModular\Tamara;

use App\Core\Extensions\StorefrontSlots;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Checkout\Pricing\PricedCart;
use App\Domains\Payments\PaymentMethods;
use App\Domains\Pricing\Money;
use Illuminate\Support\ServiceProvider;

/**
 * Registers Tamara as a payment gateway and places its messaging in the
 * storefront: the product page, the cart total and the Tamara payment method
 * at checkout. Every snippet is hidden unless Tamara is ready to take payments
 * for the store currency and the amount.
 */
class TamaraServiceProvider extends ServiceProvider
{
    public const MODULE = 'modrixcommerce/tamara';

    public function register(): void
    {
        $this->app->singleton(TamaraSettings::class);
        $this->app->singleton(TamaraGateway::class);
    }

    public function boot(PaymentMethods $methods, StorefrontSlots $slots): void
    {
        $methods->register($this->app->make(TamaraGateway::class));

        $slots->block(self::MODULE, 'promo', 'Tamara instalments', 'TamaraPromo', ['product.after_details', 'product.after_price', 'product.after_add_to_cart'], 'product.after_details', function (array $context) {
            $product = $context['product'] ?? null;

            return $product instanceof Product && $this->settings()->option('product_snippet', true)
                ? $this->snippet($this->lowestPrice($product), 'product')
                : null;
        }, order: 10);

        $slots->register('cart.after_totals', self::MODULE, 'TamaraPromo', function (array $context) {
            $cart = $context['cart'] ?? null;

            return $cart instanceof PricedCart && $this->settings()->option('cart_snippet', true)
                ? $this->snippet($cart->grandTotal(), 'cart')
                : null;
        });

        $slots->register('checkout.payment_method', self::MODULE, 'TamaraCheckout', function (array $context) {
            $cart = $context['cart'] ?? null;
            $props = $cart instanceof PricedCart ? $this->snippet($cart->grandTotal(), 'checkout') : null;

            return $props === null ? null : [
                ...$props,
                'method' => TamaraGateway::CODE,
                'showCard' => (bool) $this->settings()->option('checkout_card', true),
                'eligibilityUrl' => $this->settings()->option('eligibility_check', true) ? route('modrixcommerce.tamara.eligibility') : null,
            ];
        });
    }

    /**
     * Props shared by every snippet: only public values (the schedule of
     * instalments, which is derived from the price and the currency).
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
            'lang' => TamaraCopy::lang(),
            'instalments' => 4,
            'schedule' => $this->schedule($price),
            'learnMoreUrl' => 'https://tamara.co/'.TamaraCopy::lang(),
            'inheritBackground' => (bool) $settings->option('inherit_background', false),
        ];
    }

    /**
     * The four-payment schedule: an equal share today, then every 30 days.
     *
     * @return list<array{label: string, amount: string}>
     */
    private function schedule(Money $price): array
    {
        $share = $price->multipliedBy('0.25')->rounded();
        $labels = TamaraCopy::lang() === 'ar'
            ? ['اليوم', 'بعد 30 يومًا', 'بعد 60 يومًا', 'بعد 90 يومًا']
            : ['Today', 'In 30 days', 'In 60 days', 'In 90 days'];

        return array_map(fn (string $label) => ['label' => $label, 'amount' => $share->toDisplayDecimal()], $labels);
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

    private function settings(): TamaraSettings
    {
        return $this->app->make(TamaraSettings::class);
    }
}
