<?php

namespace App\Http\Controllers\Admin;

use App\Core\Audit\AuditLog;
use App\Core\Extensions\DashboardWidgets;
use App\Core\Marketplace\ModrixCloudPrompt;
use App\Core\Onboarding\SetupGuide;
use App\Core\Settings\StoreSettings;
use App\Domains\Inventory\InventoryService;
use App\Domains\Orders\Models\Order;
use App\Domains\Reports\SalesReport;
use App\Http\Controllers\Controller;
use App\Http\Presenters\OrderPresenter;
use App\Http\Resources\Admin\AuditLogResource;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, StoreSettings $store, SalesReport $report, InventoryService $inventory, OrderPresenter $presenter, DashboardWidgets $widgets): Response
    {
        /** @var User $user */
        $user = $request->user();

        $warnings = [];

        if (config('app.debug') && app()->isProduction()) {
            $warnings[] = 'APP_DEBUG is enabled in production. Disable it to avoid leaking error details.';
        }

        if (! $user->hasEnabledTwoFactorAuthentication() && $user->isOwner()) {
            $warnings[] = 'Two-factor authentication is not enabled on your owner account.';
        }

        $timezone = $store->timezone();
        $now = CarbonImmutable::now($timezone);
        $canSeeSales = $user->can('reports.view');
        $canSeeOrders = $user->can('orders.view');

        return Inertia::render('admin/dashboard', [
            'store' => $store->toArray(),
            'sales' => $canSeeSales ? [
                'today' => $this->headline($report->build($now, $now, $timezone, $store->currency())),
                'last30' => $this->headline($report->build($now->subDays(29), $now, $timezone, $store->currency())),
            ] : null,
            'operations' => ($canSeeOrders || $user->can('inventory.view')) ? $report->operationalCounts($inventory) : null,
            'recentOrders' => $canSeeOrders
                ? Order::query()->latest('placed_at')->limit(8)->get()->map(fn (Order $o) => $presenter->summary($o))
                : null,
            'recentActivity' => $user->can('audit.view')
                ? AuditLogResource::collection(AuditLog::query()->latest('id')->limit(8)->get())->resolve()
                : null,
            'warnings' => $warnings,
            'widgets' => $widgets->forUser($user),
            'cloudPrompt' => app(ModrixCloudPrompt::class)->forUser($user),
            'setupGuide' => app(SetupGuide::class)->forUser($user),
        ]);
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    private function headline(array $report): array
    {
        return [
            'gross_sales' => $report['gross_sales'],
            'orders' => $report['orders'],
            'average_order_value' => $report['average_order_value'],
        ];
    }
}
