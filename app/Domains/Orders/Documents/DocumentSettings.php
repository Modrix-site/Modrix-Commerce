<?php

namespace App\Domains\Orders\Documents;

use App\Core\Settings\SettingsRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Admin → Settings → Documents: invoice numbering and the seller details
 * printed on invoices, receipts and packing slips.
 */
final class DocumentSettings
{
    private const KEYS = [
        'invoice_prefix' => 'documents.invoice_prefix',
        'invoice_padding' => 'documents.invoice_padding',
        'tax_id' => 'documents.tax_id',
        'seller_address' => 'documents.seller_address',
        'footer' => 'documents.footer',
        'logo_media_id' => 'documents.logo_media_id',
    ];

    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly InvoiceNumbers $numbers,
    ) {}

    /**
     * @return array{invoice_prefix: string, invoice_padding: int, next_invoice_number: int, tax_id: string|null, seller_address: string|null, footer: string|null, logo_media_id: int|null}
     */
    public function toArray(): array
    {
        $logo = $this->settings->get(self::KEYS['logo_media_id']);

        return [
            'invoice_prefix' => (string) $this->settings->get(self::KEYS['invoice_prefix'], InvoiceNumbers::DEFAULT_PREFIX),
            'invoice_padding' => (int) $this->settings->get(self::KEYS['invoice_padding'], InvoiceNumbers::DEFAULT_PADDING),
            'next_invoice_number' => $this->numbers->next(),
            'tax_id' => $this->nullable(self::KEYS['tax_id']),
            'seller_address' => $this->nullable(self::KEYS['seller_address']),
            'footer' => $this->nullable(self::KEYS['footer']),
            'logo_media_id' => $logo === null ? null : (int) $logo,
        ];
    }

    /**
     * Saves the given values (any subset). The next invoice number may only
     * move up, so an existing series is continued and never reused.
     *
     * @param  array<string, mixed>  $values
     */
    public function update(array $values): void
    {
        DB::transaction(function () use ($values) {
            if (array_key_exists('next_invoice_number', $values)) {
                $current = (int) DB::table('document_counters')->where('name', InvoiceNumbers::COUNTER)->lockForUpdate()->value('next');
                $next = (int) $values['next_invoice_number'];

                if ($next < $current) {
                    throw ValidationException::withMessages(['next_invoice_number' => "The next invoice number cannot be lower than {$current}: numbers already issued must not be reused."]);
                }

                $this->numbers->setNext($next);
            }

            $settings = [];
            foreach (self::KEYS as $field => $key) {
                if (array_key_exists($field, $values)) {
                    $value = $values[$field];
                    $settings[$key] = is_string($value) && trim($value) === '' ? null : $value;
                }
            }

            if ($settings !== []) {
                $this->settings->setMany($settings);
            }
        });
    }

    private function nullable(string $key): ?string
    {
        $value = $this->settings->get($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
