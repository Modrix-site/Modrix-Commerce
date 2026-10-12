<?php

namespace App\Domains\Tax;

use App\Core\Settings\SettingsRepository;

final class TaxSettings
{
    public function __construct(private readonly SettingsRepository $settings) {}

    /**
     * Whether catalog prices already include tax (common for B2C in VAT/GST regions).
     */
    public function pricesIncludeTax(): bool
    {
        return (bool) $this->settings->get('tax.prices_include_tax', false);
    }

    public function taxShipping(): bool
    {
        return (bool) $this->settings->get('tax.shipping_taxable', false);
    }

    public function update(bool $pricesIncludeTax, bool $taxShipping): void
    {
        $this->settings->setMany([
            'tax.prices_include_tax' => $pricesIncludeTax,
            'tax.shipping_taxable' => $taxShipping,
        ]);
    }
}
