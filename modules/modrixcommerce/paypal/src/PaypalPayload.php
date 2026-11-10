<?php

namespace EcoModular\Paypal;

use App\Core\Settings\StoreSettings;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\OrderAddress;
use App\Domains\Payments\Gateway\GatewayException;
use App\Domains\Payments\Models\Payment;

/**
 * Orders v2 create-order body. PayPal collects the payment method.
 *
 * @see https://developer.paypal.com/docs/api/orders/v2/#orders_create
 */
final class PaypalPayload
{
    public function __construct(
        private readonly PaypalSettings $settings,
        private readonly StoreSettings $store,
    ) {}

    /**
     * @return array<string, mixed>
     *
     * @throws GatewayException
     */
    public function order(Order $order, Payment $payment, string $returnUrl, string $cancelUrl): array
    {
        if (! $this->settings->sandbox() && ! str_starts_with($returnUrl, 'https://')) {
            throw new GatewayException('PayPal live mode requires an https return address. Set APP_URL to your store\'s https address.');
        }

        $order->loadMissing(['billingAddress', 'shippingAddress']);
        $address = $order->shippingAddress ?? $order->billingAddress;
        $reference = 'p'.$payment->id;

        $unit = [
            'reference_id' => $reference,
            'custom_id' => $reference,
            'invoice_id' => $reference,
            'description' => mb_substr('Order '.($order->number ?? $payment->id), 0, 127),
            'amount' => [
                'currency_code' => $payment->currency,
                'value' => PaypalAmount::toValue($payment->money()),
            ],
        ];

        $shipping = $this->shipping($address);

        if ($shipping !== null) {
            $unit['shipping'] = $shipping;
        }

        return [
            'intent' => 'CAPTURE',
            'purchase_units' => [$unit],
            'payment_source' => [
                'paypal' => [
                    'experience_context' => [
                        'payment_method_preference' => 'IMMEDIATE_PAYMENT_REQUIRED',
                        'brand_name' => mb_substr($this->store->name(), 0, 127),
                        'shipping_preference' => $shipping === null ? 'NO_SHIPPING' : 'SET_PROVIDED_ADDRESS',
                        'user_action' => 'PAY_NOW',
                        'return_url' => $returnUrl,
                        'cancel_url' => $cancelUrl,
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function shipping(?OrderAddress $address): ?array
    {
        if (! $address instanceof OrderAddress || preg_match('/^[A-Z]{2}$/', strtoupper($address->country_code)) !== 1) {
            return null;
        }

        if ($address->line1 === '' || $address->city === '') {
            return null;
        }

        return [
            'name' => ['full_name' => mb_substr(trim($address->first_name.' '.$address->last_name), 0, 300)],
            'address' => array_filter([
                'address_line_1' => mb_substr($address->line1, 0, 300),
                'address_line_2' => $address->line2 ? mb_substr($address->line2, 0, 300) : null,
                'admin_area_2' => mb_substr($address->city, 0, 120),
                'admin_area_1' => $address->region ? mb_substr($address->region, 0, 300) : null,
                'postal_code' => $address->postal_code ? mb_substr($address->postal_code, 0, 60) : null,
                'country_code' => strtoupper($address->country_code),
            ]),
        ];
    }
}
