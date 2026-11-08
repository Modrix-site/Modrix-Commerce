<?php

namespace App\Core\Updates;

use App\Core\Audit\AuditLog;
use App\Core\Audit\AuditLogger;
use App\Core\Marketplace\Client\UpdateHistory;
use App\Core\Operations\Backups;
use App\Core\Settings\SettingsRepository;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Process\PendingProcess;
use Illuminate\Process\ProcessResult;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Throwable;

/**
 * Installs a core platform release into the updates root
 * (`ecomodular.updates.root`, normally the app itself):
 *
 *  1. lock (one run at a time)          6. maintenance mode on
 *  2. re-verify the signed metadata     7. database backup (no writes can follow it)
 *  3. download, check size and sha256   8. swap each managed path (current → `.old-*`)
 *  4. extract with safe entry checks    9. migrate + optimize:clear in a fresh process
 *  5. zip the current managed paths    10. maintenance off, drop `.old-*`, completed
 *
 * A failure before step 8 changes nothing (`failed`). A failure in steps 8–9
 * puts every `.old-*` path back, removes paths that had no old copy,
 * restores the database when migrations had started (`rolled_back`); when
 * that undo itself has problems the run is `failed` with `needs_attention`.
 * Maintenance mode is always lifted. `run()` never throws.
 */
final class CoreUpdater
{
    public const LOCK = 'ecomodular:core-update';

    public const EXTENSION_NAME = 'modrixcommerce/core';

    /**
     * Runs `php artisan <args>` for the tree at $root and returns the exit code.
     * The default spawns a fresh PHP process so the new code runs; tests swap it.
     *
     * @var (Closure(string, list<string>): int)|null
     */
    public ?Closure $runArtisan = null;

    /**
     * Renames a path (from, to) and returns success. The default is rename()
     * with retries; tests swap it to make a single move fail.
     *
     * @var (Closure(string, string): bool)|null
     */
    public ?Closure $mover = null;

    private string $lastOutput = '';

    /** Set when `artisan up` failed and the down file was deleted instead. */
    private ?string $maintenanceNote = null;

    public function __construct(
        private readonly CoreUpdateClient $client,
        private readonly CoreReleaseVerifier $verifier,
        private readonly CorePackage $package,
        private readonly Backups $backups,
        private readonly AuditLogger $audit,
    ) {}

    public function run(CoreUpdateRun $run): CoreUpdateRun
    {
        if ($run->status !== CoreUpdateRun::QUEUED) {
            return $run;
        }

        // Two starts that raced past the controller guard: only the oldest live run proceeds.
        if (CoreUpdateRun::query()->live()->where('id', '<', $run->getKey())->exists()) {
            return $this->finish($run, CoreUpdateRun::FAILED, 'Another core update is already running.');
        }

        try {
            $lock = Cache::lock(self::LOCK, 7200);

            if (! $lock->get()) {
                // The lock holder may be this very run (started twice): never fail a run that is in progress.
                $fresh = $run->fresh();
                if ($fresh === null || $fresh->status !== CoreUpdateRun::QUEUED) {
                    return $fresh ?? $run;
                }

                return $this->finish($fresh, CoreUpdateRun::FAILED, 'Another core update is running. Try again when it has finished.');
            }
        } catch (Throwable $e) {
            return $this->finish($run, CoreUpdateRun::FAILED, 'The update lock could not be taken: '.$e->getMessage());
        }

        try {
            return $this->install($run);
        } catch (Throwable $e) {
            report($e);

            return $this->finish($run, CoreUpdateRun::FAILED, $e->getMessage());
        } finally {
            try {
                $lock->release();
            } catch (Throwable) {
                // optimize:clear may have flushed the cache store already.
            }
        }
    }

    /** Maintenance bypass for this run (`/<secret>` sets the bypass cookie). */
    public function bypassSecret(CoreUpdateRun $run): string
    {
        return substr(hash_hmac('sha256', 'core-update:'.$run->getKey(), (string) config('app.key')), 0, 40);
    }

