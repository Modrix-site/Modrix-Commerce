# Admin Backups Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** An Admin → Settings → Backups page to list, create, download, delete and restore backups and edit the schedule/retention.

**Architecture:** Thin controller over the existing `App\Core\Operations\Backups` service. Long work runs in two queued jobs that report through a JSON status file in the backup directory (it survives a restore). Schedule/retention move from env-only to the settings store via a small `BackupSettings` reader with env fallback.

**Tech Stack:** Laravel 13, Inertia + React (TS), PHPUnit on SQLite, Wayfinder route helpers (`@/actions/...`, `@/routes/...`).

**Spec:** `docs/superpowers/specs/2026-11-09-admin-backups-design.md`

## Global Constraints

- PHP: `export PATH="/c/php84:$PATH"` before `php` / `npm run build`. Tests run serially (no `--parallel`).
- Do not commit unless the user asks. Touch only backup / settings / admin files (other sessions edit this folder).
- Permission name `backups.manage`, group `Administration`, label `Manage backups`; not in the Administrator role defaults.
- Backup name regex: `^ecomodular-backup-[0-9]{8}-[0-9]{6}-[a-z0-9]{4}\.zip$`.
- Status file `<Backups::directory()>/.operation.json`, lock `<directory()>/.operation.lock`.
- Settings keys `backup.schedule` (`HH:MM` or `""`), `backup.with_files` (bool), `backup.keep` (1–100).
- Keep `Backups::create()` manifest fields (`database_tables`/`database_views`) and `restoreDatabase()` unchanged.
- Audit actions: `system.backup_created`, `system.backup_downloaded`, `system.backup_deleted`, `system.backup_restore_started`, `system.backup_restored`, `system.backup_restore_failed`, `settings.backups_updated`.
- Tests use the scratch-tree `setUp()` from `tests/Feature/Operations/HealthAndBackupTest.php` (copy it), `$this->owner()`, `$this->actingAsConfirmed()`, `$this->roleWithPermissions()`.

## Review Focus

- Archive encrypted with a different password than the configured one → listed as "password mismatch", restore refused at verify, nothing queued. (Task 2, Task 4 tests)
- Worker crashed mid-operation → a `running` status older than `ecomodular.backup.timeout` does not block new operations. (Task 2 test)
- Schedule input like `24:00`, `7:5`, `abc` → 422; `""` turns the schedule off. (Task 4 test)
- Backup job throws (dump fails, disk full) → status `failed` with the message, no audit `created`, page shows the error. (Task 3 test)
- Restore job fails before data changed → store is back up; fails after → stays down with recovery commands in the status. (Task 3 test)

---

### Task 1: Permission and BackupSettings wired into schedule and prune

**Files:**

- Modify: `app/Core/Authorization/CorePermissions.php` (Administration group)
- Modify: `app/Core/Authorization/SystemRoles.php` (administrator: `array_diff($allPermissions, ['system.update', 'backups.manage'])`)
- Create: `app/Core/Settings/BackupSettings.php`
- Modify: `routes/console.php` (backup schedule block)
- Modify: `app/Core/Operations/Backups.php` (`prune()` only)
- Test: `tests/Feature/Operations/AdminBackupsTest.php`

**Interfaces:**

- Produces: `BackupSettings::schedule(): string`, `withFiles(): bool`, `keep(): int`, `nextRun(): ?\Carbon\CarbonImmutable`, `save(array{schedule: string, with_files: bool, keep: int} $values): void`, `envDefaults(): array{schedule: string, with_files: bool, keep: int}`.

- [ ] **Step 1: Write failing tests**
    - `test_backup_settings_fall_back_to_config`: config `schedule='03:15'`, `keep=4`, nothing saved → `schedule()==='03:15'`, `keep()===4`, `withFiles()===true`.
    - `test_saved_backup_settings_win_and_drive_next_run`: `save(['schedule'=>'04:00','with_files'=>false,'keep'=>2])`; `Carbon::setTestNow('2026-11-09 05:00')` → `nextRun()` equals `2026-11-10 04:00`; `save` with `schedule=''` → `nextRun()===null`.
    - `test_prune_uses_saved_keep`: save keep 2, `Backups::create(false)` three times → `count(all())===2`.
    - `test_administrator_role_lacks_backups_manage`: `userWithRole('administrator')->can('backups.manage')` is false; owner true.

