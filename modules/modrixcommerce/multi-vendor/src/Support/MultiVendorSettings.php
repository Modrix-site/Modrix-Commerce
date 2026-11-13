<?php

namespace EcoModular\MultiVendor\Support;

use App\Core\Extensions\ModuleRepository;
use App\Core\Extensions\ModuleSettingsSchema;
use EcoModular\MultiVendor\MultiVendorServiceProvider;

final class MultiVendorSettings
{
    public const PAYOUT_MODES = [
        'vendor_request',
        'automatic_on_order',
        'admin_batch',
    ];

    public function __construct(
        private readonly ModuleSettingsSchema $schema,
    ) {}

    public function enabled(): bool
    {
        return $this->bool('enabled', true);
    }

    public function allowVendorRegistration(): bool
    {
        return $this->bool('allow_vendor_registration', true);
    }

    public function autoApproveVendors(): bool
    {
        return $this->bool('auto_approve_vendors', false);
    }

    public function requireBusinessVerification(): bool
    {
        return $this->bool('require_business_verification', false);
    }

    public function autoApproveProducts(): bool
    {
        return $this->bool('auto_approve_products', true);
    }

    public function defaultCommissionRate(): float
    {
        return (float) $this->int('default_commission_rate', 10, 0, 90);
    }

    public function payoutMode(): string
    {
        $mode = $this->string('payout_mode', 'vendor_request');

        return in_array($mode, self::PAYOUT_MODES, true) ? $mode : 'vendor_request';
    }

    public function minPayoutAmount(): float
    {
        return (float) $this->int('min_payout_amount', 50, 1, 100000);
    }

    public function payoutFeePercent(): float
    {
        return (float) $this->int('payout_fee_percent', 0, 0, 20);
    }

    public function showVendorOnCards(): bool
    {
        return $this->bool('show_vendor_on_cards', true);
    }

    public function showVendorBoxOnProduct(): bool
    {
        return $this->bool('show_vendor_box_on_product', true);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveSettings(array $data): void
    {
        $manifest = app(ModuleRepository::class)->find(MultiVendorServiceProvider::MODULE)?->manifest();
        if ($manifest !== null) {
            $current = $this->schema->values($manifest);
            $merged = array_merge($current, array_intersect_key($data, $current));
            $this->schema->save($manifest, $merged);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled(),
            'allow_vendor_registration' => $this->allowVendorRegistration(),
            'auto_approve_vendors' => $this->autoApproveVendors(),
            'require_business_verification' => $this->requireBusinessVerification(),
            'auto_approve_products' => $this->autoApproveProducts(),
            'default_commission_rate' => $this->defaultCommissionRate(),
            'payout_mode' => $this->payoutMode(),
            'min_payout_amount' => $this->minPayoutAmount(),
            'payout_fee_percent' => $this->payoutFeePercent(),
            'show_vendor_on_cards' => $this->showVendorOnCards(),
            'show_vendor_box_on_product' => $this->showVendorBoxOnProduct(),
        ];
    }

    private function bool(string $key, bool $default): bool
    {
        return (bool) $this->schema->of(MultiVendorServiceProvider::MODULE, $key, $default);
    }

    private function int(string $key, int $default, int $min, int $max): int
    {
        $val = $this->schema->of(MultiVendorServiceProvider::MODULE, $key, $default);

        return max($min, min($max, is_numeric($val) ? (int) $val : $default));
    }

    private function string(string $key, string $default): string
    {
        $val = $this->schema->of(MultiVendorServiceProvider::MODULE, $key, $default);

        return is_string($val) ? $val : $default;
    }
}