    private function install(CoreUpdateRun $run): CoreUpdateRun
    {
        $root = rtrim(str_replace('\\', '/', (string) config('ecomodular.updates.root', base_path())), '/');
        $updates = $root.'/storage/app/private/core-updates';
        $work = $updates.'/tmp/'.Str::lower(Str::random(16));
        $zip = $work.'.zip';

        $from = null;
        $verified = null;
        $sha = null;
        $backup = null;
        $down = false;
        $changing = false;
        $migrating = false;
        /** @var list<array{current: string, old: string|null}> $swapped */
        $swapped = [];

        try {
            // ---- Steps 1–6: nothing in the app changes yet. ----
            $this->assertNoOtherRun($run);

            $from = CorePackage::versionIn($root) ?? throw new CoreUpdateError('The installed version could not be read from app/Core/Platform.php.');
            $run->forceFill(['from_version' => $from])->save();

            $verified = $this->verifier->verify((array) $run->release, $from);
            if ($verified === null || (is_string($run->to_version) && $run->to_version !== '' && $verified['version'] !== $run->to_version)) {
                throw new CoreUpdateError("The release could not be verified for Modrix Commerce {$from} on PHP ".PHP_VERSION.' (signature, version or PHP requirement).');
            }

            $this->stage($run, CoreUpdateRun::DOWNLOADING);
            $link = $this->client->requestDownload((int) $verified['release_id']);
            File::ensureDirectoryExists(dirname($zip), 0700);
            $this->client->download($link['url'], $zip, (int) $verified['size']);

            $this->stage($run, CoreUpdateRun::VERIFYING);
            $sha = (string) hash_file('sha256', $zip);
            if (! hash_equals((string) $verified['sha256'], $sha)) {
                $sha = null;
                throw new CoreUpdateError('The downloaded package checksum does not match the signed release. Nothing was changed.');
            }

            $this->package->extract($zip, $work, max(1, (int) config('ecomodular.updates.max_mb', 300)) * 4 * 1024 * 1024);
            @unlink($zip);

            $declared = CorePackage::versionIn($work);
            if ($declared !== $verified['version']) {
                throw new CoreUpdateError('The package declares version '.($declared ?? 'none').' but the signed release is '.$verified['version'].'. Nothing was changed.');
            }
            if (! is_file($work.'/vendor/autoload.php')) {
                throw new CoreUpdateError('The package has no vendor/autoload.php. Nothing was changed.');
            }

            $plan = $this->plan($root, $work);

            // The code snapshot is slow but needs no downtime.
            $this->stage($run, CoreUpdateRun::BACKING_UP);
            $codeBackup = 'storage/app/private/core-updates/core-'.Str::slug($from).'-'.now()->utc()->format('Ymd-His').'.zip';
            $this->package->zipManagedPaths($root, $root.'/'.$codeBackup);
            $run->forceFill(['code_backup_path' => $codeBackup])->save();

            $this->warmUp();
            $down = true;
            $this->artisan($root, ['down', '--secret='.$this->bypassSecret($run)]);

            // Taken in maintenance mode, so no order written after it can be lost by a restore.
            $backup = $this->backups->create(withFiles: false);
            $run->forceFill(['backup_name' => $backup['name']])->save();
            $this->stage($run, CoreUpdateRun::INSTALLING);

            // ---- Steps 8–9: from here a failure is rolled back. ----
            $changing = true;
            foreach ($plan as $path) {
                $current = $root.'/'.$path;
                $old = null;

                if (file_exists($current) || is_link($current)) {
                    $old = $current.'.old-'.Str::lower(Str::random(8));
                    if (! $this->move($current, $old)) {
                        throw new CoreUpdateError("Could not move {$path} aside.");
                    }
                }

                $swapped[] = ['current' => $current, 'old' => $old];
                File::ensureDirectoryExists(dirname($current));

                if (! $this->move($work.'/'.$path, $current)) {
                    throw new CoreUpdateError("Could not move the new {$path} into place.");
                }
            }

            // Caches written by the old code (package manifest, config, routes) must not boot the new code.
            $this->clearBootstrapCache($root);

            $this->stage($run, CoreUpdateRun::MIGRATING);
            $migrating = true;
            $this->artisan($root, ['migrate', '--force']);
            $this->artisan($root, ['optimize:clear']);
        } catch (Throwable $e) {
            if (! $changing) {
                $maintenance = $this->liftMaintenance($root, $down);

                return $this->finish($run, CoreUpdateRun::FAILED, $e->getMessage().$this->noteSuffix($maintenance), $from, $verified, $sha, needsAttention: $maintenance !== null);
            }

            $problems = $this->rollback($swapped, $root, $migrating ? $backup : null);
            $maintenance = $this->liftMaintenance($root, $down);
            $where = 'Code backup: '.($run->code_backup_path ?? '-').'; database backup: '.($run->backup_name ?? '-').'.';

            if ($problems !== [] || $maintenance !== null) {
                return $this->finish($run, CoreUpdateRun::FAILED, 'The update failed and could NOT be fully undone ('.implode('; ', array_filter([...$problems, $maintenance, $this->maintenanceNote])).'). '
                    .$where.' Cause: '.$e->getMessage(), $from, $verified, $sha, needsAttention: true);
            }

            return $this->finish($run, CoreUpdateRun::ROLLED_BACK, 'The update failed and was undone: '.$e->getMessage().$this->noteSuffix(null), $from, $verified, $sha);
        } finally {
            if ($down) {
                $this->liftMaintenance($root, $down);
            }
            File::deleteDirectory($work);
            @unlink($zip);
        }

        // ---- Step 10 (finally above already lifted maintenance mode). ----
        $notes = array_values(array_filter([$down ? 'maintenance mode is still on: run php artisan up' : null, $this->maintenanceNote]));
        foreach ($swapped as $swap) {
            if ($swap['old'] !== null && ! $this->remove($swap['old'])) {
                $notes[] = 'could not delete '.basename($swap['old']);
            }
        }

        try {
            if ($this->runArtisanRaw($root, ['queue:restart']) !== 0) {
                $notes[] = 'restart queue workers manually';
            }
        } catch (Throwable) {
            $notes[] = 'restart queue workers manually';
        }

        return $this->finish($run, CoreUpdateRun::COMPLETED, "Modrix Commerce updated from {$from} to {$verified['version']}.".($notes === [] ? '' : ' Note: '.implode('; ', $notes).'.'), $from, $verified, $sha);
    }

