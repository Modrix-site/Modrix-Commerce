<?php

namespace App\Core\Operations;

use App\Core\Installation\RequirementChecker;
use App\Core\Marketplace\Client\StoreLicense;
use App\Core\Platform;
use App\Core\Settings\SettingsRepository;
use App\Core\Updates\CoreReleaseVerifier;
use App\Core\Updates\CoreUpdateRun;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * Readiness checks for monitoring: the admin status page, the /health
 * endpoint and `ecomodular:health`. Details never include credentials,
 * hostnames or absolute paths.
 */
final class HealthCheck
{
    public const OK = 'ok';

    public const WARNING = 'warning';

    public const FAILED = 'failed';

    public const HEARTBEAT_FILE = 'framework/ecomodular-schedule-heartbeat';

    public function __construct(
        private readonly RequirementChecker $requirements,
        private readonly Backups $backups,
    ) {}

    /**
     * @return list<array{key: string, label: string, status: string, detail: string}>
     */
    public function run(): array
    {
        $checks = [];

        foreach ([
            'database' => fn () => $this->database(),
            'migrations' => fn () => $this->migrations(),
            'cache' => fn () => $this->cache(),
            'storage' => fn () => $this->storage(),
            'disk' => fn () => $this->disk(),
            'queue' => fn () => $this->queue(),
            'scheduler' => fn () => $this->scheduler(),
            'backups' => fn () => $this->backupAge(),
            'webhooks' => fn () => $this->webhooks(),
            'advisories' => fn () => $this->advisories(),
            'core_updates' => fn () => $this->coreUpdates(),
            'configuration' => fn () => $this->configuration(),
        ] as $key => $check) {
            try {
                [$label, $status, $detail] = $check();
            } catch (Throwable $e) {
                [$label, $status, $detail] = [Str::headline($key), self::FAILED, 'Check failed: '.class_basename($e)];
            }

            $checks[] = ['key' => $key, 'label' => $label, 'status' => $status, 'detail' => $detail];
        }

        return $checks;
    }

    /**
     * @param  list<array{status: string}>  $checks
     */
    public static function overall(array $checks): string
    {
        $statuses = array_column($checks, 'status');

        return match (true) {
            in_array(self::FAILED, $statuses, true) => self::FAILED,
            in_array(self::WARNING, $statuses, true) => self::WARNING,
            default => self::OK,
        };
    }

    public static function beat(): void
    {
        @file_put_contents(storage_path(self::HEARTBEAT_FILE), (string) time());
    }

    /** @return array{string, string, string} */
    private function database(): array
    {
        $start = hrtime(true);

        try {
            DB::select('select 1');
        } catch (Throwable) {
            return ['Database', self::FAILED, 'Cannot connect.'];
        }

        $ms = (int) round((hrtime(true) - $start) / 1e6);

        return ['Database', $ms > 500 ? self::WARNING : self::OK, "Connected ({$ms} ms)"];
    }

    /** @return array{string, string, string} */
    private function migrations(): array
    {
        $pending = $this->requirements->pendingMigrations();

        return match (true) {
            $pending === null => ['Migrations', self::WARNING, 'Unable to inspect migrations.'],
            $pending === 0 => ['Migrations', self::OK, 'Up to date'],
            default => ['Migrations', self::FAILED, "{$pending} pending: run php artisan migrate --force"],
        };
    }

    /** @return array{string, string, string} */
    private function cache(): array
    {
        $key = 'ecomodular:health:'.Str::random(8);
        Cache::put($key, 'ok', 10);
        $ok = Cache::get($key) === 'ok';
        Cache::forget($key);

        return ['Cache', $ok ? self::OK : self::FAILED, $ok ? 'Read and write work ('.config('cache.default').')' : 'Cannot read back a written value.'];
    }

    /** @return array{string, string, string} */
    private function storage(): array
    {
        $bad = array_keys(array_filter([
            'storage/app' => storage_path('app'),
            'storage/framework' => storage_path('framework'),
            'storage/logs' => storage_path('logs'),
            'bootstrap/cache' => base_path('bootstrap/cache'),
        ], fn (string $path) => ! is_dir($path) || ! is_writable($path)));

        return ['Writable storage', $bad === [] ? self::OK : self::FAILED, $bad === [] ? 'Writable' : 'Not writable: '.implode(', ', $bad)];
    }

    /** @return array{string, string, string} */
    private function disk(): array
    {
        $free = @disk_free_space(storage_path());
        $total = @disk_total_space(storage_path());

        if ($free === false || $total === false || $total <= 0) {
            return ['Disk space', self::WARNING, 'Unknown'];
        }

        $percent = $free / $total * 100;
        $detail = sprintf('%.1f GB free (%.0f%%)', $free / 1024 ** 3, $percent);

        return ['Disk space', match (true) {
            $percent < (float) config('ecomodular.health.disk_fail_percent', 5) => self::FAILED,
            $percent < (float) config('ecomodular.health.disk_warn_percent', 10) => self::WARNING,
            default => self::OK,
        }, $detail];
    }

    /** @return array{string, string, string} */
    private function queue(): array
    {
        $connection = (string) config('queue.default');

        if ($connection === 'sync') {
            return ['Queue', app()->isProduction() ? self::WARNING : self::OK, 'sync: jobs run inside web requests'];
        }

        $failed = Schema::hasTable('failed_jobs')
            ? DB::table('failed_jobs')->where('failed_at', '>=', now()->subDay())->count()
            : 0;

        $stale = 0;
        if (config("queue.connections.{$connection}.driver") === 'database' && Schema::hasTable('jobs')) {
            $stale = DB::table('jobs')->whereNull('reserved_at')->where('available_at', '<', now()->subMinutes(15)->getTimestamp())->count();
        }

        if ($stale > 0) {
            return ['Queue', self::WARNING, "{$stale} jobs waiting over 15 minutes: is a worker running?"];
        }

        return ['Queue', $failed > 0 ? self::WARNING : self::OK, $failed > 0 ? "{$failed} failed jobs in the last 24 hours" : "{$connection}: no failed jobs in the last 24 hours"];
    }

