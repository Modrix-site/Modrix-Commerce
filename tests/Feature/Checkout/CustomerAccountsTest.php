<?php

namespace Tests\Feature\Checkout;

use App\Core\Settings\SettingsRepository;
use App\Domains\Orders\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CommerceFixtures;
use Tests\TestCase;

/**
 * Store settings → General → Customer accounts: guests may order, or an
 * account is required before checkout.
 */
class CustomerAccountsTest extends TestCase
{
    use CommerceFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        $this->enableCheckout();
        Mail::fake();
    }

    /**
     * @return array<string, string>
     */
    private function storeSettings(array $overrides = []): array
    {
        return array_merge([
            'store_name' => 'Acme Goods',
            'store_email' => 'hello@acme.test',
            'default_currency' => 'USD',
            'timezone' => 'UTC',
            'weight_unit' => 'kg',
            'dimension_unit' => 'cm',
        ], $overrides);
    }

    private function guestsAllowed(): bool
    {
        return (bool) app(SettingsRepository::class)->get('checkout.guest_enabled', true);
    }

    public function test_store_settings_show_and_save_the_choice_with_an_audit_entry()
    {
        $owner = $this->owner();

        $this->actingAs($owner)->get(route('admin.settings.store.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('customerAccounts', 'optional')
                ->where('registrationEnabled', true));

        $this->actingAs($owner)->put(route('admin.settings.store.update'), $this->storeSettings(['customer_accounts' => 'required']))
            ->assertSessionHasNoErrors();

        $this->assertFalse($this->guestsAllowed());
        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.store_updated', 'actor_id' => $owner->id]);
        $this->actingAs($owner)->get(route('admin.settings.store.edit'))
            ->assertInertia(fn (Assert $page) => $page->where('customerAccounts', 'required'));

        $this->actingAs($owner)->put(route('admin.settings.store.update'), $this->storeSettings(['customer_accounts' => 'optional']))
            ->assertSessionHasNoErrors();
        $this->assertTrue($this->guestsAllowed());
    }

    public function test_unknown_values_are_rejected_and_omitting_the_field_keeps_the_choice()
    {
        $owner = $this->owner();
        app(SettingsRepository::class)->set('checkout.guest_enabled', false);

        $this->actingAs($owner)->put(route('admin.settings.store.update'), $this->storeSettings(['customer_accounts' => 'sometimes']))
            ->assertSessionHasErrors('customer_accounts');
        $this->actingAs($owner)->put(route('admin.settings.store.update'), $this->storeSettings())
            ->assertSessionHasNoErrors();

        $this->assertFalse($this->guestsAllowed());
    }

    public function test_saving_payment_settings_no_longer_changes_the_choice()
    {
        app(SettingsRepository::class)->set('checkout.guest_enabled', false);

        $this->actingAs($this->userWithRole('administrator'))->put(route('admin.payments.settings.update'), [
            'methods' => [
                'bank_transfer' => ['enabled' => '1', 'label' => 'Bank deposit', 'instructions' => 'Pay to account 123.'],
                'cash_on_delivery' => ['label' => 'COD', 'instructions' => 'Pay the courier.'],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertFalse($this->guestsAllowed());
    }

    public function test_staff_without_settings_access_cannot_change_it()
    {
        $this->actingAs($this->userWithRole('support-agent'))
            ->put(route('admin.settings.store.update'), $this->storeSettings(['customer_accounts' => 'required']))
            ->assertForbidden();

        $this->assertTrue($this->guestsAllowed());
    }

    public function test_guests_can_order_when_accounts_are_optional()
    {
        $this->addToCart($this->sellable());

        $this->get(route('checkout.show'))->assertOk();
        $this->post(route('checkout.store'), $this->checkoutPayload())->assertSessionHasNoErrors();

        $this->assertNull(Order::query()->firstOrFail()->user_id);
    }

    public function test_a_guest_signs_up_before_checkout_when_an_account_is_required_and_keeps_the_cart()
    {
        app(SettingsRepository::class)->set('checkout.guest_enabled', false);
        $this->addToCart($this->sellable('15.00'));

        // Browsing and the cart stay open; checkout asks the shopper to sign in.
        $this->get(route('cart.show'))->assertOk();
        $this->get(route('checkout.show'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('login_reason', 'checkout');
        $this->get(route('login'))->assertInertia(fn (Assert $page) => $page->where('reason', 'checkout'));
        $this->post(route('checkout.store'), $this->checkoutPayload())->assertSessionHasErrors('email');
        $this->assertSame(0, Order::query()->count());

        // Creating an account returns them to checkout with the same cart.
        $this->post(route('register.store'), [
            'name' => 'Ana Cruz',
            'email' => 'ana@example.com',
            'password' => 'a-Very-strong-passphrase-42',
            'password_confirmation' => 'a-Very-strong-passphrase-42',
        ])->assertRedirect(route('checkout.show'));

        $this->get(route('checkout.show'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('summary.lines.0.subtotal.amount', '15.00'));
        $this->post(route('checkout.store'), $this->checkoutPayload(['email' => 'ana@example.com']))->assertSessionHasNoErrors();

        $this->assertNotNull(Order::query()->firstOrFail()->user_id);
    }

    public function test_the_admin_page_knows_when_sign_up_is_off()
    {
        app(SettingsRepository::class)->set('general.security', ['registration_enabled' => false]);

        $this->actingAs($this->owner())->get(route('admin.settings.store.edit'))
            ->assertInertia(fn (Assert $page) => $page->where('registrationEnabled', false));
    }
}