    /**
     * The managed paths the package replaces, checked before anything changes.
     *
     * @return list<string>
     */
    private function plan(string $root, string $work): array
    {
        $plan = [];

        foreach (CorePackage::managedPaths() as $path) {
            $new = $work.'/'.$path;

            if (! file_exists($new)) {
                continue; // never delete a path the package does not ship
            }

            $current = $root.'/'.$path;
            if ((file_exists($current) || is_link($current)) && (is_dir($current) && ! is_link($current)) !== is_dir($new)) {
                throw new CoreUpdateError("The package's {$path} is not the same kind (file or directory) as the installed one. Nothing was changed.");
            }

            $plan[] = $path;
        }

        if ($plan === []) {
            throw new CoreUpdateError('The package contains none of the managed paths. Nothing was changed.');
        }

        $this->assertDatabaseOutside($root);

        return $plan;
    }

    /**
     * A swap would move (and on success delete) a SQLite database that lives
     * inside a managed path, so such an install is refused up front.
     */
    private function assertDatabaseOutside(string $root): void
    {
        $database = config('database.connections.sqlite.database');
        $database = is_string($database) && $database !== '' && $database !== ':memory:' ? realpath($database) : false;

        if ($database === false) {
            return;
        }

        $normalize = fn (string $path): string => PHP_OS_FAMILY === 'Windows' ? strtolower(str_replace('\\', '/', $path)) : $path;
        $database = $normalize($database);

        foreach (CorePackage::managedPaths() as $path) {
            $managed = realpath($root.'/'.$path);

            if ($managed === false) {
                continue;
            }

            $managed = rtrim($normalize($managed), '/');

            if ($database === $managed || str_starts_with($database, $managed.'/')) {
                throw new CoreUpdateError("The SQLite database is inside the managed path {$path}, which an update replaces. Move it (DB_DATABASE) outside the managed paths, for example to database/database.sqlite. Nothing was changed.");
            }
        }
    }

