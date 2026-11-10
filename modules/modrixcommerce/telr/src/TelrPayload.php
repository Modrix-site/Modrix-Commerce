<?php

namespace EcoModular\Telr;

use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\OrderAddress;
use App\Domains\Payments\Models\Payment;

/**
 * Hosted payment page create request (POST /gateway/order.json).
 *
 * Card fields are not included. Telr collects them on its own page.
 */
final class TelrPayload
{
    public function __construct(private readonly TelrSettings $settings) {}

    /**
     * @return array<string, mixed>
     */
    public function create(Order $order, Payment $payment, string $returnUrl, string $cancelUrl): array
    {
        $order->loadMissing(['billingAddress', 'shippingAddress']);
        $description = mb_substr('Order '.($order->number ?? $order->id), 0, 63);
        $webhook = route('webhooks.payments', TelrGateway::CODE);

        $body = [
            'method' => 'create',
            'store' => $this->settings->storeId(),
            'authkey' => $this->settings->authKey(),
            'framed' => $this->settings->iframe() ? 3 : 0,
            'order' => [
                'cartid' => 'p'.$payment->id,
                'test' => $this->settings->testMode() ? '1' : '0',
                'amount' => $payment->money()->toDisplayDecimal(),
                'currency' => $payment->currency,
                'description' => $description,
                'trantype' => 'sale',
            ],
            'return' => [
                'authorised' => $returnUrl,
                'declined' => $returnUrl,
                'cancelled' => $cancelUrl,
            ],
        ];

        $customer = $this->customer($order);

        if ($customer !== []) {
            $body['customer'] = $customer;
        }

        if (str_starts_with($webhook, 'https://')) {
            $body['webhooks'] = [['url' => $webhook]];
        }

        return $body;
    }

    /**
     * @return array<string, mixed>
     */
    private function customer(Order $order): array
    {
        $address = $order->billingAddress ?? $order->shippingAddress;
        $customer = ['email' => mb_substr($order->email, 0, 100)];

        if ($address instanceof OrderAddress) {
            $customer['name'] = [
                'forenames' => mb_substr($address->first_name, 0, 50),
                'surname' => mb_substr($address->last_name, 0, 50),
            ];
            $country = strtoupper($address->country_code);
            $line1 = mb_substr($address->line1, 0, 60);
            $city = mb_substr($address->city, 0, 40);

            if ($line1 !== '' && $city !== '' && preg_match('/^[A-Z]{2}$/', $country) === 1) {
                $customer['address'] = array_filter([
                    'line1' => $line1,
                    'line2' => $address->line2 !== null && $address->line2 !== '' ? mb_substr($address->line2, 0, 60) : null,
                    'city' => $city,
                    'state' => $address->region !== null && $address->region !== '' ? mb_substr($address->region, 0, 40) : null,
                    'country' => $country,
                    'areacode' => $address->postal_code !== null && $address->postal_code !== '' ? mb_substr($address->postal_code, 0, 16) : null,
                ], fn ($value) => is_string($value) && $value !== '');
            }

            $phone = $address->phone ?? $order->phone;
        } else {
            $phone = $order->phone;
        }

        if (is_string($phone) && $phone !== '') {
            $digits = mb_substr(preg_replace('/[^\d+]/', '', $phone) ?? '', 0, 20);

            if ($digits !== '') {
                $customer['phone'] = $digits;
            }
        }

        return $customer;
    }
}