    /** @return array{string, string, string} */
    private function scheduler(): array
    {
        $file = storage_path(self::HEARTBEAT_FILE);
        $last = is_file($file) ? (int) file_get_contents($file) : 0;

        if ($last === 0) {
            return ['Scheduler', self::WARNING, 'No heartbeat yet: add the schedule:run cron entry.'];
        }

        $minutes = intdiv(time() - $last, 60);

        return ['Scheduler', $minutes > 5 ? self::WARNING : self::OK, $minutes > 5 ? "Last run {$minutes} minutes ago" : 'Running'];
    }

    /** @return array{string, string, string} */
    private function backupAge(): array
    {
        $latest = $this->backups->latest();

        if ($latest === null) {
            return ['Backups', self::WARNING, 'No backup yet: run php artisan ecomodular:backup'];
        }

        $hours = (int) floor((time() - $latest['created_at']) / 3600);
        $limit = (int) config('ecomodular.health.backup_max_age_hours', 26);

        return ['Backups', $hours > $limit ? self::WARNING : self::OK, "Latest {$hours} h ago ({$latest['name']})"];
    }

    /** @return array{string, string, string} */
    private function webhooks(): array
    {
        if (! Schema::hasTable('payment_webhook_events')) {
            return ['Payment webhooks', self::OK, 'Not used'];
        }

        $failed = DB::table('payment_webhook_events')->where('status', 'failed')->where('received_at', '>=', now()->subDay())->count();

        return ['Payment webhooks', $failed > 0 ? self::WARNING : self::OK, $failed > 0 ? "{$failed} failed in the last 24 hours (Admin → Payments → Events)" : 'No failures in the last 24 hours'];
    }

    /** @return array{string, string, string} */
    private function advisories(): array
    {
        if (! Schema::hasTable('marketplace_licenses')) {
            return ['Extension advisories', self::OK, 'None'];
        }

        $affected = [];
        foreach (StoreLicense::query()->get() as $license) {
            $installed = $license->installedVersion();
            if ($installed !== null && $license->advisoriesFor($installed) !== []) {
                $affected[] = $license->extension_name.' '.$installed;
            }
        }

        return $affected === []
            ? ['Extension advisories', self::OK, 'No installed Modrix Cloud package is affected']
            : ['Extension advisories', self::WARNING, 'Security advisory for '.implode(', ', $affected).': update under Admin → Modrix Cloud'];
    }

    /** @return array{string, string, string} */
    private function coreUpdates(): array
    {
        if (Schema::hasTable('core_update_runs')) {
            $completed = (int) CoreUpdateRun::query()->where('status', CoreUpdateRun::COMPLETED)->max('id');
            $broken = CoreUpdateRun::query()->where('needs_attention', true)->where('id', '>', $completed)->latest('id')->first();

            if ($broken !== null) {
                return ['Core updates', self::FAILED, "Core update #{$broken->id} failed and could not be fully undone: recover from code backup "
                    .($broken->code_backup_path ?? '-').' and database backup '.($broken->backup_name ?? '-').' (docs/operations/core-updates.md, Manual recovery)'];
            }

            $stuck = CoreUpdateRun::query()->whereNotIn('status', CoreUpdateRun::TERMINAL)
                ->where('updated_at', '<', now()->subMinutes(CoreUpdateRun::STUCK_MINUTES))->latest('id')->first();

            if ($stuck !== null) {
                $minutes = (int) $stuck->updated_at->diffInMinutes(now());

                return ['Core updates', self::WARNING, "Core update #{$stuck->id} has been {$stuck->status} for {$minutes} minutes without progress: see Stuck runs in docs/operations/core-updates.md"];
            }

            if (CoreUpdateRun::query()->latest('id')->value('status') === CoreUpdateRun::ROLLED_BACK) {
                return ['Core updates', self::WARNING, 'The last core update was rolled back'];
            }
        }

        $settings = app(SettingsRepository::class);
        $available = $settings->get('core_update.available');

        if (is_array($available) && is_string($available['version'] ?? null) && app(CoreReleaseVerifier::class)->newer($available['version'], Platform::VERSION)) {
            return ['Core updates', self::WARNING, "Modrix Commerce {$available['version']} is available"];
        }

        $checked = $settings->get('core_update.checked_at');

        return ['Core updates', self::OK, is_string($checked) ? 'Up to date (checked '.$checked.')' : 'Up to date (not checked yet)'];
    }

    /** @return array{string, string, string} */
    private function configuration(): array
    {
        if (! app()->isProduction()) {
            return ['Production configuration', self::OK, 'Not a production environment ('.app()->environment().')'];
        }

        if (config('app.debug')) {
            return ['Production configuration', self::FAILED, 'APP_DEBUG is true: error pages can reveal secrets.'];
        }

        $issues = array_values(array_filter([
            ! str_starts_with((string) config('app.url'), 'https://') ? 'APP_URL is not https' : null,
            ! config('session.secure') ? 'SESSION_SECURE_COOKIE is not true' : null,
            in_array(config('mail.default'), ['log', 'array'], true) ? 'mail is not delivered (log mailer)' : null,
            config('ecomodular.security.csp') === 'off' ? 'Content-Security-Policy is off' : null,
        ]));

        return ['Production configuration', $issues === [] ? self::OK : self::WARNING, $issues === [] ? 'Debug off, HTTPS, secure cookies' : implode('; ', $issues)];
    }
}
