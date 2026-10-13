<?php

namespace App\Http\Controllers\Admin;

use App\Core\Audit\AuditLogger;
use App\Core\Media\Media;
use App\Domains\Orders\Documents\DocumentSettings;
use App\Domains\Orders\Documents\InvoiceNumbers;
use App\Domains\Orders\OrderEmailSettings;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin → Settings → Documents: invoice numbering and the seller details
 * printed on invoices, receipts and packing slips.
 */
class DocumentSettingsController extends Controller
{
    private const LOGO_TYPES = ['image/png', 'image/jpeg', 'image/gif'];

    public function edit(DocumentSettings $settings, InvoiceNumbers $numbers, OrderEmailSettings $emails): Response
    {
        $values = $settings->toArray();

        return Inertia::render('admin/settings/documents', [
            'settings' => $values,
            'preview' => $numbers->format($values['next_invoice_number']),
            'emails' => $emails->all(),
            'emailKinds' => collect(OrderEmailSettings::KINDS)->map(fn (string $label, string $kind) => ['kind' => $kind, 'label' => $label])->values(),
            'logos' => Media::query()
                ->whereIn('mime', self::LOGO_TYPES)
                ->latest('id')
                ->limit(100)
                ->get()
                ->map(fn (Media $m) => ['id' => $m->id, 'filename' => $m->filename, 'url' => $m->url()])
                ->values(),
        ]);
    }

    public function update(Request $request, DocumentSettings $settings, OrderEmailSettings $emails, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
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

        $before = $settings->toArray();
        $settings->update($data);
        $after = $settings->toArray();

        $changes = array_keys(array_filter($after, fn ($value, $key) => $before[$key] !== $value, ARRAY_FILTER_USE_BOTH));
        foreach ($emails->all() as $kind => $on) {
            if ($emailsBefore[$kind] !== $on) {
                $changes[] = "email.{$kind}";
            }
        }
        if ($changes !== []) {
            $audit->record('settings.documents_updated', null, ['changed' => $changes]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Document settings saved.')]);

        return redirect()->route('admin.settings.documents.edit');
    }
}