    /**
     * @param  list<array{current: string, old: string|null}>  $swapped
     * @param  array{name: string, path: string}|null  $backup  restore the database from this when set
     * @return list<string> what could not be undone
     */
    private function rollback(array $swapped, string $root, ?array $backup): array
    {
        $problems = [];

        foreach (array_reverse($swapped) as $swap) {
            $current = $swap['current'];
            $failed = null;

            if (file_exists($current) || is_link($current)) {
                // Renaming is more reliable than deleting a tree that may be locked.
                $failed = $current.'.failed-'.Str::lower(Str::random(8));
                if (! $this->move($current, $failed)) {
                    $problems[] = 'could not remove the new '.$this->relative($root, $current);

                    continue;
                }
            }

            if ($swap['old'] !== null && ! $this->move($swap['old'], $current)) {
                $problems[] = 'could not put back '.$this->relative($root, $current).' from '.basename($swap['old']);
            }

            if ($failed !== null) {
                $this->remove($failed);
            }
        }

        $this->clearBootstrapCache($root);

        if ($backup !== null) {
            try {
                $this->backups->restoreDatabase($backup['path']);
            } catch (Throwable $e) {
                report($e);
                $problems[] = 'the database could not be restored: '.$e->getMessage();
            }
        }

        return $problems;
    }

    /** Returns null when maintenance mode is off, otherwise what went wrong. */
    private function liftMaintenance(string $root, bool &$down): ?string
    {
        if (! $down) {
            return null;
        }

        try {
            $code = $this->runArtisanRaw($root, ['up']);
        } catch (Throwable $e) {
            report($e);
            $code = 1;
        }

        if ($code === 0) {
            $down = false;

            return null;
        }

        // File driver fallback: maintenance mode is just this file.
        $file = $root.'/storage/framework/down';
        if (is_file($file) && @unlink($file) && ! is_file($file)) {
            $down = false;
            $this->maintenanceNote = 'php artisan up failed, so maintenance mode was lifted by deleting storage/framework/down';

            return null;
        }

        return 'maintenance mode is still on: run php artisan up';
    }

    private function noteSuffix(?string $maintenance): string
    {
        $notes = array_values(array_filter([$maintenance, $this->maintenanceNote]));

        return $notes === [] ? '' : ' Note: '.implode('; ', $notes).'.';
    }

    /** @param  list<string>  $args */
    private function artisan(string $root, array $args): void
    {
        $code = $this->runArtisanRaw($root, $args);

        if ($code !== 0) {
            throw new CoreUpdateError("php artisan {$args[0]} failed (exit {$code})".($this->lastOutput !== '' ? ': '.$this->lastOutput : '.'));
        }
    }

    /** @param  list<string>  $args */
    private function runArtisanRaw(string $root, array $args): int
    {
        $this->lastOutput = '';
        $runner = $this->runArtisan ?? function (string $root, array $args): int {
            $result = Process::path($root)->timeout(1800)->run([CoreUpdateLauncher::phpBinary(), $root.'/artisan', ...$args]);

            if ($result->failed()) {
                $this->lastOutput = trim(Str::substr(trim($result->output()."\n".$result->errorOutput()), -500));
            }

            return $result->exitCode() ?? 1;
        };

        return (int) $runner($root, $args);
    }

    private function assertNoOtherRun(CoreUpdateRun $run): void
    {
        // The cache lock can be flushed by optimize:clear; the table is the second guard.
        $busy = CoreUpdateRun::query()
            ->whereKeyNot($run->getKey())
            ->live()
            ->where('status', '!=', CoreUpdateRun::QUEUED)
            ->exists();

        if ($busy) {
            throw new CoreUpdateError('Another core update is in progress. Try again when it has finished.');
        }
    }

    private function stage(CoreUpdateRun $run, string $status): void
    {
        $run->forceFill(['status' => $status])->save();
    }

    /**
     * Loads the classes needed after the swap from the current code, before
     * `app/` and `vendor/` are replaced underneath this process.
     */
    private function warmUp(): void
    {
        foreach ([
            UpdateHistory::class, AuditLog::class, User::class, CoreUpdateError::class, SettingsRepository::class,
            PendingProcess::class, ProcessResult::class,
            ProcessTimedOutException::class, \Symfony\Component\Process\Process::class,
            Builder::class, \Illuminate\Database\Query\Builder::class,
        ] as $class) {
            class_exists($class);
        }
    }

