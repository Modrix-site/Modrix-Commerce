<?php

namespace Tests\Feature\Admin;

use App\Core\Settings\SettingsRepository;
use App\Domains\Shipping\Models\ShippingMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CommerceFixtures;
use Tests\TestCase;

/**
 * The dashboard's first-use setup guide: steps tick themselves off from the
 * store's state, optional ones can be skipped, and the guide can be hidden.
 */
class SetupGuideTest extends TestCase
{
    use CommerceFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['inertia.ssr.enabled' => false]);
        $this->installPlatform();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function steps(): array
    {
        $guide = $this->get(route('admin.dashboard'))->viewData('page')['props']['setupGuide'];

        return collect($guide['steps'])->keyBy('key')->all();
    }

    public function test_a_new_store_sees_the_steps_in_order_with_links()
    {
        $this->actingAs($this->owner())->get(route('admin.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('setupGuide.hidden', false)
                ->where('setupGuide.steps.0.key', 'store')
                ->where('setupGuide.steps.0.url', route('admin.settings.store.edit'))
                ->where('setupGuide.steps.1.key', 'theme'));

        $steps = $this->steps();
        $this->assertSame(['store', 'theme', 'modules', 'product', 'payments', 'shipping', 'taxes', 'email'], array_keys($steps));
        $this->assertFalse($steps['product']['done']);
        $this->assertSame(route('admin.products.create'), $steps['product']['url']);
        $this->assertTrue($steps['shipping']['optional']);
        $this->assertFalse($steps['payments']['optional']);
    }

    public function test_steps_tick_themselves_off_from_the_store_state()
    {
        $this->actingAs($this->owner());
        $this->enableCheckout();
        $this->sellable();
        $this->taxRate('US', '10');
        app(SettingsRepository::class)->setMany(['store.country' => 'US']);
        $this->put(route('admin.settings.store.update'), [
            'store_name' => 'Acme', 'store_email' => 'hello@acme.test', 'default_currency' => 'USD',
            'timezone' => 'UTC', 'weight_unit' => 'kg', 'dimension_unit' => 'cm',
        ])->assertSessionHasNoErrors();
        app(SettingsRepository::class)->set('theme.modrixcommerce.circuit.design', ['color.primary' => '#123456']);

        $steps = $this->steps();
        foreach (['store', 'theme', 'product', 'payments', 'shipping', 'taxes'] as $key) {
            $this->assertTrue($steps[$key]['done'], "$key should be done");
        }
        $this->assertFalse($steps['modules']['done']);

        $this->get(route('admin.dashboard'))->assertInertia(fn (Assert $page) => $page->where('setupGuide.completed', 6));
    }

    public function test_optional_steps_can_be_skipped_and_restored()
    {
        $this->actingAs($this->owner());

        $this->put(route('admin.setup-guide.skip', 'shipping'), ['skipped' => true])->assertRedirect();
        $this->assertTrue($this->steps()['shipping']['skipped']);
        $this->get(route('admin.dashboard'))->assertInertia(fn (Assert $page) => $page->where('setupGuide.completed', 1));

        $this->put(route('admin.setup-guide.skip', 'shipping'), ['skipped' => false])->assertRedirect();
        $this->assertFalse($this->steps()['shipping']['skipped']);
    }

    public function test_required_or_unknown_steps_cannot_be_skipped()
    {
        $this->actingAs($this->owner());

        $this->put(route('admin.setup-guide.skip', 'product'), ['skipped' => true])->assertNotFound();
        $this->put(route('admin.setup-guide.skip', 'nonsense'), ['skipped' => true])->assertNotFound();
        $this->put(route('admin.setup-guide.skip', 'taxes'), ['skipped' => 'maybe'])->assertSessionHasErrors('skipped');
    }

    public function test_the_guide_can_be_hidden_and_shown_again()
    {
        $this->actingAs($this->owner());

        $this->put(route('admin.setup-guide.visibility'), ['hidden' => true])->assertRedirect();
        $this->get(route('admin.dashboard'))->assertInertia(fn (Assert $page) => $page->where('setupGuide.hidden', true));

        $this->put(route('admin.setup-guide.visibility'), ['hidden' => false])->assertRedirect();
        $this->get(route('admin.dashboard'))->assertInertia(fn (Assert $page) => $page->where('setupGuide.hidden', false));
    }

    public function test_staff_who_do_not_manage_settings_neither_see_nor_change_it()
    {
        $agent = $this->userWithRole('support-agent');

        $this->actingAs($agent)->get(route('admin.dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('setupGuide', null));
        $this->put(route('admin.setup-guide.visibility'), ['hidden' => true])->assertForbidden();
        $this->put(route('admin.setup-guide.skip', 'shipping'), ['skipped' => true])->assertForbidden();
    }

    public function test_an_inactive_shipping_method_does_not_count()
    {
        $this->actingAs($this->owner());
        $method = new ShippingMethod(['name' => 'Old', 'rate' => '5.00', 'currency' => 'USD', 'countries' => ['US'], 'is_active' => false]);
        $method->code = 'old';
        $method->save();

        $this->assertFalse($this->steps()['shipping']['done']);
    }
}