- [ ] **Step 2: Run** `php artisan test --filter=AdminBackupsTest` → FAIL (class missing).

- [ ] **Step 3: Implement.** `BackupSettings` takes `SettingsRepository`; each getter wraps the repository read in `try { } catch (\Throwable) { return config fallback }`. `keep()` clamps to 1–100. `save()` uses `setMany` then `flush()`. `prune()` reads `app(BackupSettings::class)->keep()`. In `routes/console.php`, resolve `BackupSettings`; if `schedule() !== ''` schedule `ecomodular:backup` with `withFiles() ? [] : ['--database-only']`, `dailyAt(schedule())`, keeping `withoutOverlapping()->onOneServer()`.

- [ ] **Step 4: Run** the filter again → PASS. Run `php artisan test tests/Feature/Operations/HealthAndBackupTest.php` → same results as before the change.

### Task 2: Backups::describe and BackupOperation status/lock

**Files:**

- Modify: `app/Core/Operations/Backups.php` (add `describe()`, add `.operation.*` files to nothing — they are not zips so `all()` already ignores them)
- Create: `app/Core/Operations/BackupOperation.php`
- Test: `tests/Feature/Operations/AdminBackupsTest.php`

**Interfaces:**

- Produces: `Backups::describe(string $path): array{encrypted: bool, readable: bool, includes_files: ?bool, platform_version: ?string}` — opens the zip, `statName('manifest.json')['encryption_method'] !== 0` → encrypted; sets the configured password and reads the manifest; unreadable → `readable=false`, other fields null. Never throws for a corrupt zip (returns `readable=false`).
- Produces: `BackupOperation` (constructed with `Backups`):
    - `current(): ?array` (status array per spec §3, with a stale `queued`/`running` older than `config('ecomodular.backup.timeout')` seconds reported as `state='failed'`, `message='Timed out: no worker finished it.'`);
    - `begin(string $type, ?string $backup, bool $withFiles, int $userId): array` — under `flock(LOCK_EX)` on the lock file; throws `App\Core\Operations\OperationInProgress` (new RuntimeException subclass) if `current()` is queued/running; writes `queued` with a random `id`, returns the status;
    - `update(string $id, array $changes): void` — merges into the file only if `id` matches;
    - `busy(): bool`.

- [ ] **Step 1: Write failing tests**
    - `test_describe_reports_files_and_encryption`: create with files, no password → `encrypted=false, readable=true, includes_files=true`. Set password `p1`, create db-only, then set password `p2` → `describe()` `encrypted=true, readable=false`.
    - `test_describe_survives_a_corrupt_archive`: `File::put(dir.'/ecomodular-backup-20260101-000000-abcd.zip','junk')` → `readable=false`.
    - `test_operation_blocks_while_running_but_not_when_stale`: `begin('backup', ...)` then second `begin` throws `OperationInProgress`; rewrite the file with `queued_at` = now − (timeout+1) s → `current()['state']==='failed'` and `begin` succeeds.

- [ ] **Step 2: Run** filter → FAIL.
- [ ] **Step 3: Implement** as above; write the status with `File::put(..., json_encode(...), lock: true)` and `chmod 0600`.
- [ ] **Step 4: Run** filter → PASS.

### Task 3: CreateBackupJob and RestoreBackupJob

**Files:**

- Create: `app/Jobs/CreateBackupJob.php`, `app/Jobs/RestoreBackupJob.php`
- Test: `tests/Feature/Operations/AdminBackupsTest.php`

**Interfaces:**

- Consumes: `Backups`, `BackupOperation::update()`, `AuditLogger::record(string $action, ?Model $subject = null, array $metadata = [], ?User $actor = null)`.
- Produces: `new CreateBackupJob(bool $withFiles, string $operationId, int $userId)`, `new RestoreBackupJob(string $name, string $operationId, int $userId)`; both `ShouldQueue`, `$tries = 1`, `timeout()` = `config('ecomodular.backup.timeout')`, `failed(\Throwable)` marks the status failed.

