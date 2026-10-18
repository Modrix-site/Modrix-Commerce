<?php

namespace EcoModular\Tap;

use App\Domains\Orders\Models\Order;
use App\Domains\Payments\Gateway\GatewayException;
use App\Domains\Payments\Models\Payment;

/**
 * Create-charge body for Tap's hosted page. The card is collected by Tap.
 *
 * @see https://developers.tap.company/reference/charges
 * @see https://developers.tap.company/docs/redirect
 */
final class TapPayload
{
    public function __construct(private readonly TapSettings $settings) {}

    /**
     * @return array<string, mixed>
     *
     * @throws GatewayException
     */
    public function charge(Order $order, Payment $payment, string $returnUrl, string $webhookUrl): array
    {
        if ($this->settings->live() && ! str_starts_with($returnUrl, 'https://')) {
            throw new GatewayException('Tap live mode requires an https return address. Set APP_URL to your store\'s https address.');
        }

        if (! filter_var($order->email, FILTER_VALIDATE_EMAIL)) {
            throw new GatewayException('Tap requires an email address for the customer.');
        }

        $order->loadMissing(['billingAddress', 'shippingAddress']);
        $address = $order->billingAddress ?? $order->shippingAddress;
        $reference = 'p'.$payment->id;
        $customer = [
            'first_name' => $this->name($address?->first_name, 'Customer'),
            'last_name' => $this->name($address?->last_name, 'Customer'),
            'email' => $order->email,
        ];
        $phone = $this->phone($order->phone);

        if ($phone !== null) {
            $customer['phone'] = $phone;
        }

        $body = [
            'amount' => TapAmount::toValue($payment->money()),
            'currency' => $payment->currency,
            'customer_initiated' => true,
            'threeDSecure' => true,
            'save_card' => false,
            'description' => mb_substr('Order '.($order->number ?? $payment->id), 0, 100),
            'metadata' => [
                'payment_id' => (string) $payment->id,
                'order_id' => (string) $order->id,
            ],
            'reference' => [
                'transaction' => $reference,
                'order' => mb_substr((string) ($order->number ?? $payment->id), 0, 50),
            ],
            'receipt' => ['email' => false, 'sms' => false],
            'customer' => $customer,
            'source' => ['id' => $this->settings->sourceId()],
            'redirect' => ['url' => $returnUrl],
        ];

        if (str_starts_with($webhookUrl, 'https://')) {
            $body['post'] = ['url' => $webhookUrl];
        }

        $merchantId = $this->settings->merchantId();

        if ($merchantId !== null) {
            $body['merchant'] = ['id' => $merchantId];
        }

        return $body;
    }

    private function name(?string $value, string $fallback): string
    {
        $name = trim((string) $value);

        return mb_substr($name !== '' ? $name : $fallback, 0, 50);
    }

    /**
     * @return array{country_code: string, number: string}|null
     */
    private function phone(?string $phone): ?array
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        if (preg_match('/^(966|965|973|968|974|971|20|962|44|1)(\d{6,12})$/', $digits, $match) !== 1) {
            return null;
        }

        return ['country_code' => $match[1], 'number' => $match[2]];
    }
}
