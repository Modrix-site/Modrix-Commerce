<?php

namespace App\Domains\Payments;

use App\Core\Settings\SettingsRepository;
use App\Core\Settings\StoreSettings;
use App\Domains\Checkout\Pricing\PricedCart;
use App\Domains\Payments\Contracts\PaymentGateway;
use App\Domains\Payments\Contracts\PaymentMethod;
use App\Domains\Pricing\Money;

/**
 * Registry of payment methods: the merchant-configured manual methods plus
 * online gateways and methods registered by modules. A gateway is offered
 * only once it is configured (enabled, with credentials).
 */
final class PaymentMethods
{
    /** Built-in manual methods and their default customer-facing texts. */
    public const MANUAL = [
        'bank_transfer' => [
            'label' => 'Bank transfer',
            'instructions' => 'Transfer the order total to our bank account. Use your order number as the reference. We ship once the payment is received.',
            'pay_on_delivery' => false,
        ],
        'cash_on_delivery' => [
            'label' => 'Cash on delivery',
            'instructions' => 'Pay the courier in cash when your order arrives.',
            'pay_on_delivery' => true,
        ],
    ];

    /** @var array<string, PaymentMethod> */
    private array $extra = [];

    public function __construct(private readonly SettingsRepository $settings) {}

    public function register(PaymentMethod $method): void
    {
        $this->extra[$method->code()] = $method;
    }

    /**
     * Enabled methods, in display order.
     *
     * @return array<string, PaymentMethod>
     */
    public function enabled(): array
    {
        $methods = [];

        foreach (self::MANUAL as $code => $defaults) {
            $config = $this->manualConfig($code);

            if ($config['enabled']) {
                $methods[$code] = new ManualPaymentMethod($code, $config['label'], $config['instructions'], $defaults['pay_on_delivery']);
            }
        }

        $extra = array_filter($this->extra, fn (PaymentMethod $m) => ! $m instanceof PaymentGateway || $m->isConfigured());

        return $methods + $extra;
    }

    /**
     * @return array<string, PaymentMethod>
     */
    public function availableFor(PricedCart $cart): array
    {
        return array_filter($this->enabled(), fn (PaymentMethod $m) => $m->isAvailable($cart));
    }

    public function find(string $code): ?PaymentMethod
    {
        return $this->enabled()[$code] ?? null;
    }

    /**
     * A method by code even if it has since been disabled (for existing orders).
     */
    public function definition(string $code): ?PaymentMethod
    {
        if (isset(self::MANUAL[$code])) {
            $config = $this->manualConfig($code);

            return new ManualPaymentMethod($code, $config['label'], $config['instructions'], self::MANUAL[$code]['pay_on_delivery']);
        }

        return $this->extra[$code] ?? null;
    }

    /**
     * The gateway behind a method code, if the method is an online gateway.
     */
    public function gateway(string $code): ?PaymentGateway
    {
        $method = $this->extra[$code] ?? null;

        return $method instanceof PaymentGateway ? $method : null;
    }

    /**
     * The gateway for a webhook/provider identifier, configured or not
     * (webhooks for earlier payments must still be accepted after disabling).
     */
    public function gatewayForProvider(string $provider): ?PaymentGateway
    {
        foreach ($this->extra as $method) {
            if ($method instanceof PaymentGateway && $method->provider() === $provider) {
                return $method;
            }
        }

        return null;
    }

    public function isGateway(string $code): bool
    {
        return $this->gateway($code) !== null;
    }

    /**
     * @return array{enabled: bool, label: string, instructions: string, fee_enabled: bool, fee_amount: string}
     */
    public function manualConfig(string $code): array
    {
        $defaults = self::MANUAL[$code];

        return [
            'enabled' => (bool) $this->settings->get("payments.{$code}.enabled", false),
            'label' => (string) ($this->settings->get("payments.{$code}.label") ?: $defaults['label']),
            'instructions' => (string) ($this->settings->get("payments.{$code}.instructions") ?: $defaults['instructions']),
            'fee_enabled' => $code === 'cash_on_delivery' && (bool) $this->settings->get('payments.cash_on_delivery.fee_enabled', false),
            'fee_amount' => $code === 'cash_on_delivery' ? (string) $this->settings->get('payments.cash_on_delivery.fee_amount', '0.00') : '0.00',
        ];
    }

    public function cashOnDeliveryFee(string $currency): Money
    {
        $config = $this->manualConfig('cash_on_delivery');
        $feeCurrency = $this->settings->get('payments.cash_on_delivery.fee_currency', app(StoreSettings::class)->currency());

        if (! $config['enabled'] || ! $config['fee_enabled'] || $currency !== $feeCurrency) {
            return Money::zero($currency);
        }

        return Money::of($config['fee_amount'], $currency)->nonNegative()->rounded();
    }
}
