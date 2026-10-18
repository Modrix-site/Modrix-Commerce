<?php

namespace Tests\Feature\Orders;

use App\Core\Settings\SettingsRepository;
use App\Core\Settings\StoreSettings;
use App\Domains\Orders\Documents\DocumentSettings;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\OrderEmailSettings;
use App\Domains\Orders\OrderNumbers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Support\CommerceFixtures;
use Tests\TestCase;

class OrderNumberSettingsTest extends TestCase
{
    use CommerceFixtures,RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        $this->enableCheckout();
        $this->withoutVite();
        Mail::fake();
    }

    private function settings(): array
    {
        return [...app(DocumentSettings::class)->toArray(), 'order_prefix' => 'ORD-{year}-', 'order_padding' => 5, 'next_order_number' => 100];
    }

    public function test_prefix_and_starting_number_apply_to_new_orders_only(): void
    {
        $variant = $this->sellable('10', 10);
        $this->addToCart($variant);
        $this->post(route('checkout.store'), $this->checkoutPayload())->assertSessionHasNoErrors();
        $old = Order::firstOrFail();
        $this->actingAs($this->userWithRole('administrator'))->put(route('admin.settings.documents.update'), $this->settings())->assertSessionHasNoErrors();
        $this->addToCart($variant);
        $this->post(route('checkout.store'), $this->checkoutPayload())->assertSessionHasNoErrors();
        $this->assertSame('ORD-'.now()->format('Y').'-00100', Order::latest('id')->firstOrFail()->number);
        $this->assertSame('EM-000001', $old->refresh()->number);
        $this->assertSame(101, app(OrderNumbers::class)->toArray()['next_order_number']);
        $this->put(route('admin.settings.documents.update'), [...$this->settings(), 'loaded_next_order_number' => 100])->assertSessionHasNoErrors();
        $this->assertSame(101, app(OrderNumbers::class)->toArray()['next_order_number']);
    }

    public function test_store_settings_saves_general_and_document_preferences_together(): void
    {
        $this->actingAs($this->userWithRole('administrator'))->put(route('admin.settings.store.update'), [
            ...$this->settings(),
            'store_name' => 'Unified Store', 'default_currency' => 'USD',
            'timezone' => 'UTC', 'weight_unit' => 'kg', 'dimension_unit' => 'cm',
            'emails' => ['shipped' => false], 'tax_id' => 'VAT-123', 'seller_address' => '123 Business Street',
        ])->assertSessionHasNoErrors();
        $this->assertSame('Unified Store', app(StoreSettings::class)->name());
        $this->assertSame('ORD-{year}-', app(OrderNumbers::class)->toArray()['order_prefix']);
        $this->assertSame(100, app(OrderNumbers::class)->toArray()['next_order_number']);
        $this->assertSame('VAT-123', app(DocumentSettings::class)->toArray()['tax_id']);
        $this->assertSame('123 Business Street', app(DocumentSettings::class)->toArray()['seller_address']);
        $this->assertSame('123 Business Street', app(SettingsRepository::class)->get('store.address'));
        $this->assertFalse(app(OrderEmailSettings::class)->all()['shipped']);
    }

    public function test_counter_cannot_move_back_and_shared_save_is_atomic(): void
    {
        app(OrderNumbers::class)->update(['next_order_number' => 100]);
        $this->actingAs($this->userWithRole('administrator'));
        $this->put(route('admin.settings.store.update'), [...$this->settings(), 'next_order_number' => 2, 'store_name' => 'Should not save', 'default_currency' => 'USD', 'timezone' => 'UTC', 'weight_unit' => 'kg', 'dimension_unit' => 'cm', 'emails' => ['shipped' => false]])->assertSessionHasErrors('next_order_number');
        $this->assertNotSame('Should not save', app(StoreSettings::class)->name());
        $this->assertTrue(app(OrderEmailSettings::class)->all()['shipped']);
        $this->put(route('admin.settings.documents.update'), [...$this->settings(), 'order_prefix' => 'bad/prefix'])->assertSessionHasErrors('order_prefix');
    }

    public function test_a_changed_prefix_cannot_reuse_an_existing_order_number(): void
    {
        $numbers = app(OrderNumbers::class);
        $numbers->update(['order_prefix' => 'A100', 'order_padding' => 3]);
        $this->addToCart($this->sellable());
        $this->post(route('checkout.store'), $this->checkoutPayload())->assertSessionHasNoErrors();
        $this->assertSame('A100001', Order::firstOrFail()->number);
        $numbers->update(['order_prefix' => 'A', 'order_padding' => 6, 'next_order_number' => 100001]);
        $number = DB::transaction(fn () => $numbers->take());
        $this->assertSame('A100002', $number);
    }

    public function test_counter_rolls_back_and_legacy_url_redirects_to_tab(): void
    {
        $numbers = app(OrderNumbers::class);
        $before = $numbers->toArray()['next_order_number'];
        DB::beginTransaction();
        $a = $numbers->take();
        DB::rollBack();
        $this->assertSame($before, $numbers->toArray()['next_order_number']);
        $b = DB::transaction(fn () => $numbers->take());
        $this->assertSame($a, $b);
        $this->actingAs($this->userWithRole('administrator'))->get(route('admin.settings.documents.edit'))->assertRedirect('/admin/settings/store#documents');
    }
}
