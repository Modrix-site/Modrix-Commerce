<?php

namespace App\Domains\Orders\Documents;

use App\Core\Audit\AuditLogger;
use App\Core\Media\Media;
use App\Core\Settings\SettingsRepository;
use App\Domains\Orders\OrderEmailSettings;
use App\Domains\Orders\OrderNumbers;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class DocumentPreferences
{
    private const LOGO_TYPES = ['image/png', 'image/jpeg', 'image/gif'];

    public function __construct(private readonly DocumentSettings $settings, private readonly InvoiceNumbers $numbers, private readonly OrderEmailSettings $emails, private readonly OrderNumbers $orders, private readonly AuditLogger $audit) {}

    /** @return array<string,mixed> */
    public function props(): array
    {
        $settings = $this->settings;
        $numbers = $this->numbers;
        $emails = $this->emails;
        $values = $settings->toArray();

        return [
            'settings' => $values,
            'orderSettings' => $this->orders->toArray(),
            'orderPreview' => $this->orders->format($this->orders->toArray()['next_order_number']),
            'preview' => $numbers->format($values['next_invoice_number']),
            'emails' => $emails->all(),
            'emailKinds' => collect(OrderEmailSettings::KINDS)->map(fn (string $label, string $kind) => ['kind' => $kind, 'label' => $label])->values(),
            'logos' => Media::query()
                ->whereIn('mime', ['image/png', 'image/jpeg', 'image/gif'])
                ->latest('id')
                ->limit(100)
                ->get()
                ->map(fn (Media $m) => ['id' => $m->id, 'filename' => $m->filename, 'url' => $m->url()])
                ->values(),

        ];
    }

    public function save(Request $request): void
    {
        $settings = $this->settings;
        $emails = $this->emails;
        $audit = $this->audit;
        $data = $request->validate([
            'order_prefix' => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^(?:[A-Za-z0-9_-]|\{year\})*$/'],
            'order_padding' => ['sometimes', 'required', 'integer', 'between:3,8'],
            'next_order_number' => ['sometimes', 'required', 'integer', 'min:1', 'max:999999999'],
            'loaded_next_order_number' => ['nullable', 'integer'],
            'invoice_prefix' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9\-\/_{}]*$/'],
            'invoice_padding' => ['required', 'integer', 'between:3,8'],
            'next_invoice_number' => ['required', 'integer', 'min:1', 'max:999999999'],
            'loaded_next_invoice_number' => ['nullable', 'integer'],
            'tax_id' => ['nullable', 'string', 'max:60'],
            'seller_address' => ['nullable', 'string', 'max:500'],
            'footer' => ['nullable', 'string', 'max:1000'],
            'logo_media_id' => ['nullable', 'integer', Rule::exists('media', 'id')->whereIn('mime', self::LOGO_TYPES)],
            'emails' => ['sometimes', 'array'],
            'emails.*' => ['boolean'],
        ], [
            'invoice_prefix.regex' => 'Use letters, numbers, - / _ and {year} only.',
        ]);

        // Invoices may have been issued since the page was opened: an unchanged
        // number field means "keep the counter", not "move it back".
        if (isset($data['loaded_next_invoice_number']) && (int) $data['loaded_next_invoice_number'] === (int) $data['next_invoice_number']) {
            unset($data['next_invoice_number']);
        }
        unset($data['loaded_next_invoice_number']);

        /** @var array<string, mixed> $emailChoices */
        $emailChoices = $data['emails'] ?? [];
        unset($data['emails']);
        $emailsBefore = $emails->all();
        $emails->update($emailChoices);

        if (isset($data['loaded_next_order_number'], $data['next_order_number']) && (int) $data['loaded_next_order_number'] === (int) $data['next_order_number']) {
            unset($data['next_order_number']);
        }
        unset($data['loaded_next_order_number']);
        $orderBefore = $this->orders->toArray();
        $this->orders->update($data);
        $before = $settings->toArray();
        $settings->update($data);
        if (array_key_exists('seller_address', $data)) {
            app(SettingsRepository::class)->set('store.address', $data['seller_address']);
        }
        $after = $settings->toArray();

        $changes = array_keys(array_filter($after, fn ($value, $key) => $before[$key] !== $value, ARRAY_FILTER_USE_BOTH));
        foreach ($emails->all() as $kind => $on) {
            if ($emailsBefore[$kind] !== $on) {
                $changes[] = "email.{$kind}";
            }
        }
        if ($orderBefore !== $this->orders->toArray()) {
            $changes[] = 'order_numbering';
        }
        if ($changes !== []) {
            $audit->record('settings.documents_updated', null, ['changed' => $changes]);
        }

    }
}
