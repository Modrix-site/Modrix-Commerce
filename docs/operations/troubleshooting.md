# Troubleshooting

Start with `php artisan ecomodular:health` and `storage/logs/`. Each entry below gives the symptom, the likely cause and the fix.

## The site shows a 500 error

- **Cause:** a missing `APP_KEY`, or cached configuration pointing at old values.
- **Fix:** run `php artisan optimize:clear`, check `.env`, then run `php artisan optimize` again.
- **Detail:** the exact error is in the newest `storage/logs/laravel-*.log` (never turn on `APP_DEBUG` in production).

## Pages load without styles or scripts

- **Cause:** `public/build` is missing or stale.
- **Fix:** run `npm ci && npm run build`, or deploy the built assets.
- **Images on another origin are blocked:** the browser console shows "Content Security Policy" errors for images on another domain. Set the media disk URL (`AWS_URL`), or add the origin to `ECOMODULAR_CSP_IMG_SOURCES`.
- **Diagnosing the CSP:** set `ECOMODULAR_CSP=report-only` temporarily to confirm it is the cause.

## Orders stay "processing payment" / emails are not sent

- **Cause:** the queue worker is not running, which the health check reports under "Queue".
- **Fix:** start the worker with `systemctl status ecomodular-queue`, then run `php artisan queue:restart` after deploys.
- **Failed jobs:** list them with `php artisan queue:failed` and retry with `php artisan queue:retry all`.

## Stripe payments are not confirmed

- **Check:** Admin → Payments → Events for failed or missing webhooks, and that the webhook secret matches the Stripe dashboard.
- **Missed webhooks:** payment reconciliation (every 5 minutes, needs the scheduler) catches them.
- **Fix:** `php artisan ecomodular:payments:reconcile` runs it at once. See `docs/payments/stripe.md`.

## Scheduler warning

- **Cause:** the `schedule:run` cron entry is missing or runs as a user that cannot write `storage/`.
- **Check:** `php artisan schedule:list`, and the crontab of `www-data`.

## A module broke the site

- **Fix:** set `ECOMODULAR_SAFE_MODE=true` in `.env`, run `php artisan config:clear`, disable the module (Admin → Extensions or `php artisan ecomodular:modules disable vendor/name`), then remove safe mode.
- **Recovery paths:** see `docs/extensions/recovery.md`.

## A theme update looks wrong

- **Theme files:** Admin → Appearance shows the applied version; merchant customizations survive updates.
- **Marketplace installs:** Admin → Extensions → Marketplace → History → "Restore previous files".

## "Backup failed: mysqldump failed"

- **Detail:** the reason is in the application log, which records the exit code and the truncated error output.
- **Usual causes:** the client is not installed (`apt install mysql-client`), the binary is not on `PATH` (set `ECOMODULAR_MYSQLDUMP`), or the database user lacks `PROCESS`/`LOCK TABLES` privileges.

## "The archive has no readable manifest"

- **Encrypted archive:** it was encrypted with a different `ECOMODULAR_BACKUP_PASSWORD`. Use the password from your secret manager.
- **Other causes:** the file is incomplete or not an EcoModular backup.

## Locked out of the owner account

- **Fix:** with shell access, `php artisan tinker` lets you reset a password (`User::where('email', '…')->first()->forceFill(['password' => '…'])->save();`). Then review the audit log.
- **Two-factor reset:** recovery codes are shown when 2FA is enabled. Without them, clear `two_factor_secret` and `two_factor_confirmed_at` for that user the same way.

## Permission errors writing to storage

- **Fix:** run `chmod -R ug+rwX storage bootstrap/cache` and `chown -R deploy:www-data storage bootstrap/cache`.
- **Check:** the health "Writable storage" check names the failing directory.
