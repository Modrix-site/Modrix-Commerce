<?php

namespace Tests\Feature\Checkout;

use App\Core\Settings\SettingsRepository;
use App\Core\Themes\PageSettingFields;
use App\Core\Themes\ResolvedTheme;
use App\Core\Themes\ThemeManager;
use App\Core\Themes\ThemeRepository;
use App\Core\Themes\ThemeResolver;
use App\Domains\Orders\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CommerceFixtures;
use Tests\Support\ThemeFixtures;
use Tests\TestCase;

class CheckoutPageSettingsTest extends TestCase
{
    use CommerceFixtures, RefreshDatabase, ThemeFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        $this->enableCheckout();
        config(['inertia.ssr.enabled' => false]);
        Mail::fake();
        $this->addToCart($this->sellable('20.00', 5));
    }

    private function pageSettings(array $values): void
    {
        app(SettingsRepository::class)->set(PageSettingFields::STORE_KEY, $values);
    }

    public function test_required_phone()
    {
        $this->pageSettings(['checkout.phone' => 'required']);

        $this->post(route('checkout.store'), $this->checkoutPayload(['phone' => '']))->assertSessionHasErrors('phone');
        $this->assertSame(0, Order::query()->count());

        $this->post(route('checkout.store'), $this->checkoutPayload())->assertSessionHasNoErrors();
        $this->assertSame('555-0100', Order::query()->firstOrFail()->phone);
    }

    public function test_hidden_fields_are_not_stored()
    {
        $this->pageSettings(['checkout.phone' => 'hidden', 'checkout.company' => false, 'checkout.address_line2' => false, 'checkout.order_note' => false]);

        $this->post(route('checkout.store'), $this->checkoutPayload([
            'customer_note' => 'Leave at the door',
            'shipping' => ['company' => 'Acme', 'line2' => 'Unit 4', 'phone' => '555-0199'],
        ]))->assertSessionHasNoErrors();

        $order = Order::query()->with('addresses')->firstOrFail();
        $shipping = $order->addresses->firstWhere('type', 'shipping');
        $this->assertNull($order->phone);
        $this->assertNull($order->customer_note);
        $this->assertEmpty($shipping->company);
        $this->assertEmpty($shipping->line2);
        $this->assertEmpty($shipping->phone);
    }

    public function test_terms_must_be_accepted()
    {
        $this->pageSettings(['checkout.terms' => true]);

        $this->post(route('checkout.store'), $this->checkoutPayload())
            ->assertSessionHasErrors(['terms' => 'Please accept the terms to place your order.']);
        $this->assertSame(0, Order::query()->count());

        $this->post(route('checkout.store'), $this->checkoutPayload(['terms' => '1']))->assertSessionHasNoErrors();
        $this->assertSame(1, Order::query()->count());
    }

    public function test_rules_apply_with_the_builtin_theme()
    {
        // No theme package at all: the store falls back to the built-in theme.
        $this->setUpThemes();
        $this->assertSame(ResolvedTheme::SOURCE_BUILTIN, app(ThemeResolver::class)->forRequest(request())->source);
        $this->pageSettings(['checkout.phone' => 'required', 'checkout.order_note' => false]);

        $this->post(route('checkout.store'), $this->checkoutPayload(['phone' => '']))->assertSessionHasErrors('phone');
        $this->post(route('checkout.store'), $this->checkoutPayload(['customer_note' => 'Hi']))->assertSessionHasNoErrors();
        $this->assertNull(Order::query()->firstOrFail()->customer_note);
    }

    public function test_defaults_change_nothing()
    {
        $this->post(route('checkout.store'), $this->checkoutPayload(['customer_note' => 'Hi', 'shipping' => ['company' => 'Acme']]))->assertSessionHasNoErrors();

        $order = Order::query()->with('addresses')->firstOrFail();
        $this->assertSame('555-0100', $order->phone);
        $this->assertSame('Hi', $order->customer_note);
        $this->assertSame('Acme', $order->addresses->firstWhere('type', 'shipping')->company);
    }

    public function test_checkout_page_gets_its_settings()
    {
        $this->pageSettings(['checkout.button_label' => 'Pay now']);

        $this->get(route('checkout.show'))->assertInertia(fn (Assert $page) => $page
            ->where('pageCheckout.button_label', 'Pay now')
            ->where('termsUrl', null));
    }

    public function test_terms_accepts_the_checkbox_value_the_page_sends()
    {
        $this->pageSettings(['checkout.terms' => true]);

        $this->post(route('checkout.store'), $this->checkoutPayload(['terms' => true]))->assertSessionHasNoErrors();
        $this->assertSame(1, Order::query()->count());
    }

    public function test_hidden_billing_fields_are_not_stored()
    {
        $this->pageSettings(['checkout.company' => false, 'checkout.address_line2' => false]);

        $this->post(route('checkout.store'), $this->checkoutPayload([
            'billing_same' => false,
            'billing' => ['first_name' => 'Bea', 'last_name' => 'Cruz', 'company' => 'Acme', 'line1' => '2 Side St', 'line2' => 'Unit 9', 'city' => 'Springfield', 'region' => 'IL', 'postal_code' => '62701', 'country_code' => 'US'],
        ]))->assertSessionHasNoErrors();

        $billing = Order::query()->with('addresses')->firstOrFail()->addresses->firstWhere('type', 'billing');
        $this->assertEmpty($billing->company);
        $this->assertEmpty($billing->line2);
    }

    public function test_theme_defaults_cannot_change_checkout_rules()
    {
        // A theme package that ships checkout page settings as design defaults.
        $this->setUpThemes();
        $this->makeTheme('acme/strict', ['design' => ['color.primary' => '#123456', 'checkout.terms' => true, 'checkout.phone' => 'required']]);
        app(ThemeRepository::class)->sync();
        app(ThemeManager::class)->activate('acme/strict');

        $this->post(route('checkout.store'), $this->checkoutPayload(['phone' => '']))->assertSessionHasNoErrors();
        $this->assertSame(1, Order::query()->count());
    }
}
