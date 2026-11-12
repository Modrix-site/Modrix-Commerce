# Admin → Settings → Backups

Date: 2026-11-09 · Status: approved design, pending spec review

## Goal

Store owners manage backups without a shell: see existing backups and the
next scheduled run, back up now (database only or with files), download,
delete and restore a backup, and configure the daily schedule and
retention that are env-only today.

Restore stays available on the CLI (`ecomodular:restore`); the admin is an
additional, guarded entry point. Dumping, archiving, verifying and
restoring stay in `App\Core\Operations\Backups` — nothing is
reimplemented.

## Decisions

| Topic              | Decision                                                                                                                                                                                                                                                               |
| ------------------ | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Restore from admin | Yes, full restore, with strong guards (below).                                                                                                                                                                                                                         |
| Permission         | New `backups.manage` (Administration group). Owner has it implicitly. The built-in Administrator role does **not** get it by default (same treatment as `system.update`: whoever can restore can replace every account). Grantable to any role in Roles & permissions. |
| Long operations    | Backup and restore run as queued jobs; the page polls a status file.                                                                                                                                                                                                   |
| Schedule/retention | Editable on the page, saved in the settings store; env values remain the defaults until saved.                                                                                                                                                                         |

## 1. Settings: `App\Core\Settings\BackupSettings`

Keys in `SettingsRepository` under `backup.*`:

- `backup.schedule` — `"HH:MM"` (24 h, app timezone) or `""` (off).
  Default `config('ecomodular.backup.schedule')`.
- `backup.with_files` — bool, scope of the scheduled backup. Default `true`.
- `backup.keep` — int 1–100. Default `config('ecomodular.backup.keep')`.

API: `schedule(): string`, `withFiles(): bool`, `keep(): int`,
`save(array $values): void`, `nextRun(): ?CarbonImmutable` (next
occurrence of the daily time, null when off).

Every read is wrapped so a missing/unreachable database (fresh install,
`package:discover`, broken connection) falls back to config silently.

Consumers:

- `routes/console.php` schedules `ecomodular:backup` at
  `BackupSettings::schedule()`, adding `--database-only` when
  `withFiles()` is false. Skipped when the schedule is `""`.
- `Backups::prune()` reads `keep()` instead of the config value directly
  (config remains the fallback through `BackupSettings`).
- `ecomodular:backup` CLI flags still win when given explicitly.

## 2. Page and routes

Page: `resources/js/pages/admin/settings/backups.tsx`, in the Settings
folder of the admin nav (`CoreServiceProvider` nav item `backups`,
"Backups", icon `archive`, permission `backups.manage`, order 922).

Controller: `App\Http\Controllers\Admin\BackupSettingsController`.

Routes (`routes/admin.php`, group `can:backups.manage`):

| Method | URI                                | Action                   | Extra middleware   |
| ------ | ---------------------------------- | ------------------------ | ------------------ |
| GET    | `settings/backups`                 | `index`                  | —                  |
| GET    | `settings/backups/status`          | `status` (JSON, polled)  | —                  |
| PUT    | `settings/backups/schedule`        | `schedule`               | —                  |
| POST   | `settings/backups`                 | `store` (queue a backup) | `throttle:6,1`     |
| GET    | `settings/backups/{name}/download` | `download`               | `password.confirm` |
| DELETE | `settings/backups/{name}`          | `destroy`                | `password.confirm` |
| POST   | `settings/backups/{name}/restore`  | `restore`                | `password.confirm` |

`{name}` must match `^ecomodular-backup-[0-9]{8}-[0-9]{6}-[a-z0-9]{4}\.zip$`
and exist in `Backups::directory()`; anything else is a 404. The path is
always built from `directory()` + the validated name, never from input.

Page props:

- `backups`: list of `{name, created_at, size, encrypted, includes_files,
platform_version}`. `encrypted` / `includes_files` / version come from
  reading the archive's `manifest.json` (a new cheap
  `Backups::describe(path)` that opens the zip and reads only the manifest
  — the entry's encryption flag tells `encrypted`; an encrypted manifest
  that cannot be read with the configured password shows
  "encrypted — password mismatch").
- `settings`: `{schedule, with_files, keep}` plus `env_defaults` so the
  form can say "default from .env".
- `next_run` (ISO or null), `scheduler_alive` (heartbeat seen in the last
  5 minutes, from `HealthCheck`), `encryption_configured`
  (`ECOMODULAR_BACKUP_PASSWORD` set), `operation` (current status, below).

UI: schedule card (time input + off toggle, scope select, keep number,
Save); "Back up now" with a database-only / database + files choice;
table with Download / Delete / Restore per row. Warnings: no encryption
password set; scheduler heartbeat missing; operation queued > 2 minutes
("no queue worker seems to be running — run `php artisan queue:work`").

