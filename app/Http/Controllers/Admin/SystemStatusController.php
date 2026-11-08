<?php

namespace App\Http\Controllers\Admin;

use App\Core\Installation\InstallationState;
use App\Core\Installation\RequirementChecker;
use App\Core\Operations\HealthCheck;
use App\Core\Platform;
use App\Http\Controllers\Controller;
use App\Core\Settings\SettingsRepository;
use App\Core\Updates\CoreReleaseVerifier;
use App\Core\Updates\CoreUpdateCheck;
use App\Core\Updates\CoreUpdateClient;
use App\Core\Updates\CoreUpdater;
use App\Core\Updates\CoreUpdateRun;
use App\Models\User;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Operational overview for administrators. Shows driver names and health,
 * never credentials, hostnames or absolute paths.
 */
class SystemStatusController extends Controller
{
    public function __invoke(Request $request, RequirementChecker $requirements, InstallationState $installation, HealthCheck $health, CoreUpdateCheck $coreCheck, CoreUpdateClient $coreClient, SettingsRepository $settings): Response
    {
        $freeBytes = @disk_free_space(storage_path());

        return Inertia::render('admin/system/status', [
            'environment' => [
                ['label' => 'Modrix Commerce', 'value' => Platform::VERSION],
                ['label' => 'Application environment', 'value' => app()->environment()],
                ['label' => 'Debug mode', 'value' => config('app.debug') ? 'Enabled' : 'Disabled'],
                ['label' => 'Laravel', 'value' => Application::VERSION],
                ['label' => 'PHP', 'value' => PHP_VERSION],
                ['label' => 'Database driver', 'value' => DB::connection()->getDriverName()],
                ['label' => 'Cache store', 'value' => (string) config('cache.default')],
                ['label' => 'Queue connection', 'value' => (string) config('queue.default')],
                ['label' => 'Session driver', 'value' => (string) config('session.driver')],
                ['label' => 'Secure session cookie', 'value' => config('session.secure') ? 'Yes' : 'No'],
                ['label' => 'Mailer', 'value' => (string) config('mail.default')],
                ['label' => 'Filesystem disk', 'value' => (string) config('filesystems.default')],
                ['label' => 'Installed at', 'value' => $installation->installedAt() ?? 'Not installed'],
                ['label' => 'Free disk space (storage)', 'value' => $freeBytes === false ? 'Unknown' : number_format($freeBytes / 1024 ** 3, 1).' GB'],
            ],
            'coreUpdate' => $this->coreUpdate($request->user(), $coreCheck, $coreClient, $settings, app(CoreReleaseVerifier::class)),
            'checks' => $requirements->check(),
            'health' => $health->run(),
            'warnings' => array_values(array_filter([
                config('app.debug') && app()->isProduction() ? 'Debug mode is enabled in production.' : null,
                app()->isProduction() && ! config('session.secure') ? 'SESSION_SECURE_COOKIE should be true when serving over HTTPS.' : null,
                config('queue.default') === 'sync' && app()->isProduction() ? 'The sync queue runs jobs inside web requests. Configure a queue worker.' : null,
            ])),
        ]);
    }

    /** @return array<string, mixed> */
    private function coreUpdate(?User $user, CoreUpdateCheck $check, CoreUpdateClient $client, SettingsRepository $settings, CoreReleaseVerifier $verifier): array
    {
        $available = $check->available();
        // A stored result can predate the install of that very version.
        if (! is_string($available['version'] ?? null) || ! $verifier->newer($available['version'], Platform::VERSION)) {
            $available = null;
        }
        $available = $available === null ? null : Arr::except($available, ['item', 'verification']);
        $connected = $client->connected();
        // Until `migrate` has run there is no run history; the Migrations health check flags it.
        $migrated = Schema::hasTable((new CoreUpdateRun)->getTable());
        $active = $migrated ? CoreUpdateRun::live() : null;
        $installer = $user?->can('system.update') === true;

        return [
            'current' => Platform::VERSION,
            'available' => $available,
            'checked_at' => $settings->get('core_update.checked_at'),
            'last_error' => $settings->get('core_update.last_error'),
            'connected' => $connected,
            'can_install' => $migrated && $available !== null && $connected && $active === null && $installer,
            'connect_url' => Route::has('admin.modrixcommerce.cloud-connector.index') ? route('admin.modrixcommerce.cloud-connector.index', absolute: false) : null,
            'active_run' => $active === null ? null : [
                'id' => $active->id,
                'status' => $active->status,
                'hint' => $active->startHint(),
                // `/<secret>` sets the maintenance bypass cookie, so the installer can follow the run.
                'bypass_url' => $installer ? url('/'.app(CoreUpdater::class)->bypassSecret($active)) : null,
            ],
            'runs' => ! $migrated ? [] : CoreUpdateRun::query()->latest('id')->limit(5)->get()->map(fn (CoreUpdateRun $run) => [
                'id' => $run->id,
                'from_version' => $run->from_version,
                'to_version' => $run->to_version,
                'status' => $run->status,
                'message' => $run->message,
                'needs_attention' => (bool) $run->needs_attention,
                'created_at' => $run->created_at?->toIso8601String(),
            ])->all(),
        ];
    }
}
