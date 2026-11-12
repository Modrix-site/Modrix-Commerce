<?php

namespace App\Domains\Catalog\Pricing;

use App\Domains\Pricing\Money;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * A temporary price reduction for a product, offered by a module through
 * SalePriceProvider (e.g. a flash sale). Percentages apply to any currency;
 * a fixed amount applies only to prices in its own currency.
 */
final class SaleOffer
{
    public const PERCENT = 'percent';

    public const FIXED = 'fixed';

    public function __construct(
        public readonly string $type,
        public readonly string $value,
        public readonly string $name,
        public readonly ?CarbonInterface $endsAt = null,
        public readonly ?string $currency = null,
        // Shown on the tag, e.g. "Flash sale".
        public readonly string $label = 'Sale',
        // Whether shoppers see a countdown to endsAt.
        public readonly bool $countdown = true,
    ) {
        if (! in_array($type, [self::PERCENT, self::FIXED], true)) {
            throw new InvalidArgumentException("Unknown sale offer type [{$type}].");
        }

        if ($type === self::FIXED && $currency === null) {
            throw new InvalidArgumentException('A fixed-amount offer needs a currency.');
        }
    }

    /** Whether the offer can reduce a price in this currency. */
    public function appliesTo(string $currency): bool
    {
        return $this->type === self::PERCENT || $this->currency === $currency;
    }

    /** The reduced price, never below zero. */
    public function apply(Money $price): Money
    {
        $reduction = $this->type === self::PERCENT
            ? $price->percentage($this->value)
            : Money::of($this->value, $price->currency());

        // Rounded to the currency's precision, so the shown and charged prices match.
        return $price->minus($reduction)->rounded()->nonNegative();
    }

    /** Short label for a badge: "−20%" or "−$5.00". */
    public function badge(): string
    {
        if ($this->type === self::PERCENT) {
            return '−'.rtrim(rtrim(number_format((float) $this->value, 2, '.', ''), '0'), '.').'%';
        }

        return '−'.Money::of($this->value, (string) $this->currency)->format();
    }

    /**
     * What the storefront shows: the tag on cards and the notice under the
     * product price.
     *
     * @return array{badge: string, name: string, label: string, ends_at: string|null, countdown: bool}
     */
    public function toProps(): array
    {
        return [
            'badge' => $this->badge(),
            'name' => $this->name,
            'label' => $this->label,
            'ends_at' => $this->endsAt?->toIso8601String(),
            'countdown' => $this->countdown && $this->endsAt !== null,
        ];
    }
}