Every mutating action and every download is audit-logged
(`system.backup_created` (existing), `system.backup_downloaded`,
`system.backup_deleted`, `system.backup_restore_started`,
`system.backup_restored`, `system.backup_restore_failed`,
`settings.backups_updated`).

## 3. Operations: jobs, status file, lock

`App\Core\Operations\BackupOperation` owns
`<directory()>/.operation.json` — the backup directory is excluded from
backups and never touched by restore, so the status survives the database
being replaced. Shape:

```json
{
    "id": "…",
    "type": "backup|restore",
    "state": "queued|running|done|failed",
    "backup": "<name or null>",
    "with_files": true,
    "queued_at": "…",
    "started_at": "…",
    "finished_at": "…",
    "message": "…",
    "user_id": 1
}
```

- `begin(type, …)` takes an exclusive `flock` on `.operation.lock`,
  refuses (409 / flash error) when the current state is `queued` or
  `running` and younger than the configured timeout
  (`ecomodular.backup.timeout`), then writes `queued`.
- A stale `queued`/`running` older than the timeout is treated as failed
  so a crashed worker never blocks the page forever.

Jobs (`app/Jobs`, `ShouldQueue`, `tries = 1`, `timeout =
ecomodular.backup.timeout`):

- `CreateBackupJob(withFiles, operationId)` → marks running, calls
  `Backups::create()`, audit-logs, marks done/failed with message.
- `RestoreBackupJob(name, operationId)` → see section 4.

## 4. Restore safety

Request side (`restore` action), before anything is queued:

1. `password.confirm` (recent password) **and** a `confirm_name` field
   that must equal the backup name exactly.
2. `Backups::verify()` on the archive: checksums, password, readable
   manifest. Driver mismatch with the current connection is rejected
   here too. Failures return to the page with the error; nothing changes.
3. `BackupOperation::begin('restore')`, dispatch `RestoreBackupJob`,
   audit `system.backup_restore_started`.

Job side:

1. Mark running. Create a **database-only pre-restore backup**
   (`Backups::create(false)`) so there is a way back; its name is stored
   in the status.
2. `Artisan::call('down', ['--secret' => <random>, '--retry' => 60])`.
   The secret is written to the status file; the polling page (still open
   in the admin's browser) shows a "continue to the store" link through
   `/<secret>` so the admin can watch it finish.
3. `Backups::restore(path)` (verifies again, swaps files, imports,
   migrates, `optimize:clear`).
4. Clear what the dump brought back but must not replay or be trusted:
   delete all rows of the `jobs` table (the dump may contain jobs queued
   at backup time, including this kind of job), and flush the settings
   cache (`SettingsRepository::flush()`).
5. `Artisan::call('up')`, mark done, audit `system.backup_restored`
   (written into the restored database).

Failure: if step 1 or 2 fails, nothing was changed — bring the store up,
mark failed. If step 3 or later fails, `Backups::restore()` has already
put back the files where it could; the store **stays in maintenance**,
status `failed` with the error and the recovery commands
(`php artisan ecomodular:restore <pre-restore name> --force` / `php artisan up`)
shown on the page and in the log.

User-visible consequence (warned in the confirmation dialog): users,
roles and sessions come from the backup, so the admin is signed out
afterwards and signs in with the credentials that existed at backup time.

## 5. Testing

Feature tests in `tests/Feature/Operations/AdminBackupsTest.php`
(SQLite, scratch `backup.path` and `files_root` as in
`HealthAndBackupTest`):

- Gate: guests redirected; a user without `backups.manage` gets 403 on
  every route; Administrator role does not get it by default.
- Index lists backups with size, encrypted flag, includes_files, next run.
- Store queues `CreateBackupJob` (`Queue::fake`); running the job creates
  an archive and writes `done` to the status; a second request while one
  is running is refused; stale operations are not blocking.
- Download streams the file and audits; delete removes it and audits;
  invalid / traversal names (`../x.zip`, `foo.zip`, missing file) → 404.
- Schedule saving validates `HH:MM`/empty and 1–100, persists, and
  `BackupSettings` falls back to config when nothing is saved;
  `prune()` honours the saved keep.
- Restore: wrong `confirm_name` rejected; a corrupt archive fails verify
  and nothing is dispatched; a full round trip run synchronously
  (create → change data → `RestoreBackupJob::handle`) restores the data,
  leaves a pre-restore backup, clears `jobs`, and the app is up.
- Existing `HealthAndBackupTest` and core-update `restoreDatabase()`
  tests keep passing.

Then `npm run build`.

## Out of scope

Uploading a backup file through the admin, off-site copies (S3 etc.),
per-backup notes, changing the encryption password from the admin.
