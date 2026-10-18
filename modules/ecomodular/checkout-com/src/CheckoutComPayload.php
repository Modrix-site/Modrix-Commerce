<?php

namespace EcoModular\CheckoutCom;

use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\OrderAddress;
use App\Domains\Payments\Gateway\GatewayException;
use App\Domains\Payments\Models\Payment;

/**
 * Hosted Payments Page and Payment Session requests.
 *
 * Card fields are never included. Checkout.com collects them.
 *
 * @see https://www.checkout.com/docs/payments/accept-payments/accept-a-payment-on-a-hosted-page
 * @see https://www.checkout.com/docs/payments/accept-payments/accept-a-payment-on-your-website/get-started-with-flow
 */
final class CheckoutComPayload
{
    public function __construct(private readonly CheckoutComSettings $settings) {}

    /**
     * @return array<string, mixed>
     *
     * @throws GatewayException
     */
    public function session(Order $order, Payment $payment, string $returnUrl, string $cancelUrl): array
    {
        $order->loadMissing(['billingAddress', 'shippingAddress']);
        $address = $order->billingAddress ?? $order->shippingAddress;
        $country = $address instanceof OrderAddress ? strtoupper($address->country_code) : '';

        if (preg_match('/^[A-Z]{2}$/', $country) !== 1) {
            throw new GatewayException(
                'Checkout.com requires a billing country.',
                customerMessage: 'Add a billing address before paying with Checkout.com.',
            );
        }

        $body = [
            'amount' => CheckoutComAmount::toMinor($payment->money()),
            'currency' => $payment->currency,
            'reference' => 'p'.$payment->id,
            'description' => mb_substr('Order '.($order->number ?? $payment->id), 0, 100),
            'capture' => true,
            'customer' => array_filter([
                'email' => mb_substr($order->email, 0, 255),
                'name' => $address instanceof OrderAddress ? mb_substr(trim($address->first_name.' '.$address->last_name), 0, 255) : null,
            ]),
            'billing' => [
                'address' => array_filter([
                    'country' => $country,
                    'address_line1' => $address instanceof OrderAddress ? mb_substr($address->line1, 0, 200) : null,
                    'city' => $address instanceof OrderAddress ? mb_substr($address->city, 0, 50) : null,
                    'zip' => $address instanceof OrderAddress && $address->postal_code ? mb_substr($address->postal_code, 0, 50) : null,
                ]),
            ],
            'success_url' => $returnUrl,
            'failure_url' => $returnUrl,
            'cancel_url' => $cancelUrl,
            'metadata' => [
                'payment_id' => (string) $payment->id,
                'order_number' => (string) ($order->number ?? ''),
            ],
        ];

        $channel = $this->settings->processingChannelId();

        if ($channel !== null) {
            $body['processing_channel_id'] = $channel;
        }

        return $body;
    }
}
