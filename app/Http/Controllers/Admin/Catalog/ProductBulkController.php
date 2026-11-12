<?php

namespace App\Http\Controllers\Admin\Catalog;

use App\Core\Audit\AuditLogger;
use App\Core\Settings\StoreSettings;
use App\Domains\Catalog\Actions\ProductBulkActions;
use App\Domains\Catalog\Csv\ProductCsv;
use App\Domains\Catalog\Models\ProductTag;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Product list → bulk actions on the selected products.
 */
class ProductBulkController extends Controller
{
    public function __invoke(Request $request, ProductBulkActions $actions, ProductCsv $csv, StoreSettings $store, AuditLogger $audit): Response
    {
        $data = $request->validate([
            'action' => ['required', 'string', Rule::in([...ProductBulkActions::ACTIONS, 'export'])],
            'ids' => ['required', 'array', 'min:1', 'max:'.ProductBulkActions::MAX],
            'ids.*' => ['integer', 'distinct'],
        ]);
        $action = $data['action'];

        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can(match ($action) {
            'delete' => 'products.delete',
            'export' => 'products.view',
            default => 'products.update',
        }), 403);

        $ids = array_values(array_map('intval', $data['ids']));

        if ($action === 'export') {
            return $this->export($ids, $csv, $store, $audit);
        }

        $result = $actions->run($action, $ids, $request->validate($this->rules($action)), $user);
        Inertia::flash('toast', [
            'type' => $result->skipped === [] ? 'success' : ($result->updated > 0 ? 'warning' : 'error'),
            'message' => $result->message(),
        ]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(string $action): array
    {
        return match ($action) {
            'add_category', 'remove_category' => ['category_id' => ['required', 'integer', Rule::exists('categories', 'id')]],
            'set_brand' => ['brand_id' => ['nullable', 'integer', Rule::exists('brands', 'id')]],
            'add_tags', 'remove_tags' => [
                'tags' => ['required', 'array', 'min:1', 'max:'.ProductTag::MAX_PER_PRODUCT],
                'tags.*' => ['string', 'max:'.ProductTag::MAX_LENGTH],
            ],
            'adjust_prices' => [
                'direction' => ['required', Rule::in(['increase', 'decrease'])],
                'mode' => ['required', Rule::in(['percent', 'fixed'])],
                'value' => ['required', 'numeric', 'gt:0', 'regex:/^\d{1,13}(\.\d{1,4})?$/', ...$this->percentLimit()],
            ],
            'set_stock' => ['quantity' => ['required', 'integer', 'min:0', 'max:1000000']],
            default => [],
        };
    }

    /**
     * Percentages: up to 1000% up, at most 99% down.
     *
     * @return list<mixed>
     */
    private function percentLimit(): array
    {
        $request = request();

        if ($request->input('mode') !== 'percent') {
            return [];
        }

        return [$request->input('direction') === 'decrease' ? 'max:99' : 'max:1000', 'min:0.01'];
    }

    /**
     * @param  list<int>  $ids
     */
    private function export(array $ids, ProductCsv $csv, StoreSettings $store, AuditLogger $audit): StreamedResponse
    {
        $audit->record('product.bulk_export', null, ['ids' => $ids]);

        return response()->streamDownload(function () use ($csv, $store, $ids) {
            $out = fopen('php://output', 'wb');

            if ($out === false) {
                return;
            }

            fwrite($out, "\u{FEFF}");

            foreach ($csv->export($store->currency(), $ids) as $row) {
                fputcsv($out, $row, escape: '');
            }

            fclose($out);
        }, 'products-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
