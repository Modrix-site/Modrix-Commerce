<?php

namespace App\Domains\Orders\Documents;

use App\Core\Settings\SettingsRepository;
use App\Core\Settings\StoreSettings;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * The gap-free invoice sequence. A number is taken under a row lock inside
 * the caller's transaction, so it is only consumed when the invoice that
 * uses it commits, and two concurrent issues can never share a number.
 */
final class InvoiceNumbers
{
    public const COUNTER = 'invoice';

    public const DEFAULT_PREFIX = 'INV-{year}-';

    public const DEFAULT_PADDING = 5;

    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly StoreSettings $store,
    ) {}

    /**
     * @return array{sequence: int, number: string}
     */
    public function take(): array
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Invoice numbers must be taken inside a transaction.');
        }

        $sequence = (int) DB::table('document_counters')->where('name', self::COUNTER)->lockForUpdate()->value('next');
        DB::table('document_counters')->where('name', self::COUNTER)->update(['next' => $sequence + 1]);

        return ['sequence' => $sequence, 'number' => $this->format($sequence)];
    }

    public function next(): int
    {
        return (int) DB::table('document_counters')->where('name', self::COUNTER)->value('next');
    }

    public function setNext(int $next): void
    {
        DB::table('document_counters')->where('name', self::COUNTER)->update(['next' => $next]);
    }

    public function format(int $sequence): string
    {
        $prefix = (string) $this->settings->get('documents.invoice_prefix', self::DEFAULT_PREFIX);
        $padding = (int) $this->settings->get('documents.invoice_padding', self::DEFAULT_PADDING);

        return str_replace('{year}', now($this->store->timezone())->format('Y'), $prefix)
            .str_pad((string) $sequence, $padding, '0', STR_PAD_LEFT);
    }
}
