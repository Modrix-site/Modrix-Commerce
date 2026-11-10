<?php

namespace EcoModular\SalesWidget;

use App\Core\Extensions\DashboardWidgets;
use App\Core\Extensions\ModuleSettingsSchema;
use App\Core\Settings\StoreSettings;
use App\Domains\Pricing\Money;
use App\Domains\Reports\SalesReport;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Support\ServiceProvider;

/**
 * A dashboard widget built on the core SalesReport (same figures as the
 * Reports page). Shown only to staff with `reports.view`.
 */
class SalesWidgetServiceProvider extends ServiceProvider
{
    public const MODULE = 'modrixcommerce/sales-widget';

    public function boot(DashboardWidgets $widgets): void
    {
        $widgets->register('sales-trend', self::MODULE, 'Sales trend', 'SalesTrend', 'reports.view', function () {
            $store = $this->app->make(StoreSettings::class);
            $days = (int) $this->app->make(ModuleSettingsSchema::class)->of(self::MODULE, 'days', 7);
            $days = max(7, min(30, $days));
            $now = CarbonImmutable::now($store->timezone());

            $report = $this->app->make(SalesReport::class)->build($now->subDays($days - 1), $now, $store->timezone(), $store->currency());

            /** @var list<array{date: string, orders: int, sales: Money}> $series */
            $series = $report['series'];
            $max = array_reduce($series, fn (Money $carry, array $day) => $day['sales']->greaterThan($carry) ? $day['sales'] : $carry, Money::zero($store->currency()));

            return [
                'days' => $days,
                'total' => $report['gross_sales'],
                'series' => array_map(fn (array $day) => [
                    'date' => $day['date'],
                    'orders' => $day['orders'],
                    'sales' => $day['sales'],
                    // Bar length as a whole percentage of the best day, computed exactly.
                    'percent' => $max->isZero() ? 0 : (int) $day['sales']->amount()->multipliedBy(100)->dividedBy($max->amount(), 0, RoundingMode::DOWN)->toInt(),
                ], $series),
            ];
        }, order: 50);
    }
}
