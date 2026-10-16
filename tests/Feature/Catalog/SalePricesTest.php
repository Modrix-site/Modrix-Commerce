<?php

namespace Tests\Feature\Catalog;

use App\Core\Settings\StoreSettings;
use App\Domains\Catalog\Pricing\SaleOffer;
use App\Domains\Catalog\Pricing\SalePriceProvider;
use App\Domains\Catalog\Pricing\SalePrices;
use App\Domains\Catalog\StorefrontCatalog;
use App\Domains\Orders\Models\Order;
use App\Domains\Pricing\Money;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\Support\CommerceFixtures;
use Tests\TestCase;

/**
 * The core "catalog.sale_prices" extension point, driven by a fake provider:
 * the shown price, the cart price and the order price always agree.
 */
class SalePricesTest extends TestCase
{
    use CommerceFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        Mail::fake();
    }

    /**
     * @param  array<int, SaleOffer>  $offers
     */
    private function provide(array $offers): void
    {
        app(SalePrices::class)->addProvider(new class($offers) implements SalePriceProvider
        {
            public function __construct(private array $offers) {}

            public function offers(array $productIds, CarbonInterface $now): array
            {
                return array_intersect_key($this->offers, array_flip($productIds));
            }
        });
    }

    private function currency(): string
    {
        return app(StoreSettings::class)->currency();
    }

    public function test_cards_and_product_pages_show_the_sale_price_with_the_original_crossed_out()
    {
        $variant = $this->sellable('40.00', 5);
        $this->provide([$variant->product_id => new SaleOffer(SaleOffer::PERCENT, '25', 'Weekend sale', now()->addDay())]);

        $card = app(StorefrontCatalog::class)->card($variant->product()->with(['variants.prices', 'images'])->firstOrFail(), $this->currency());
        $this->assertSame('30.00', $card['price']->toDisplayDecimal());
        $this->assertSame('40.00', $card['compare_at_price']->toDisplayDecimal());
        $this->assertSame('−25%', $card['sale']['badge']);
        $this->assertNotNull($card['sale']['ends_at']);

        $this->get(route('storefront.product', $variant->product->slug))->assertInertia(fn (Assert $page) => $page
            ->where('product.variants.0.price.amount', '30.00')
            ->where('product.variants.0.compare_at_price.amount', '40.00')
            ->where('product.sale.badge', '−25%')
            ->where('product.sale.name', 'Weekend sale'));
    }

    public function test_the_cart_and_the_order_charge_the_sale_price_and_coupons_apply_on_top()
    {
        $this->enableCheckout();
        $variant = $this->sellable('40.00', 5);
        $this->provide([$variant->product_id => new SaleOffer(SaleOffer::FIXED, '10', 'Flash', null, $this->currency())]);
        $coupon = $this->coupon(['value' => '10']);

        $this->addToCart($variant, 2);
        $this->post(route('cart.coupon.apply'), ['code' => $coupon->code])->assertSessionHasNoErrors();
        $this->post(route('checkout.store'), $this->checkoutPayload())->assertSessionHasNoErrors();

        $order = Order::query()->latest('id')->firstOrFail();
        $item = $order->items()->firstOrFail();
        $this->assertSame('30.0000', $item->unit_price);
        $this->assertSame('60.0000', $order->subtotal);
        // 10% coupon on the sale price.
        $this->assertSame('6.0000', $order->discount_total);
    }

    public function test_fixed_amounts_only_apply_to_their_currency_and_never_go_below_zero()
    {
        $prices = app(SalePrices::class);
        $this->provide([
            1 => new SaleOffer(SaleOffer::FIXED, '5', 'Flash', null, 'EUR'),
            2 => new SaleOffer(SaleOffer::FIXED, '500', 'Flash', null, 'USD'),
        ]);

        $this->assertNull($prices->apply(1, Money::of('20', 'USD')));
        $this->assertSame('0.00', $prices->apply(2, Money::of('20', 'USD'))['price']->toDisplayDecimal());
    }

    public function test_the_best_offer_wins_and_a_failing_provider_is_ignored()
    {
        $this->provide([1 => new SaleOffer(SaleOffer::PERCENT, '10', 'Small', null)]);
        $this->provide([1 => new SaleOffer(SaleOffer::PERCENT, '30', 'Big', null)]);
        app(SalePrices::class)->addProvider(new class implements SalePriceProvider
        {
            public function offers(array $productIds, CarbonInterface $now): array
            {
                throw new RuntimeException('broken module');
            }
        });

        $best = app(SalePrices::class)->apply(1, Money::of('100', 'USD'));
        $this->assertSame('Big', $best['offer']->name);
        $this->assertSame('70.00', $best['price']->toDisplayDecimal());
    }

    public function test_without_providers_prices_are_unchanged()
    {
        $variant = $this->sellable('40.00', 5);

        $card = app(StorefrontCatalog::class)->card($variant->product()->with(['variants.prices', 'images'])->firstOrFail(), $this->currency());

        $this->assertSame('40.00', $card['price']->toDisplayDecimal());
        $this->assertNull($card['sale']);
    }
}
