# Monitoring

## Endpoints and commands

| Probe                                                                | Use                                 | Response                                                                                |
| -------------------------------------------------------------------- | ----------------------------------- | --------------------------------------------------------------------------------------- |
| `GET /up`                                                            | Liveness (load balancer, container) | 200 when the application boots                                                          |
| `GET /health`                                                        | Readiness and uptime monitors       | `{"status":"ok"}`, `"warning"` (200) or `"failed"` (503); rate limited to 60 per minute |
| `GET /health` with `Authorization: Bearer <ECOMODULAR_HEALTH_TOKEN>` | Dashboards                          | Adds `checks`: key, label, status and detail for each check                             |
| `php artisan ecomodular:health [--json]`                             | Cron or agent-based alerting        | Table or JSON; exit code 1 when a check failed                                          |
| Admin → System status                                                | Operators                           | The same checks, plus environment and requirements                                      |

Details never include credentials, hostnames or absolute paths.

## Checks

| Check                    | Warning                                                                              | Failed                                        |
| ------------------------ | ------------------------------------------------------------------------------------ | --------------------------------------------- |
| Database                 | Query slower than 500 ms                                                             | Cannot connect                                |
| Migrations               | Cannot inspect                                                                       | Pending migrations                            |
| Cache                    |                                                                                      | Cannot read back a written value              |
| Writable storage         |                                                                                      | `storage/*` or `bootstrap/cache` not writable |
| Disk space               | Under `ECOMODULAR_HEALTH_DISK_WARN_PERCENT` (10%) free                               | Under `…_FAIL_PERCENT` (5%) free              |
| Queue                    | `sync` in production; failed jobs in 24 h; jobs waiting over 15 min (database queue) |                                               |
| Scheduler                | No heartbeat, or none for 5 minutes                                                  |                                               |
| Backups                  | None, or the newest is older than `ECOMODULAR_HEALTH_BACKUP_MAX_AGE_HOURS` (26)      |                                               |
| Payment webhooks         | Failed events in the last 24 hours                                                   |                                               |
| Production configuration | `APP_URL` not HTTPS, insecure session cookie, log mailer, CSP off                    | `APP_DEBUG=true` in production                |

## Suggested alerts

- **Page someone:** `/health` returns 503, or `/up` fails, for 2 consecutive minutes.
- **Ticket:** `/health` reports `warning` for 30 minutes. Fetch the details with the token to see which check.
- **Cron alternative:** `*/5 * * * * cd /var/www/ecomodular && php artisan ecomodular:health > /dev/null || <your alert command>`.
- **Errors:** send `LOG_LEVEL=error` entries to your error tracker or chat (add a log channel).
- **Payments:** failed webhooks also appear in Admin → Payments → Events, with a retry button.

## Disk space

Backups (`storage/app/private/backups`), logs and media grow over time. Retention is bounded by `ECOMODULAR_BACKUP_KEEP` and `LOG_DAILY_DAYS`. Check the disk check and the provider's disk alerts, and move media to S3 for large catalogs.
