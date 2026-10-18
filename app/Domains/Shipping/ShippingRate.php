<?php

namespace App\Domains\Shipping;

use App\Domains\Pricing\Money;

final class ShippingRate
{
    public function __construct(
        public readonly string $code,
        public readonly string $label,
        public readonly Money $amount,
        public readonly ?string $description = null,
    ) {}

    /**
     * @return array{code: string, label: string, amount: Money, description: string|null}
     */
    public function toArray(): array
    {
        return ['code' => $this->code, 'label' => $this->label, 'amount' => $this->amount, 'description' => $this->description];
    }
}
