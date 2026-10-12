<?php

namespace App\Http\Controllers\Admin;

use App\Core\Settings\StoreSettings;
use App\Domains\Reports\SalesReport;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function index(Request $request, SalesReport $report, StoreSettings $store): Response
    {
        $timezone = $store->timezone();
        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $to = isset($data['to']) ? CarbonImmutable::parse($data['to'], $timezone) : CarbonImmutable::now($timezone);
        $from = isset($data['from']) ? CarbonImmutable::parse($data['from'], $timezone) : $to->subDays(29);

        if ($from->greaterThan($to)) {
            throw ValidationException::withMessages(['from' => 'The start date must be before the end date.']);
        }

        if ($from->diffInDays($to) > SalesReport::MAX_DAYS) {
            throw ValidationException::withMessages(['from' => 'Reports can cover at most '.SalesReport::MAX_DAYS.' days.']);
        }

        return Inertia::render('admin/reports/index', [
            'report' => $report->build($from, $to, $timezone, $store->currency()),
        ]);
    }
}
