<?php

namespace EcoModular\AuthorizeNet;

use App\Core\Settings\StoreSettings;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\OrderAddress;
use App\Domains\Payments\Gateway\GatewayException;
use App\Domains\Payments\Models\Payment;

/**
 * getHostedPaymentPageRequest body. Card fields stay on Authorize.Net.
 *
 * @see https://developer.authorize.net/api/reference/features/accept-hosted.html
 */
final class AuthorizeNetPayload
{
    public function __construct(
        private readonly AuthorizeNetSettings $settings,
        private readonly StoreSettings $store,
    ) {}

    /**
     * @return array<string, mixed>
     *
     * @throws GatewayException
     */
    public function page(Order $order, Payment $payment, string $returnUrl, string $cancelUrl): array
    {
        if (! $this->settings->sandbox() && ! str_starts_with($returnUrl, 'https://')) {
            throw new GatewayException('Authorize.Net live mode requires an https return address. Set APP_URL to your store\'s https address.');
        }

        $reference = 'p'.$payment->id;

        if (preg_match('/^p\d{1,18}$/', $reference) !== 1) {
            throw new GatewayException('This payment cannot be sent to Authorize.Net.');
        }

        $order->loadMissing('billingAddress');
        $transaction = [
            'transactionType' => 'authCaptureTransaction',
            'amount' => AuthorizeNetAmount::toValue($payment->money()),
            'order' => [
                'invoiceNumber' => $reference,
                'description' => mb_substr('Order '.($order->number ?? $payment->id), 0, 255),
            ],
        ];

        if (filter_var($order->email, FILTER_VALIDATE_EMAIL)) {
            $transaction['customer'] = ['email' => mb_substr($order->email, 0, 255)];
        }

        $billTo = $this->billTo($order->billingAddress);

        if ($billTo !== []) {
            $transaction['billTo'] = $billTo;
        }

        return [
            'refId' => $reference,
            'transactionRequest' => $transaction,
            'hostedPaymentSettings' => [
                'setting' => [
                    [
                        'settingName' => 'hostedPaymentReturnOptions',
                        'settingValue' => json_encode([
                            'showReceipt' => true,
                            'url' => $returnUrl,
                            'urlText' => 'Continue',
                            'cancelUrl' => $cancelUrl,
                            'cancelUrlText' => 'Cancel',
                        ], JSON_THROW_ON_ERROR),
                    ],
                    [
                        'settingName' => 'hostedPaymentButtonOptions',
                        'settingValue' => '{"text":"Pay"}',
                    ],
                    [
                        'settingName' => 'hostedPaymentPaymentOptions',
                        'settingValue' => '{"cardCodeRequired":true,"showCreditCard":true,"showBankAccount":false}',
                    ],
                    [
                        'settingName' => 'hostedPaymentBillingAddressOptions',
                        'settingValue' => '{"show":true,"required":false}',
                    ],
                    [
                        'settingName' => 'hostedPaymentShippingAddressOptions',
                        'settingValue' => '{"show":false,"required":false}',
                    ],
                    [
                        'settingName' => 'hostedPaymentOrderOptions',
                        'settingValue' => json_encode([
                            'show' => true,
                            'merchantName' => mb_substr($this->store->name(), 0, 50),
                        ], JSON_THROW_ON_ERROR),
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function billTo(?OrderAddress $address): array
    {
        if (! $address instanceof OrderAddress) {
            return [];
        }

        $fields = [
            'firstName' => mb_substr($address->first_name, 0, 50),
            'lastName' => mb_substr($address->last_name, 0, 50),
            'company' => $address->company === null ? '' : mb_substr($address->company, 0, 50),
            'address' => mb_substr(trim($address->line1.($address->line2 ? ' '.$address->line2 : '')), 0, 60),
            'city' => mb_substr($address->city, 0, 40),
            'state' => $address->region === null ? '' : mb_substr($address->region, 0, 40),
            'zip' => $address->postal_code === null ? '' : mb_substr($address->postal_code, 0, 20),
            'country' => preg_match('/^[A-Za-z]{2}$/', $address->country_code) === 1 ? strtoupper($address->country_code) : '',
        ];

        return array_filter($fields, fn (string $value): bool => $value !== '');
    }
}