    private function clearBootstrapCache(string $root): void
    {
        foreach (glob($root.'/bootstrap/cache/*.php') ?: [] as $file) {
            @unlink($file);
        }
    }

    /** rename() with retries: on Windows, antivirus and indexers hold files briefly. */
    private function move(string $from, string $to): bool
    {
        if ($this->mover !== null) {
            return (bool) ($this->mover)($from, $to);
        }

        $attempts = PHP_OS_FAMILY === 'Windows' ? 15 : 1;

        for ($attempt = 0; $attempt < $attempts; $attempt++) {
            if (@rename($from, $to)) {
                return true;
            }

            if (! (file_exists($from) || is_link($from)) || file_exists($to) || $attempt + 1 === $attempts) {
                return false;
            }

            usleep(min(1_000_000, 100_000 * ($attempt + 1)));
        }

        return false;
    }

    private function remove(string $path): bool
    {
        if (is_dir($path) && ! is_link($path)) {
            return File::deleteDirectory($path) || ! file_exists($path);
        }

        return @unlink($path) || ! file_exists($path);
    }

    /** The check result must not keep offering the version just installed (or an older one). */
    private function forgetInstalledRelease(string $installed): void
    {
        try {
            $settings = app(SettingsRepository::class);
            $available = $settings->get('core_update.available');
            $version = is_array($available) ? ($available['version'] ?? null) : null;

            if ($available !== null && (! is_string($version) || ! $this->verifier->newer($version, $installed))) {
                $settings->set('core_update.available', null);
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function relative(string $root, string $path): string
    {
        return ltrim(Str::after($path, $root), '/');
    }

    /**
     * Persists the terminal status and writes history and audit. Never throws.
     *
     * @param  array<string, mixed>|null  $verified
     */
    private function finish(CoreUpdateRun $run, string $status, string $message, ?string $from = null, ?array $verified = null, ?string $sha = null, bool $needsAttention = false): CoreUpdateRun
    {
        $message = Str::limit($message, 990);

        $run->forceFill(['status' => $status, 'message' => $message, 'needs_attention' => $needsAttention]);

        try {
            // Written in full, not as a dirty diff: a database restore may have
            // put the row back to how it was when the backup was taken.
            $run->newQuery()->whereKey($run->getKey())->update([
                'status' => $status,
                'message' => $message,
                'from_version' => $run->from_version,
                'backup_name' => $run->backup_name,
                'code_backup_path' => $run->code_backup_path,
                'needs_attention' => $needsAttention,
                'updated_at' => $run->freshTimestamp(),
            ]);
            $run->syncOriginal();
        } catch (Throwable $e) {
            report($e);
        }

        if ($status === CoreUpdateRun::COMPLETED) {
            $this->forgetInstalledRelease(is_string($verified['version'] ?? null) ? $verified['version'] : (string) $run->to_version);
        }

        try {
            $actor = $run->actor_id ? User::query()->find($run->actor_id) : null;
            $to = is_string($verified['version'] ?? null) ? $verified['version'] : $run->to_version;
            $from ??= $run->from_version;

            UpdateHistory::query()->forceCreate([
                'extension_name' => self::EXTENSION_NAME,
                'type' => 'core',
                'action' => 'update',
                'from_version' => $from,
                'to_version' => $to,
                'success' => $status === CoreUpdateRun::COMPLETED,
                'message' => $message,
                'backup_path' => $run->code_backup_path === null ? null : Str::limit($run->code_backup_path, 250),
                'sha256' => $sha,
                'actor_id' => $actor?->id,
            ]);

            $this->audit->record(match ($status) {
                CoreUpdateRun::COMPLETED => 'core.update_completed',
                CoreUpdateRun::ROLLED_BACK => 'core.update_rolled_back',
                default => 'core.update_failed',
            }, $run, [
                'run_id' => $run->getKey(),
                'from_version' => $from,
                'to_version' => $to,
                'message' => $message,
                'backup_name' => $run->backup_name,
            ], $actor);
        } catch (Throwable $e) {
            report($e);
        }

        return $run;
    }
}
