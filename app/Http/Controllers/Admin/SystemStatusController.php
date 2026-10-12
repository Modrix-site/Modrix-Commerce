<?php

namespace App\Http\Controllers\Admin;

use App\Core\Installation\InstallationState;
use App\Core\Installation\RequirementChecker;
use App\Core\Operations\HealthCheck;
use App\Http\Controllers\Controller;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Operational overview for administrators. Shows driver names and health,
 * never credentials, hostnames or absolute paths.
 */
class SystemStatusController extends Controller
{
    public function __invoke(RequirementChecker $requirements, InstallationState $installation, HealthCheck $health): Response
    {
        $freeBytes = @disk_free_space(storage_path());

        return Inertia::render('admin/system/status', [
            'environment' => [
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
            'checks' => $requirements->check(),
            'health' => $health->run(),
            'warnings' => array_values(array_filter([
                config('app.debug') && app()->isProduction() ? 'Debug mode is enabled in production.' : null,
                app()->isProduction() && ! config('session.secure') ? 'SESSION_SECURE_COOKIE should be true when serving over HTTPS.' : null,
                config('queue.default') === 'sync' && app()->isProduction() ? 'The sync queue runs jobs inside web requests. Configure a queue worker.' : null,
            ])),
        ]);
    }
}
