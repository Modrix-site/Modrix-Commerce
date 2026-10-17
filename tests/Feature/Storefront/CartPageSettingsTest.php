<?php

namespace Tests\Feature\Storefront;

use App\Core\Settings\SettingsRepository;
use App\Core\Themes\PageSettingFields;
use App\Domains\Catalog\ProductRelations;
use App\Domains\Shipping\Models\ShippingMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CommerceFixtures;
use Tests\TestCase;

class CartPageSettingsTest extends TestCase
{
    use CommerceFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        $this->enableCheckout(); // Standard: 5.00, free over 100.00
        config(['inertia.ssr.enabled' => false]);
    }

    private function pageSettings(array $values): void
    {
        app(SettingsRepository::class)->set(PageSettingFields::STORE_KEY, $values);
    }

    public function test_free_shipping_remaining()
    {
        $this->pageSettings(['cart.free_shipping_bar' => true]);
        $this->addToCart($this->sellable('30.00', 5), 2);

        $this->get(route('cart.show'))->assertInertia(fn (Assert $page) => $page
            ->where('freeShipping.threshold.amount', '100.00')
            ->where('freeShipping.remaining.amount', '40.00'));
    }

    public function test_free_shipping_reached()
    {
        $this->pageSettings(['cart.free_shipping_bar' => true]);
        $this->addToCart($this->sellable('60.00', 5), 2);

        $this->get(route('cart.show'))->assertInertia(fn (Assert $page) => $page->where('freeShipping.remaining.amount', '0.00'));
    }

    public function test_lowest_threshold_wins_and_none_means_no_bar()
    {
        $this->pageSettings(['cart.free_shipping_bar' => true]);
        $express = new ShippingMethod(['name' => 'Express', 'rate' => '15.00', 'currency' => 'USD', 'free_over' => '80.00', 'is_active' => true]);
        $express->code = 'express';
        $express->save();
        $this->addToCart($this->sellable('30.00', 5));

        $this->get(route('cart.show'))->assertInertia(fn (Assert $page) => $page->where('freeShipping.remaining.amount', '50.00'));

        ShippingMethod::query()->update(['free_over' => null]);
        $this->get(route('cart.show'))->assertInertia(fn (Assert $page) => $page->where('freeShipping', null));
    }

    public function test_cross_sells_exclude_cart_items()
    {
        $this->pageSettings(['cart.cross_sells' => true]);
        $a = $this->sellable('10.00', 5, product: ['name' => 'A']);
        $b = $this->sellable('10.00', 5, product: ['name' => 'B']);
        $c = $this->sellable('10.00', 5, product: ['name' => 'C']);
        app(ProductRelations::class)->sync($a->product, [$b->product_id, $c->product_id], []);
        $this->addToCart($a);
        $this->addToCart($c);

        $this->get(route('cart.show'))->assertInertia(fn (Assert $page) => $page
            ->has('crossSells', 1)
            ->where('crossSells.0.name', 'B'));
    }

    public function test_settings_off_send_nothing()
    {
        $a = $this->sellable('10.00', 5);
        $b = $this->sellable('10.00', 5);
        app(ProductRelations::class)->sync($a->product, [$b->product_id], []);
        $this->addToCart($a);

        $this->get(route('cart.show'))->assertInertia(fn (Assert $page) => $page
            ->where('freeShipping', null)
            ->where('crossSells', []));
    }
}
