<?php

namespace App\Http\Controllers\Admin;

use App\Core\Audit\AuditLog;
use App\Core\Settings\StoreSettings;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AuditLogResource;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, StoreSettings $store): Response
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

        return Inertia::render('admin/dashboard', [
            'store' => $store->toArray(),
            'stats' => [
                'staff' => User::query()->whereHas('roles')->count(),
                'active_staff' => User::query()->whereHas('roles')->whereNull('disabled_at')->count(),
                'customers' => User::query()->whereDoesntHave('roles')->count(),
            ],
            'recentActivity' => $user->can('audit.view')
                ? AuditLogResource::collection(AuditLog::query()->latest('id')->limit(8)->get())->resolve()
                : null,
            'warnings' => $warnings,
        ]);
    }
}
