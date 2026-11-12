<?php

namespace App\Domains\Marketplace;

use App\Core\Audit\AuditLogger;
use App\Domains\Marketplace\Models\LedgerEntry;
use App\Domains\Marketplace\Models\MarketplaceOrderItem;
use App\Domains\Marketplace\Models\Payout;
use App\Domains\Marketplace\Models\Publisher;
use App\Domains\Pricing\Money;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Commission and payout accounting for third-party sellers. Entries are
 * append-only and idempotent per order item; a seller's balance is the sum
 * of its net amounts per currency. Platform products are not recorded.
 */
final class SellerLedger
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function recordSale(MarketplaceOrderItem $item): void
    {
        $publisher = $item->product->publisher;

        if ($publisher->is_platform || $this->exists($item, LedgerEntry::SALE)) {
            return;
        }

        $currency = $item->order->currency;
        $gross = Money::of($item->unit_price, $currency);
        $commission = $gross->percentage($publisher->commission_percent)->rounded();

        $this->write($publisher, LedgerEntry::SALE, $item->id, null, $gross, $commission, "Sale {$item->order->number}: {$item->product_name}");
    }

    public function recordRefund(MarketplaceOrderItem $item): void
    {
        $sale = LedgerEntry::query()->where('order_item_id', $item->id)->where('type', LedgerEntry::SALE)->first();

        if ($sale === null || $this->exists($item, LedgerEntry::REFUND)) {
            return;
        }

        $publisher = Publisher::query()->findOrFail($sale->publisher_id);
        $this->write(
            $publisher,
            LedgerEntry::REFUND,
            $item->id,
            null,
            Money::of($sale->gross, $sale->currency)->multipliedBy(-1),
            Money::of($sale->commission, $sale->currency)->multipliedBy(-1),
            "Refund {$item->order->number}: {$item->product_name}",
        );
    }

    /**
     * Records a payout made outside the application. It cannot exceed the balance.
     *
     * @throws ValidationException
     */
    public function recordPayout(Publisher $publisher, Money $amount, string $reference, ?string $notes, User $actor): Payout
    {
        if ($publisher->is_platform || ! $amount->isPositive()) {
            throw ValidationException::withMessages(['amount' => 'Enter a positive amount for a seller.']);
        }

        return DB::transaction(function () use ($publisher, $amount, $reference, $notes, $actor) {
            Publisher::query()->whereKey($publisher->id)->lockForUpdate()->first();

            if ($amount->greaterThan($this->balance($publisher, $amount->currency()))) {
                throw ValidationException::withMessages(['amount' => 'The payout is larger than the balance owed.']);
            }

            $payout = new Payout;
            $payout->forceFill([
                'publisher_id' => $publisher->id,
                'currency' => $amount->currency(),
                'amount' => $amount->toDecimal(),
                'reference' => $reference,
                'notes' => $notes,
                'created_by' => $actor->id,
                'paid_at' => now(),
            ])->save();

            $zero = Money::zero($amount->currency());
            $entry = new LedgerEntry;
            $entry->forceFill([
                'publisher_id' => $publisher->id,
                'type' => LedgerEntry::PAYOUT,
                'payout_id' => $payout->id,
                'currency' => $amount->currency(),
                'gross' => $zero->toDecimal(),
                'commission' => $zero->toDecimal(),
                'net' => $amount->multipliedBy(-1)->toDecimal(),
                'description' => "Payout {$reference}",
                'created_at' => now(),
            ])->save();

            $this->audit->record('marketplace.payout_recorded', null, ['publisher' => $publisher->slug, 'amount' => $amount->toDecimal(), 'currency' => $amount->currency(), 'reference' => $reference], $actor);

            return $payout;
        });
    }

    public function balance(Publisher $publisher, string $currency): Money
    {
        $sum = LedgerEntry::query()->where('publisher_id', $publisher->id)->where('currency', $currency)->sum('net');

        return Money::of(self::decimal($sum), $currency);
    }

    /**
     * @return array<string, array{gross: Money, commission: Money, net: Money}>
     */
    public function totals(Publisher $publisher): array
    {
        $totals = [];

        foreach (LedgerEntry::query()->where('publisher_id', $publisher->id)
            ->selectRaw('currency, sum(gross) as gross, sum(commission) as commission, sum(net) as net')
            ->groupBy('currency')->get() as $row) {
            $currency = (string) $row->getAttribute('currency');
            $totals[$currency] = [
                'gross' => Money::of(self::decimal($row->getAttribute('gross')), $currency),
                'commission' => Money::of(self::decimal($row->getAttribute('commission')), $currency),
                'net' => Money::of(self::decimal($row->getAttribute('net')), $currency),
            ];
        }

        return $totals;
    }

    /** Database sums come back as strings (MySQL) or floats (SQLite); amounts have 4 decimals. */
    private static function decimal(mixed $value): string
    {
        return is_string($value) ? $value : number_format((float) $value, 4, '.', '');
    }

    private function exists(MarketplaceOrderItem $item, string $type): bool
    {
        return LedgerEntry::query()->where('order_item_id', $item->id)->where('type', $type)->exists();
    }

    private function write(Publisher $publisher, string $type, ?int $itemId, ?int $payoutId, Money $gross, Money $commission, string $description): void
    {
        $entry = new LedgerEntry;
        $entry->forceFill([
            'publisher_id' => $publisher->id,
            'type' => $type,
            'order_item_id' => $itemId,
            'payout_id' => $payoutId,
            'currency' => $gross->currency(),
            'gross' => $gross->toDecimal(),
            'commission' => $commission->toDecimal(),
            'net' => $gross->minus($commission)->toDecimal(),
            'description' => mb_substr($description, 0, 255),
            'created_at' => now(),
        ])->save();
    }
}
