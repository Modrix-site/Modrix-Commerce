<?php

namespace App\Domains\Payments;

use App\Core\Settings\SettingsRepository;
use App\Domains\Checkout\Pricing\PricedCart;
use App\Domains\Payments\Contracts\PaymentMethod;

/**
 * Registry of payment methods: the merchant-configured manual methods plus
 * any registered by modules (Phase 3+).
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

        return $methods + $this->extra;
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
     * @return array{enabled: bool, label: string, instructions: string}
     */
    public function manualConfig(string $code): array
    {
        $defaults = self::MANUAL[$code];

        return [
            'enabled' => (bool) $this->settings->get("payments.{$code}.enabled", false),
            'label' => (string) ($this->settings->get("payments.{$code}.label") ?: $defaults['label']),
            'instructions' => (string) ($this->settings->get("payments.{$code}.instructions") ?: $defaults['instructions']),
        ];
    }
}
