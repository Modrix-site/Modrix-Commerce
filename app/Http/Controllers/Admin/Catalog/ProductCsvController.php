<?php

namespace App\Http\Controllers\Admin\Catalog;

use App\Core\Audit\AuditLogger;
use App\Core\Settings\StoreSettings;
use App\Domains\Catalog\Csv\ProductCsv;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductCsvController extends Controller
{
    public const SESSION_ERRORS = 'csv_import_errors';

    public function export(Request $request, ProductCsv $csv, StoreSettings $store, AuditLogger $audit): StreamedResponse
    {
        $audit->record('catalog.csv_exported');
        $filename = 'products-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($csv, $store) {
            $out = fopen('php://output', 'wb');

            if ($out === false) {
                return;
            }

            fwrite($out, "\u{FEFF}"); // UTF-8 BOM so spreadsheet apps detect the encoding

            foreach ($csv->export($store->currency()) as $row) {
                fputcsv($out, $row, escape: '');
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function import(Request $request, ProductCsv $csv, StoreSettings $store): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        /** @var User $actor */
        $actor = $request->user();
        $result = $csv->import(
            (string) $request->file('file')?->getRealPath(),
            $store->currency(),
            $actor,
            $actor->can('inventory.manage'),
            (int) config('ecomodular.csv_import_max_rows', 5000),
        );

        if (! $result->ok) {
            $request->session()->flash(self::SESSION_ERRORS, array_slice($result->errors, 0, 100));
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Import failed. No changes were made.')]);

            return redirect()->route('admin.products.index');
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Import complete: :created created, :updated updated.', [
            'created' => $result->created,
            'updated' => $result->updated,
        ])]);

        return redirect()->route('admin.products.index');
    }
}
