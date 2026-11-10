<?php

namespace EcoModular\FraudDetection\Services;

use App\Core\Extensions\ModuleSettingsSchema;
use App\Domains\Orders\Models\Order;
use EcoModular\FraudDetection\FraudDetectionServiceProvider as Module;
use EcoModular\FraudDetection\Models\BlacklistedEmail;
use EcoModular\FraudDetection\Models\BlacklistedIp;
use EcoModular\FraudDetection\Models\FraudCountryRisk;
use EcoModular\FraudDetection\Models\FraudCustomerProfile;
use EcoModular\FraudDetection\Models\FraudRule;
use EcoModular\FraudDetection\Models\FraudScore;

class RiskScoringEngine
{
    /** @var list<array{key: string, label: string, score: int, details: string}> */
    private array $indicators = [];

    private int $totalScore = 0;

    public function __construct(
        private readonly ModuleSettingsSchema $settings,
    ) {}

    /**
     * Analyze an order and produce a FraudScore.
     *
     * @param  array<string, mixed>  $context
     */
    public function analyze(Order $order, array $context = []): FraudScore
    {
        $this->indicators = [];
        $this->totalScore = 0;

        $orderContext = $this->buildOrderContext($order, $context);

        // ── Built-in checks ──
        $this->checkHighOrderValue($orderContext);
        $this->checkFailedPayments($orderContext);
        $this->checkAddressMismatch($orderContext);
        $this->checkCountryMismatch($orderContext);
        $this->checkDisposableEmail($orderContext);
        $this->checkVpnProxy($orderContext);
        $this->checkMultipleIpOrders($orderContext);
        $this->checkSuspiciousBehavior($orderContext);
        $this->checkBlacklistedIp($orderContext);
        $this->checkBlacklistedEmail($orderContext);
        $this->checkCountryRisk($orderContext);
        $this->checkCustomerHistory($orderContext);

        // ── Custom rules ──
        $this->evaluateCustomRules($orderContext);

        // Cap at 100
        $this->totalScore = min(100, max(0, $this->totalScore));

        $riskLevel = $this->determineRiskLevel($this->totalScore);
        $reviewStatus = $this->determineReviewStatus($this->totalScore);

        $fraudScore = FraudScore::create([
            'order_id' => $order->id,
            'customer_id' => $orderContext['customer_id'] ?? null,
            'total_score' => $this->totalScore,
            'risk_level' => $riskLevel,
            'review_status' => $reviewStatus,
            'indicators' => $this->indicators,
            'ip_address' => $orderContext['ip_address'] ?? null,
            'customer_email' => $orderContext['email'] ?? null,
            'customer_country' => $orderContext['customer_country'] ?? null,
            'payment_country' => $orderContext['payment_country'] ?? null,
            'is_vpn' => $orderContext['is_vpn'] ?? false,
            'is_proxy' => $orderContext['is_proxy'] ?? false,
            'is_disposable_email' => $this->isDisposableEmail($orderContext['email'] ?? ''),
            'address_mismatch' => $orderContext['address_mismatch'] ?? false,
            'failed_payment_attempts' => $orderContext['failed_payment_attempts'] ?? 0,
            'order_total' => $orderContext['order_total'] ?? 0,
            'currency' => $orderContext['currency'] ?? 'USD',
        ]);

        // Auto-approve low-risk orders
        if ($riskLevel === FraudScore::RISK_LOW) {
            $fraudScore->update(['review_status' => FraudScore::STATUS_APPROVED]);
        }

        return $fraudScore;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function buildOrderContext(Order $order, array $context): array
    {
        $billingAddress = $order->billingAddress ?? null;
        $shippingAddress = $order->shippingAddress ?? null;
        $email = $order->customer?->email ?? $order->email ?? $context['email'] ?? '';

        return array_merge([
            'order_id' => $order->id,
            'order_total' => (float) ($order->grand_total ?? 0),
            'currency' => $order->currency ?? 'USD',
            'customer_id' => $order->customer_id,
            'email' => $email,
            'email_domain' => $email ? strtolower(substr(strrchr($email, '@'), 1) ?: '') : '',
            'ip_address' => $context['ip_address'] ?? $order->ip_address ?? request()->ip(),
            'billing_country' => $billingAddress?->country_code ?? $billingAddress?->country ?? null,
            'shipping_country' => $shippingAddress?->country_code ?? $shippingAddress?->country ?? null,
            'customer_country' => $billingAddress?->country_code ?? $billingAddress?->country ?? $context['customer_country'] ?? null,
            'payment_country' => $context['payment_country'] ?? null,
            'billing_address_line' => $this->normalizeAddress($billingAddress),
            'shipping_address_line' => $this->normalizeAddress($shippingAddress),
            'address_mismatch' => false,
            'is_vpn' => $context['is_vpn'] ?? false,
            'is_proxy' => $context['is_proxy'] ?? false,
            'failed_payment_attempts' => $context['failed_payment_attempts'] ?? 0,
            'items_count' => $order->items?->count() ?? 0,
            'customer_orders_count' => $order->customer?->orders()?->count() ?? 0,
            'payment_method' => $context['payment_method'] ?? null,
        ], $context);
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function checkHighOrderValue(array $ctx): void
    {
        $threshold = (float) $this->settings->of(Module::MODULE, 'high_order_value_threshold', 500);
        $weight = (int) $this->settings->of(Module::MODULE, 'weight_high_order_value', 15);

        if ($weight > 0 && (float) $ctx['order_total'] > $threshold) {
            $this->addIndicator(
                'high_order_value',
                'High order value',
                $weight,
                sprintf('Order total %s exceeds threshold of %s.', number_format($ctx['order_total'], 2), number_format($threshold, 2))
            );
        }
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function checkFailedPayments(array $ctx): void
    {
        $weight = (int) $this->settings->of(Module::MODULE, 'weight_failed_payments', 20);
        $attempts = (int) ($ctx['failed_payment_attempts'] ?? 0);

        if ($weight > 0 && $attempts >= 2) {
            $score = min($weight, (int) ($weight * ($attempts / 5)));
            $this->addIndicator(
                'failed_payments',
                'Multiple failed payment attempts',
                $score,
                sprintf('%d failed payment attempt(s) detected.', $attempts)
            );
        }
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function checkAddressMismatch(array &$ctx): void
    {
        $weight = (int) $this->settings->of(Module::MODULE, 'weight_address_mismatch', 15);

        if ($weight > 0 && $ctx['billing_address_line'] && $ctx['shipping_address_line']) {
            if ($ctx['billing_address_line'] !== $ctx['shipping_address_line']) {
                $ctx['address_mismatch'] = true;
                $this->addIndicator(
                    'address_mismatch',
                    'Billing/shipping address mismatch',
                    $weight,
                    'The billing and shipping addresses do not match.'
                );
            }
        }
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function checkCountryMismatch(array $ctx): void
    {
        $weight = (int) $this->settings->of(Module::MODULE, 'weight_country_mismatch', 20);

        if ($weight > 0 && $ctx['customer_country'] && $ctx['payment_country']) {
            if (strtoupper($ctx['customer_country']) !== strtoupper($ctx['payment_country'])) {
                $this->addIndicator(
                    'country_mismatch',
                    'Country mismatch',
                    $weight,
                    sprintf('Customer country (%s) differs from payment method country (%s).', $ctx['customer_country'], $ctx['payment_country'])
                );
            }
        }
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function checkDisposableEmail(array $ctx): void
    {
        $weight = (int) $this->settings->of(Module::MODULE, 'weight_disposable_email', 15);

        if ($weight > 0 && ! empty($ctx['email']) && $this->isDisposableEmail($ctx['email'])) {
            $this->addIndicator(
                'disposable_email',
                'Disposable email address',
                $weight,
                sprintf('Email domain "%s" is flagged as disposable.', $ctx['email_domain'])
            );
        }
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function checkVpnProxy(array $ctx): void
    {
        $weight = (int) $this->settings->of(Module::MODULE, 'weight_vpn_proxy', 10);

        if ($weight > 0 && ($ctx['is_vpn'] || $ctx['is_proxy'])) {
            $type = $ctx['is_vpn'] ? 'VPN' : 'Proxy';
            $this->addIndicator(
                'vpn_proxy',
                'VPN/Proxy detected',
                $weight,
                sprintf('%s connection detected from IP %s.', $type, $ctx['ip_address'] ?? 'unknown')
            );
        }
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function checkMultipleIpOrders(array $ctx): void
    {
        $weight = (int) $this->settings->of(Module::MODULE, 'weight_multiple_ip_orders', 10);
        $windowHours = (int) $this->settings->of(Module::MODULE, 'ip_orders_window_hours', 24);
        $limit = (int) $this->settings->of(Module::MODULE, 'ip_orders_limit', 5);

        if ($weight > 0 && ! empty($ctx['ip_address'])) {
            $recentCount = FraudScore::query()
                ->where('ip_address', $ctx['ip_address'])
                ->where('created_at', '>=', now()->subHours($windowHours))
                ->count();

            if ($recentCount >= $limit) {
                $this->addIndicator(
                    'multiple_ip_orders',
                    'Multiple orders from same IP',
                    $weight,
                    sprintf('%d orders from IP %s in the last %d hours (limit: %d).', $recentCount, $ctx['ip_address'], $windowHours, $limit)
                );
            }
        }
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function checkSuspiciousBehavior(array $ctx): void
    {
        $weight = (int) $this->settings->of(Module::MODULE, 'weight_suspicious_behavior', 15);

        if ($weight <= 0) {
            return;
        }

        $suspiciousReasons = [];

        // New customer with unusually high order
        if (($ctx['customer_orders_count'] ?? 0) <= 1 && ($ctx['order_total'] ?? 0) > 300) {
            $suspiciousReasons[] = 'First-time customer with high-value order';
        }

        // Unusually large quantity of items
        if (($ctx['items_count'] ?? 0) > 10) {
            $suspiciousReasons[] = sprintf('Large item count (%d items)', $ctx['items_count']);
        }

        if (! empty($suspiciousReasons)) {
            $score = min($weight, (int) ($weight * (count($suspiciousReasons) / 3)));
            $this->addIndicator(
                'suspicious_behavior',
                'Suspicious purchasing behavior',
                $score,
                implode('; ', $suspiciousReasons)
            );
        }
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function checkBlacklistedIp(array $ctx): void
    {
        if (empty($ctx['ip_address'])) {
            return;
        }

        $blacklisted = BlacklistedIp::query()
            ->where('ip_address', $ctx['ip_address'])
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->exists();

        if ($blacklisted) {
            $this->addIndicator(
                'blacklisted_ip',
                'Blacklisted IP address',
                70,
                sprintf('IP address %s is on the blacklist.', $ctx['ip_address'])
            );
        }
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function checkBlacklistedEmail(array $ctx): void
    {
        if (empty($ctx['email_domain'])) {
            return;
        }

        $blacklisted = BlacklistedEmail::query()
            ->where('domain', strtolower($ctx['email_domain']))
            ->where('is_active', true)
            ->exists();

        if ($blacklisted) {
            $this->addIndicator(
                'blacklisted_email_domain',
                'Blacklisted email domain',
                65,
                sprintf('Email domain "%s" is on the blacklist.', $ctx['email_domain'])
            );
        }
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function checkCountryRisk(array $ctx): void
    {
        $country = $ctx['customer_country'] ?? $ctx['billing_country'] ?? null;
        if (! $country) {
            return;
        }

        $countryRisk = FraudCountryRisk::query()
            ->where('country_code', strtoupper($country))
            ->where('is_active', true)
            ->first();

        if ($countryRisk && $countryRisk->score_modifier > 0) {
            $label = $countryRisk->risk_level === 'blocked' ? 'Blocked country' : 'High-risk country';
            $score = $countryRisk->risk_level === 'blocked' ? 50 : $countryRisk->score_modifier;

            $this->addIndicator(
                'country_risk',
                $label,
                $score,
                sprintf('Country %s (%s) has risk level: %s.', $countryRisk->country_name, $countryRisk->country_code, $countryRisk->risk_level)
            );
        }
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function checkCustomerHistory(array $ctx): void
    {
        $customerId = $ctx['customer_id'] ?? null;
        if (! $customerId) {
            return;
        }

        $profile = FraudCustomerProfile::query()
            ->where('customer_id', $customerId)
            ->first();

        if ($profile && $profile->is_repeat_offender) {
            $this->addIndicator(
                'repeat_offender',
                'Repeat offender',
                25,
                sprintf('Customer has %d rejected orders out of %d total. Trust score: %d/100.', $profile->rejected_orders, $profile->total_orders, $profile->trust_score)
            );
        }
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function evaluateCustomRules(array $ctx): void
    {
        $rules = FraudRule::query()
            ->where('is_active', true)
            ->orderBy('priority')
            ->get();

        foreach ($rules as $rule) {
            /** @var FraudRule $rule */
            if ($rule->evaluate($ctx)) {
                $this->addIndicator(
                    'custom_rule_' . $rule->id,
                    'Rule: ' . $rule->name,
                    $rule->score_impact,
                    $rule->description ?? sprintf('Custom rule "%s" matched.', $rule->name)
                );
            }
        }
    }

    private function addIndicator(string $key, string $label, int $score, string $details): void
    {
        $this->indicators[] = [
            'key' => $key,
            'label' => $label,
            'score' => $score,
            'details' => $details,
        ];
        $this->totalScore += $score;
    }

    private function determineRiskLevel(int $score): string
    {
        $lowCeiling = (int) $this->settings->of(Module::MODULE, 'low_risk_threshold', 30);
        $mediumCeiling = (int) $this->settings->of(Module::MODULE, 'medium_risk_threshold', 65);

        if ($score <= $lowCeiling) {
            return FraudScore::RISK_LOW;
        }
        if ($score <= $mediumCeiling) {
            return FraudScore::RISK_MEDIUM;
        }
        return FraudScore::RISK_HIGH;
    }

    private function determineReviewStatus(int $score): string
    {
        $autoFlagEnabled = (bool) $this->settings->of(Module::MODULE, 'auto_flag_enabled', true);
        $autoRejectThreshold = (int) $this->settings->of(Module::MODULE, 'auto_reject_threshold', 95);

        if ($score >= $autoRejectThreshold) {
            return FraudScore::STATUS_REJECTED;
        }

        if ($autoFlagEnabled && $score > (int) $this->settings->of(Module::MODULE, 'low_risk_threshold', 30)) {
            return FraudScore::STATUS_PENDING;
        }

        return FraudScore::STATUS_APPROVED;
    }

    public function isDisposableEmail(string $email): bool
    {
        $domain = strtolower(substr(strrchr($email, '@'), 1) ?: '');
        if ($domain === '') {
            return false;
        }

        $disposableDomains = [
            'tempmail.com', 'throwaway.email', 'guerrillamail.com', 'guerrillamailblock.com',
            'mailinator.com', 'trashmail.com', 'yopmail.com', 'fakeinbox.com', 'sharklasers.com',
            'guerrillamail.info', 'grr.la', 'guerrillamail.biz', 'guerrillamail.de',
            'guerrillamail.net', 'guerrillamail.org', 'spam4.me', 'trashmail.me', 'trashmail.net',
            'dispostable.com', 'byom.de', 'discardmail.com', 'discardmail.de', 'tmail.ws',
            'mailcatch.com', 'maildrop.cc', 'discard.email', 'mailnesia.com', 'tempail.com',
            'tempr.email', 'temp-mail.org', '10minutemail.com', 'binkmail.com', 'mailtemp.info',
            'tempmailo.com', 'mohmal.com', 'getnada.com', 'emailondeck.com', 'inboxbear.com',
            'mintemail.com', 'harakirimail.com', 'jetable.org', 'trash-mail.at', 'mytemp.email',
        ];

        return in_array($domain, $disposableDomains, true);
    }

    /**
     * @param  object|null  $address
     */
    private function normalizeAddress(?object $address): ?string
    {
        if (! $address) {
            return null;
        }

        $parts = array_filter([
            $address->address_1 ?? $address->line1 ?? '',
            $address->city ?? '',
            $address->state ?? $address->province ?? '',
            $address->postal_code ?? $address->zip ?? '',
            $address->country_code ?? $address->country ?? '',
        ]);

        if (empty($parts)) {
            return null;
        }

        return strtolower(implode('|', $parts));
    }
}
