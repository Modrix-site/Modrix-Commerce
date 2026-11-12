<?php

namespace App\Domains\Pricing;

use App\Core\Settings\Currencies;
use App\Core\Settings\StorefrontCurrencies;
use Brick\Math\BigDecimal;
use Brick\Math\BigNumber;
use Brick\Math\RoundingMode;
use InvalidArgumentException;
use JsonSerializable;
use NumberFormatter;

/**
 * Immutable monetary amount in a single ISO 4217 currency (ADR-007).
 *
 * Amounts are held as arbitrary-precision decimals and are never floats.
 * Arithmetic between different currencies is refused. `rounded()` rounds to
 * the currency's minor units (HALF_UP); line-level amounts are rounded before
 * they are summed so that totals always equal the sum of displayed lines.
 */
final class Money implements JsonSerializable
{
    private function __construct(
        private readonly BigDecimal $amount,
        private readonly string $currency,
    ) {}

    /**
     * Floats are accepted in the signature only so they can be refused
     * explicitly: without `float` here PHP would silently coerce 24.5 to int 24.
     */
    public static function of(BigNumber|int|float|string $amount, string $currency): self
    {
        if (is_float($amount)) {
            throw new InvalidArgumentException('Money must not be created from a float; pass a decimal string.');
        }

        $currency = strtoupper($currency);

        if (! array_key_exists($currency, Currencies::ALL)) {
            throw new InvalidArgumentException("Unsupported currency [{$currency}].");
        }

        return new self(BigDecimal::of($amount)->toScale(4, RoundingMode::HALF_UP), $currency);
    }

    public static function zero(string $currency): self
    {
        return self::of(0, $currency);
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function amount(): BigDecimal
    {
        return $this->amount;
    }

    public function plus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amount->plus($other->amount), $this->currency);
    }

    public function minus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amount->minus($other->amount), $this->currency);
    }

    public function multipliedBy(BigNumber|int|string $factor): self
    {
        return new self($this->amount->multipliedBy($factor)->toScale(4, RoundingMode::HALF_UP), $this->currency);
    }

    /**
     * @param  BigNumber|int|string  $percent  e.g. "15" for 15 %
     */
    public function percentage(BigNumber|int|string $percent): self
    {
        return new self(
            $this->amount->multipliedBy($percent)->dividedBy(100, 4, RoundingMode::HALF_UP),
            $this->currency,
        );
    }

    public function rounded(): self
    {
        $scale = Currencies::ALL[$this->currency]['minor_units'];

        return new self($this->amount->toScale($scale, RoundingMode::HALF_UP)->toScale(4), $this->currency);
    }

    /**
     * Never below zero (used when discounts exceed the discountable amount).
     */
    public function nonNegative(): self
    {
        return $this->isNegative() ? self::zero($this->currency) : $this;
    }

    public function min(self $other): self
    {
        $this->assertSameCurrency($other);

        return $this->compareTo($other) <= 0 ? $this : $other;
    }

    public function isZero(): bool
    {
        return $this->amount->isZero();
    }

    public function isNegative(): bool
    {
        return $this->amount->isNegative();
    }

    public function isPositive(): bool
    {
        return $this->amount->isPositive();
    }

    public function compareTo(self $other): int
    {
        $this->assertSameCurrency($other);

        return $this->amount->compareTo($other->amount);
    }

    public function equals(self $other): bool
    {
        return $this->currency === $other->currency && $this->amount->isEqualTo($other->amount);
    }

    public function greaterThan(self $other): bool
    {
        return $this->compareTo($other) > 0;
    }

    public function lessThan(self $other): bool
    {
        return $this->compareTo($other) < 0;
    }

    /**
     * Value for a DECIMAL(19,4) column.
     */
    public function toDecimal(): string
    {
        return (string) $this->amount->toScale(4);
    }

    /**
     * Amount at the currency's precision, e.g. "12.30" for USD, "1230" for JPY.
     */
    public function toDisplayDecimal(): string
    {
        return (string) $this->amount->toScale(Currencies::ALL[$this->currency]['minor_units'], RoundingMode::HALF_UP);
    }

    public function format(?string $locale = null): string
    {
        $locale ??= str_replace('-', '_', (string) app()->getLocale());

        if (class_exists(NumberFormatter::class)) {
            $formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);
            $formatted = $formatter->formatCurrency((float) $this->toDisplayDecimal(), $this->currency);

            if ($formatted !== false) {
                return $formatted;
            }
        }

        return $this->currency.' '.$this->toDisplayDecimal();
    }

    /**
     * @return array{amount: string, currency: string, formatted: string, display_amount: string, display_currency: string}
     */
    public function jsonSerialize(): array
    {
        $display = $this;
        if (app()->bound('request')) {
            $target = request()->attributes->get('storefront.display_currency');
            if (is_string($target)) {
                $display = app(StorefrontCurrencies::class)->display($this, $target);
            }
        }

        return [
            'display_amount' => $display->toDisplayDecimal(),
            'display_currency' => $display->currency(),
            'amount' => $this->toDisplayDecimal(),
            'currency' => $this->currency,
            'formatted' => $display->format(),
        ];
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException("Currency mismatch: {$this->currency} vs {$other->currency}.");
        }
    }
}
