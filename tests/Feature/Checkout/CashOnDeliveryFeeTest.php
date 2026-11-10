<?php

namespace Tests\Feature\Checkout;

use App\Core\Settings\SettingsRepository;
use App\Domains\Orders\Documents\OrderDocuments;
use App\Domains\Orders\Mail\OrderMail;
use App\Domains\Orders\Models\Order;
use App\Domains\Payments\PaymentMethods;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CommerceFixtures;
use Tests\TestCase;

class CashOnDeliveryFeeTest extends TestCase
{
    use CommerceFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        $this->enableCheckout();
        Mail::fake();
    }

    private function enableFee(): void
    {
        app(SettingsRepository::class)->setMany([
            'payments.cash_on_delivery.fee_enabled' => true,
            'payments.cash_on_delivery.fee_amount' => '3.50',
            'payments.cash_on_delivery.fee_currency' => 'USD',
        ]);
    }

    public function test_quote_adds_the_fee_only_for_cash_on_delivery_and_removes_it_when_switching_back(): void
    {
        $this->enableFee();
        $this->taxRate('US', '10');
        $this->addToCart($this->sellable('20.00'));

        foreach ([['bank_transfer', '27.00'], ['cash_on_delivery', '30.50'], ['bank_transfer', '27.00']] as [$method, $total]) {
            $this->get(route('checkout.show', ['country' => 'US', 'region' => 'IL', 'shipping_method' => 'standard', 'payment_method' => $method]))
                ->assertInertia(fn (Assert $page) => $page
                    ->where('summary.payment_method', $method)
                    ->where('summary.payment_fee_total.amount', $method === 'cash_on_delivery' ? '3.50' : '0.00')
                    ->where('summary.grand_total.amount', $total)
                    ->where('paymentMethods.1.fee.amount', '3.50'));
        }
    }

    public function test_checkout_reprices_the_fee_and_refuses_a_stale_total(): void
    {
        $this->enableFee();
        $this->taxRate('US', '10');
        $this->addToCart($this->sellable('20.00'));
        $payload = $this->checkoutPayload(['payment_method' => 'cash_on_delivery', 'payment_fee_total' => '0', 'expected_total' => '27.00']);

        $this->post(route('checkout.store'), $payload)->assertSessionHasErrors('total');
        $this->assertDatabaseCount('orders', 0);

        $this->post(route('checkout.store'), [...$payload, 'expected_total' => '30.50'])->assertSessionHasNoErrors();
        $order = Order::query()->firstOrFail();
        $this->assertSame('3.5000', $order->payment_fee_total);
        $this->assertSame('30.5000', $order->grand_total);
        $this->assertSame('30.5000', $order->balanceDue()->toDecimal());
    }

    public function test_fee_is_snapshotted_in_confirmation_email_invoice_and_export(): void
    {
        $this->enableFee();
        $this->addToCart($this->sellable('20.00'));
        $this->post(route('checkout.store'), $this->checkoutPayload(['payment_method' => 'cash_on_delivery']))->assertSessionHasNoErrors();
        $order = Order::query()->firstOrFail();

        app(SettingsRepository::class)->setMany([
            'payments.cash_on_delivery.fee_enabled' => false,
            'payments.cash_on_delivery.fee_amount' => '99.00',
        ]);

        $this->get(route('checkout.complete', $order))->assertInertia(fn (Assert $page) => $page
            ->where('order.payment_fee_total.amount', '3.50')
            ->where('order.grand_total.amount', '28.50'));
        $html = (new OrderMail($order, 'placed'))->render();
        $this->assertStringContainsString('Cash on delivery fee (collected by the courier)', $html);
        $this->assertStringContainsString($order->money('payment_fee_total')->format(), $html);

        $invoice = app(OrderDocuments::class)->issueInvoice($order);
        $this->assertSame('3.5000', $invoice->totals['payment_fee_total']);
        $this->assertSame('28.5000', $invoice->totals['grand_total']);

        $response = $this->actingAs($this->userWithRole('order-manager'))->get(route('admin.orders.export'));
        $rows = array_map('str_getcsv', array_filter(explode("\n", ltrim((string) $response->streamedContent(), "\u{FEFF}"))));
        $this->assertSame('3.50', array_combine($rows[0], $rows[1])['payment_fee']);
    }

    public function test_fee_is_off_by_default_and_other_payment_methods_do_not_pay_it(): void
    {
        $this->addToCart($this->sellable('20.00'));
        $this->post(route('checkout.store'), $this->checkoutPayload(['payment_method' => 'cash_on_delivery']))->assertSessionHasNoErrors();
        $this->assertSame('0.0000', Order::query()->firstOrFail()->payment_fee_total);

        $this->enableFee();
        $this->addToCart($this->sellable('20.00'));
        $this->post(route('checkout.store'), $this->checkoutPayload())->assertSessionHasNoErrors();
        $this->assertSame('0.0000', Order::query()->latest('id')->firstOrFail()->payment_fee_total);
    }

    public function test_digital_only_orders_have_no_courier_fee(): void
    {
        $this->enableFee();
        $this->addToCart($this->sellable('9.00', null, ['track_inventory' => false], ['is_digital' => true]));
        $this->post(route('checkout.store'), $this->checkoutPayload(['payment_method' => 'cash_on_delivery', 'shipping_method' => 'digital']))->assertSessionHasNoErrors();

        $order = Order::query()->firstOrFail();
        $this->assertSame('0.0000', $order->payment_fee_total);
        $this->assertSame('9.0000', $order->grand_total);
    }

    public function test_discount_does_not_reduce_the_courier_fee(): void
    {
        $this->enableFee();
        $coupon = $this->coupon(['code' => 'HALF', 'value' => '50']);
        $this->addToCart($this->sellable('20.00'));
        $this->post(route('cart.coupon.apply'), ['code' => $coupon->code])->assertSessionHasNoErrors();
        $this->post(route('checkout.store'), $this->checkoutPayload(['payment_method' => 'cash_on_delivery']))->assertSessionHasNoErrors();

        $order = Order::query()->firstOrFail();
        $this->assertSame('10.0000', $order->discount_total);
        $this->assertSame('3.5000', $order->payment_fee_total);
        $this->assertSame('18.5000', $order->grand_total);
    }

    public function test_admin_can_save_disable_and_validate_the_courier_fee(): void
    {
        $this->actingAs($this->userWithRole('administrator'));
        $payload = ['methods' => [
            'bank_transfer' => ['label' => 'Bank transfer', 'instructions' => 'Pay by bank.'],
            'cash_on_delivery' => ['enabled' => '1', 'label' => 'Cash on delivery', 'instructions' => 'Pay the courier.', 'fee_enabled' => '1', 'fee_amount' => '3.50'],
        ]];

        $this->put(route('admin.payments.settings.update'), $payload)->assertSessionHasNoErrors();
        $this->assertSame('3.5000', app(PaymentMethods::class)->cashOnDeliveryFee('USD')->toDecimal());
        $this->get(route('admin.payments.settings'))->assertInertia(fn (Assert $page) => $page
            ->where('currency', 'USD')
            ->where('methods.1.fee_enabled', true)
            ->where('methods.1.fee_amount', '3.50'));

        foreach (['-1', 'invalid', '0.12345', '99999999999999', null] as $invalid) {
            $payload['methods']['cash_on_delivery']['fee_amount'] = $invalid;
            $this->put(route('admin.payments.settings.update'), $payload)->assertSessionHasErrors('methods.cash_on_delivery.fee_amount');
        }

        $payload['methods']['cash_on_delivery']['fee_amount'] = '3.50';
        unset($payload['methods']['cash_on_delivery']['fee_enabled']);
        $this->put(route('admin.payments.settings.update'), $payload)->assertSessionHasNoErrors();
        $this->assertTrue(app(PaymentMethods::class)->cashOnDeliveryFee('USD')->isZero());
    }

    public function test_disabled_cod_and_different_currency_do_not_apply_the_fee(): void
    {
        $this->enableFee();
        $this->assertTrue(app(PaymentMethods::class)->cashOnDeliveryFee('AED')->isZero());
        app(SettingsRepository::class)->set('payments.cash_on_delivery.enabled', false);
        $this->assertTrue(app(PaymentMethods::class)->cashOnDeliveryFee('USD')->isZero());
    }
}
