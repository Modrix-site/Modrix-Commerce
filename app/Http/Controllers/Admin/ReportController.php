<?php

namespace App\Http\Controllers\Admin;

use App\Core\Settings\StoreSettings;
use App\Domains\Reports\SalesReport;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function index(Request $request, SalesReport $report, StoreSettings $store): Response
    {
        $timezone = $store->timezone();
        $currencies = $report->currencies($store->currency());
        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'currency' => ['nullable', 'string', Rule::in($currencies)],
        ]);

        $to = isset($data['to']) ? CarbonImmutable::parse($data['to'], $timezone) : CarbonImmutable::now($timezone);
        $from = isset($data['from']) ? CarbonImmutable::parse($data['from'], $timezone) : $to->subDays(29);

        if ($from->greaterThan($to)) {
            throw ValidationException::withMessages(['from' => 'The start date must be before the end date.']);
        }

        if ($from->diffInDays($to) > SalesReport::MAX_DAYS) {
            throw ValidationException::withMessages(['from' => 'Reports can cover at most '.SalesReport::MAX_DAYS.' days.']);
        }

        $reports = collect($currencies)->mapWithKeys(fn (string $currency) => [$currency => $report->build($from, $to, $timezone, $currency)]);
        $currency = $data['currency'] ?? $store->currency();
        if (! isset($data['currency']) && $reports[$currency]['total_orders'] === 0 && $reports[$currency]['payment_count'] === 0) {
            $currency = $reports->sortByDesc(fn ($row) => $row['total_orders'] + $row['payment_count'])->keys()->first() ?? $currency;
        }

        return Inertia::render('admin/reports/index', [
            'report' => $reports[$currency],
            'storeCurrency' => $store->currency(),
            'today' => CarbonImmutable::now($timezone)->format('Y-m-d'),
            'currencies' => $reports->map(fn ($row, $code) => ['code' => $code, 'orders' => $row['total_orders'], 'sales' => $row['gross_sales'], 'payments' => $row['payments_received']])->values()->all(),
        ]);
    }
}
