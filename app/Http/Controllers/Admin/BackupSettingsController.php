<?php

namespace App\Http\Controllers\Admin;

use App\Core\Audit\AuditLogger;
use App\Core\Operations\BackupOperation;
use App\Core\Operations\Backups;
use App\Core\Operations\HealthCheck;
use App\Core\Operations\OperationInProgress;
use App\Core\Settings\BackupSettings;
use App\Http\Controllers\Controller;
use App\Jobs\CreateBackupJob;
use App\Jobs\RestoreBackupJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * Settings → Backups: list, create (queued), download, delete and restore
 * (queued, in maintenance mode) backup archives, and the daily schedule.
 * The work itself is App\Core\Operations\Backups; restores are also
 * available on the CLI (ecomodular:restore).
 */
class BackupSettingsController extends Controller
{
    /** File names Backups::create() produces; nothing else is ever touched. */
    public const NAME_PATTERN = 'ecomodular-backup-[0-9]{8}-[0-9]{6}-[a-z0-9]{4}\.zip';

    public function __construct(
        private readonly Backups $backups,
        private readonly BackupOperation $operation,
    ) {}

    public function index(BackupSettings $settings): Response
    {
        $next = $settings->nextRun();

        return Inertia::render('admin/settings/backups', [
            'backups' => $this->listing(),
            'settings' => ['schedule' => $settings->schedule(), 'with_files' => $settings->withFiles(), 'keep' => $settings->keep()],
            'env_defaults' => $settings->envDefaults(),
            'next_run' => $next?->toIso8601String(),
            'scheduler_alive' => $this->schedulerAlive(),
            'encryption_configured' => (string) config('ecomodular.backup.password', '') !== '',
            'operation' => $this->operationProps(),
        ]);
    }

    public function status(): JsonResponse
    {
        return response()->json(['operation' => $this->operationProps(), 'backups' => $this->listing()]);
    }

    public function schedule(Request $request, BackupSettings $settings, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'schedule' => ['nullable', 'string', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'with_files' => ['required', 'boolean'],
            'keep' => ['required', 'integer', 'between:1,100'],
        ]);

        $before = ['schedule' => $settings->schedule(), 'with_files' => $settings->withFiles(), 'keep' => $settings->keep()];
        $after = ['schedule' => (string) ($data['schedule'] ?? ''), 'with_files' => (bool) $data['with_files'], 'keep' => (int) $data['keep']];
        $settings->save($after);
        $audit->record('settings.backups_updated', null, ['from' => $before, 'to' => $after]);

        Inertia::flash('toast', ['type' => 'success', 'message' => $after['schedule'] === '' ? 'Backup settings saved. Scheduled backups are off.' : "Backup settings saved. Daily backup at {$after['schedule']}."]);

        return back();
    }

    public function store(Request $request): RedirectResponse
    {
        $withFiles = (bool) $request->validate(['with_files' => ['required', 'boolean']])['with_files'];

        try {
            $status = $this->operation->begin('backup', null, $withFiles, (int) $request->user()->id);
        } catch (OperationInProgress $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            return back();
        }

        CreateBackupJob::dispatch($withFiles, $status['id'], (int) $request->user()->id);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Backup queued.']);

        return back();
    }

    public function download(string $name, AuditLogger $audit): BinaryFileResponse
    {
        $path = $this->pathOrFail($name);
        $audit->record('system.backup_downloaded', null, ['name' => $name]);

        return response()->download($path, $name, ['Content-Type' => 'application/zip']);
    }

    public function destroy(string $name, AuditLogger $audit): RedirectResponse
    {
        $path = $this->pathOrFail($name);
        $current = $this->operation->current();
        if ($this->operation->busy() && ($current['backup'] ?? null) === $name) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'This backup is being restored.']);

            return back();
        }

        @unlink($path);
        $audit->record('system.backup_deleted', null, ['name' => $name]);
        Inertia::flash('toast', ['type' => 'success', 'message' => "Deleted {$name}."]);

        return back();
    }

    public function restore(Request $request, string $name, AuditLogger $audit): RedirectResponse
    {
        $path = $this->pathOrFail($name);
        $request->validate(['confirm_name' => ['required', 'string', 'in:'.$name]], ['confirm_name.in' => 'Type the backup name exactly to confirm.']);

        try {
            $manifest = $this->backups->verify($path);
        } catch (Throwable $e) {
            throw ValidationException::withMessages(['backup' => 'This backup cannot be restored: '.$e->getMessage()]);
        }

        $driver = DB::connection()->getDriverName();
        if (($manifest['database_driver'] ?? null) !== $driver) {
            throw ValidationException::withMessages(['backup' => 'This backup was made with the '.($manifest['database_driver'] ?? '?')." driver; this store uses {$driver}."]);
        }

        try {
            $status = $this->operation->begin('restore', $name, (bool) ($manifest['includes_files'] ?? false), (int) $request->user()->id);
        } catch (OperationInProgress $e) {
            throw ValidationException::withMessages(['backup' => $e->getMessage()]);
        }

        // The page fetches /{secret} to keep polling while the store is in maintenance.
        $secret = Str::random(32);
        $this->operation->update($status['id'], ['secret' => $secret]);
        RestoreBackupJob::dispatch($name, $status['id'], (int) $request->user()->id, $secret);
        $audit->record('system.backup_restore_started', null, ['name' => $name, 'includes_files' => $manifest['includes_files'] ?? false]);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Restore queued. The store goes into maintenance mode while it runs.']);

        return back();
    }

    private function pathOrFail(string $name): string
    {
        abort_unless(preg_match('/^'.self::NAME_PATTERN.'$/', $name) === 1, 404);
        $path = $this->backups->directory().'/'.$name;
        abort_unless(is_file($path), 404);

        return $path;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listing(): array
    {
        return array_map(fn (array $backup) => [
            'name' => $backup['name'],
            'size' => $backup['size'],
            'created_at' => date(DATE_ATOM, $backup['created_at']),
            ...$this->backups->describe($backup['path']),
        ], $this->backups->all());
    }

    /** @return array<string, mixed>|null */
    private function operationProps(): ?array
    {
        $status = $this->operation->current();
        if ($status === null) {
            return null;
        }

        // The maintenance bypass is only useful while a restore is pending or keeps the store down.
        $keepSecret = $status['type'] === 'restore' && ($status['state'] === 'queued' || $status['state'] === 'running' || ($status['state'] === 'failed' && app()->isDownForMaintenance()));
        if (! $keepSecret) {
            unset($status['secret']);
        }

        return $status;
    }

    private function schedulerAlive(): bool
    {
        $file = storage_path(HealthCheck::HEARTBEAT_FILE);
        $last = is_file($file) ? (int) @file_get_contents($file) : 0;

        return $last > 0 && time() - $last <= 300;
    }
}
