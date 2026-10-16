<?php

namespace App\Http\Controllers\Admin;

use App\Core\Audit\AuditLogger;
use App\Core\Settings\StoreSettings;
use App\Domains\Orders\Csv\OrderCsv;
use App\Domains\Orders\Documents\OrderDocuments;
use App\Domains\Orders\Enums\FulfillmentStatus;
use App\Domains\Orders\Models\Invoice;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\OrderFilters;
use App\Domains\Orders\OrderWorkflow;
use App\Http\Controllers\Controller;
use App\Http\Responses\PdfDownload;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Actions on several orders at once. Every order goes through the same
 * workflow rules as a single change; orders that cannot move are skipped
 * and reported, never forced.
 */
class OrderBulkController extends Controller
{
    public const MAX = 100;

    /** Documents are rendered while the browser waits, so bundles stay small. */
    public const MAX_DOCUMENTS = 30;

    public function fulfillment(Request $request, OrderWorkflow $workflow): RedirectResponse
    {
        $data = $request->validate([
            ...$this->numberRules(),
            'status' => ['required', Rule::enum(FulfillmentStatus::class)->except([FulfillmentStatus::Unfulfilled, FulfillmentStatus::DeliveryFailed, FulfillmentStatus::Returned])],
            'notify' => ['nullable', 'boolean'],
        ]);
        $target = FulfillmentStatus::from($data['status']);
        $notify = isset($data['notify']) ? (bool) $data['notify'] : null;
        /** @var User $actor */
        $actor = $request->user();

        $orders = $this->orders($data['numbers']);
        $moved = 0;
        $skipped = [];

        foreach ($data['numbers'] as $number) {
            $order = $orders->get($number);

            if ($order === null) {
                $skipped[] = "{$number} (not found)";

                continue;
            }

            try {
                $workflow->advanceFulfillment($order, $actor, $target, $notify);
                $moved++;
            } catch (ValidationException $e) {
                $skipped[] = "{$number} (".rtrim(Str::lower(Str::limit((string) (Arr::flatten($e->errors())[0] ?? ''), 80)), '.').')';
            }
        }

        $message = trans_choice('Moved :count order to :status.|Moved :count orders to :status.', $moved, ['status' => $target->label()]);
        if ($skipped !== []) {
            $message .= ' '.__('Skipped :count: :list.', ['count' => count($skipped), 'list' => implode(', ', array_slice($skipped, 0, 10)).(count($skipped) > 10 ? ', …' : '')]);
        }

        Inertia::flash('toast', ['type' => $skipped === [] ? 'success' : ($moved > 0 ? 'warning' : 'error'), 'message' => $message]);

        return back();
    }

    public function documents(Request $request, OrderDocuments $documents): Response
    {
        $data = $request->validate([
            ...$this->numberRules(self::MAX_DOCUMENTS),
            'type' => ['required', Rule::in(['packing-slips', 'invoices'])],
        ]);
        // Rendering takes about a second per page. Not in tests: the limit would
        // apply to the rest of the PHPUnit process.
        if (! app()->runningUnitTests()) {
            set_time_limit(120);
        }

        $orders = $this->orders($data['numbers']);
        // In the order staff selected them.
        $ordered = $orders->sortBy(fn (Order $o) => array_search($o->number, $data['numbers'], true))->values();

        if ($data['type'] === 'invoices') {
            $invoices = $ordered->map(fn (Order $o) => $o->invoice)->filter(fn ($i) => $i instanceof Invoice)->values();
            abort_if($invoices->isEmpty(), 404);

            return PdfDownload::make($documents->invoicesPdf($invoices), 'invoices-'.now()->format('Ymd-His').'.pdf');
        }

        abort_if($ordered->isEmpty(), 404);

        return PdfDownload::make($documents->packingSlipsPdf($ordered), 'packing-slips-'.now()->format('Ymd-His').'.pdf');
    }

    public function export(Request $request, OrderCsv $csv, StoreSettings $store, AuditLogger $audit): StreamedResponse
    {
        $filters = $request->validate([
            ...OrderFilters::rules(),
            'numbers' => ['nullable', 'array', 'max:'.self::MAX],
            'numbers.*' => ['string', 'max:40'],
        ]);
        $timezone = $store->timezone();

        $query = OrderFilters::query(isset($filters['numbers']) ? [] : $filters, $timezone)
            ->when($filters['numbers'] ?? null, fn ($q, array $numbers) => $q->whereIn('number', $numbers));

        $audit->record('orders.exported', null, ['selected' => count($filters['numbers'] ?? []), 'filters' => array_diff_key($filters, ['numbers' => true])]);

        return response()->streamDownload(function () use ($csv, $query, $timezone) {
            $out = fopen('php://output', 'wb');

            if ($out !== false) {
                $csv->write($out, $query, $timezone);
                fclose($out);
            }
        }, 'orders-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array<string, list<string>>
     */
    private function numberRules(int $max = self::MAX): array
    {
        return [
            'numbers' => ['required', 'array', 'min:1', 'max:'.$max],
            'numbers.*' => ['required', 'string', 'max:40'],
        ];
    }

    /**
     * @param  list<string>  $numbers
     * @return Collection<string, Order>
     */
    private function orders(array $numbers): Collection
    {
        return Order::query()->with('invoice')->whereIn('number', array_unique($numbers))->get()->keyBy('number');
    }
}