Restore job order (spec §4): mark running → `create(false)` pre-restore backup, store `pre_restore` name → `Artisan::call('down', ['--secret' => Str::random(32), '--retry' => 60])`, store `secret` → `Backups::restore(path)` → `if (Schema::hasTable('jobs')) DB::table('jobs')->delete()` and `SettingsRepository::flush()` → `Artisan::call('up')` → status done + audit `system.backup_restored` (actor null, metadata `name`, `pre_restore`, `user_id`). On a throw before `restore()` starts: `up`, status failed. On a throw from `restore()` or later: stay down, status failed with `message` and `recovery` = `["php artisan ecomodular:restore {pre_restore} --force", "php artisan up"]`, audit `system.backup_restore_failed`, rethrow nothing (status carries it).

- [ ] **Step 1: Write failing tests**
    - `test_backup_job_creates_an_archive_and_reports_done`: `begin`, `(new CreateBackupJob(false, $id, $owner->id))->handle(...)` via `app()->call([$job,'handle'])` → one backup, status `done` with `backup` name, audit row `system.backup_created`.
    - `test_backup_job_failure_is_reported`: (`Backups` is final — no mocks) point `ecomodular.backup.path` at an existing regular file so `create()` throws → status `failed` with a non-empty message, no `system.backup_created` audit.
    - `test_restore_job_round_trip`: product named `Before`, create backup, rename to `After`, insert a row in `jobs`, run `RestoreBackupJob` → product name `Before`, `jobs` empty, `app()->isDownForMaintenance()` false, status `done` with `pre_restore` set and that file exists.
    - `test_restore_job_failure_after_import_leaves_store_down`: create a backup, then overwrite one archive entry's bytes (e.g. rewrite the zip with a changed `database/` entry so the checksum fails) before running the job — `restore()` throws during its own verify → `isDownForMaintenance()` true, status `failed`, `recovery[0]` contains `--force`; then `Artisan::call('up')` in the test cleanup.

- [ ] **Step 2: Run** filter → FAIL.
- [ ] **Step 3: Implement** per the interface above.
- [ ] **Step 4: Run** filter → PASS.

### Task 4: Controller, routes, navigation

**Files:**

- Create: `app/Http/Controllers/Admin/BackupSettingsController.php`
- Modify: `routes/admin.php` (new `can:backups.manage` group next to the settings group)
- Modify: `app/Providers/CoreServiceProvider.php` (one `$navigation->add('backups', 'Backups', 'admin.settings.backups.index', 'archive', 'Administration', 'backups.manage', 922);` — check `nav-icon.tsx` has `archive`, else add `Archive` to its map)
- Test: `tests/Feature/Operations/AdminBackupsTest.php`

**Interfaces:**

- Routes and middleware exactly as spec §2 table; names `admin.settings.backups.{index,status,schedule,store,download,destroy,restore}`. `{name}` route constraint = the global name regex; controller resolves `Backups::directory().'/'.$name` and `abort_unless(is_file(...), 404)`.
- `index` renders `admin/settings/backups` with props `backups` (each `all()` row minus `path`, plus `describe()` fields, `created_at` ISO), `settings`, `env_defaults`, `next_run`, `scheduler_alive` (heartbeat file `HealthCheck::HEARTBEAT_FILE` within 300 s), `encryption_configured`, `operation` (`BackupOperation::current()` without `secret` unless type restore and state running/failed).
- `status` → JSON `{operation, backups}` (same shapes).
- `schedule` validates `schedule` `nullable|regex:/^([01]\d|2[0-3]):[0-5]\d$/`, `with_files` boolean, `keep` `integer|between:1,100`; null schedule saves `""`; audit `settings.backups_updated`; toast; redirect back.
- `store` validates `with_files` boolean; `begin` (on `OperationInProgress` → back with error toast); dispatch `CreateBackupJob`; toast "Backup queued.".
- `download` → `response()->download($path, $name)`; audit `system.backup_downloaded`.
- `destroy` → refuse (error toast) if it is the operation's current backup while busy; unlink; audit `system.backup_deleted`.
- `restore` validates `confirm_name` `required|in:{name}`; `Backups::verify($path)` in try/catch → error on `backup` key; driver mismatch (`manifest['database_driver'] !== DB::getDriverName()`) → same; `begin('restore')`; dispatch `RestoreBackupJob`; audit `system.backup_restore_started`.

- [ ] **Step 1: Write failing tests**
    - `test_routes_require_backups_manage`: user with role from `roleWithPermissions('ops', ['settings.manage'])` → 403 on GET index and POST store; guest → redirect to login.
    - `test_index_lists_backups_and_schedule`: owner, two backups → Inertia component `admin/settings/backups`, `backups` count 2 with keys `name,size,created_at,encrypted,includes_files`, `next_run` not null.
    - `test_store_queues_a_backup_and_refuses_a_second`: `Queue::fake()`; POST `with_files=false` → `CreateBackupJob` pushed; second POST → no second push, session has error toast.
    - `test_download_and_delete`: `actingAsConfirmed(owner)`; GET download → 200, `Content-Disposition` contains the name; DELETE → file gone; both audited. Without confirmation → redirect to `password.confirm`.
    - `test_invalid_names_are_404`: `foo.zip`, `ecomodular-backup-20260101-000000-zzzz.zip` (missing), `..%2Fx.zip` → 404.
    - `test_schedule_validation_and_save`: `24:00`, `7:5`, `abc` → 422 / session errors; `''` → saved off; `keep=0` and `101` → errors; valid `04:30,false,3` persisted.
    - `test_restore_guards`: wrong `confirm_name` → error, nothing queued; corrupt archive → error on `backup`; password-mismatch archive → error; valid → `RestoreBackupJob` pushed and audit `system.backup_restore_started`.
    - `test_owner_navigation_includes_backups`: owner nav keys contain `backups`.

- [ ] **Step 2: Run** filter → FAIL.
- [ ] **Step 3: Implement**, then `php artisan wayfinder:generate` if the project does not regenerate on build (check `vite.config.ts`).
- [ ] **Step 4: Run** filter → PASS; `php artisan test tests/Feature/Admin/AdminAccessTest.php` → no new failures.

### Task 5: React page

**Files:**

- Create: `resources/js/pages/admin/settings/backups.tsx`

**Interfaces:**

- Consumes: props from Task 4; Wayfinder helpers from `@/actions/App/Http/Controllers/Admin/BackupSettingsController` and `@/routes/admin/settings/backups`; components `AdminPage`, `PageHeader`, `FormField`, `NativeSelect`, `Card`, `Button`, `Input`, `Checkbox`, `InputError`, Inertia `Form` / `router` (follow `languages.tsx`).

Layout: header "Backups"; warning banners (no encryption password; scheduler heartbeat missing; operation queued > 2 min → worker hint with `php artisan queue:work`; failed operation message + `recovery` commands; running restore → maintenance notice + link to `/{secret}`). Schedule card (time input with "Off" checkbox, scope select, keep number, "Default from .env: …" hints, Save). "Back up now" card (scope select + button, disabled while busy). Table: name, created (locale date-time), size (MB, 2 decimals), Encrypted (Yes / No / "Yes — password mismatch"), Contents (Database / Database + files), actions Download (plain `<a href>`), Delete (confirm dialog), Restore (dialog explaining it replaces database and files, signs everyone out, takes a pre-restore backup; text input that must equal the name; submit disabled until it matches). While `operation.state` is queued/running, poll `status` every 3 s with `fetch` and update the operation and list; stop polling when done/failed and `router.reload()`. `Backups.layout = { breadcrumbs: [{ title: 'Backups', href: index() }] }`.

- [ ] **Step 1:** Write the page.
- [ ] **Step 2: Run** `npm run build` → exits 0 with no TS errors (also `npx tsc --noEmit` if the project has a `types` script).
- [ ] **Step 3:** In the running dev server (localhost:8017, owner login), open `/admin/settings/backups`: create a database-only backup (run `php artisan queue:work --once` if no worker), download it, delete it, save a schedule. Do not run a restore against the shared dev DB.

### Task 6: Verification

- [ ] **Step 1: Run** `php artisan test tests/Feature/Operations` → all pass.
- [ ] **Step 2: Run** the full suite serially; compare failures with the ~58 known environmental ones (disk-space /health 503, blocked directory renames) — no new failures.
- [ ] **Step 3: Run** `vendor/bin/pint --dirty` on the touched PHP files only.
- [ ] **Step 4:** Update `docs/operations/backup-and-restore.md` with a short "From the admin" section (page location, permission, worker requirement, restore behaviour).
