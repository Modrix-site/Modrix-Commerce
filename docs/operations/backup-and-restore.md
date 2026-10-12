# Backup and Restore

> Status: the procedure is documented. A restore drill is a Phase 7 exit criterion and **has not been performed yet**.

## What to back up

| Item           | Location                                                     | Notes                                                                                                                          |
| -------------- | ------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------ |
| Database       | MySQL `ecomodular`                                           | Source of truth for every store, order, settings and audit record                                                              |
| Uploaded files | `storage/app/public`, `storage/app/private` or the S3 bucket | Product media; later, extension packages                                                                                       |
| Environment    | `.env`                                                       | Contains `APP_KEY`. Without it, encrypted columns (2FA secrets) cannot be decrypted. Store it separately in a secrets manager. |

## Database backup

```bash
mysqldump --single-transaction --quick --routines --triggers \
  -u ecomodular -p ecomodular | gzip > ecomodular-$(date +%F-%H%M).sql.gz
```

`--single-transaction` produces a consistent InnoDB snapshot without locking tables.

Encrypt backups before sending them off-site (for example `gpg --encrypt` or `age`). Restrict access to backup storage to operators only.

## File backup

```bash
tar -czf ecomodular-files-$(date +%F).tar.gz storage/app
```

When using S3-compatible storage, enable bucket versioning and lifecycle policies instead.

## Restore

```bash
php artisan down
gunzip < ecomodular-YYYY-MM-DD-HHMM.sql.gz | mysql -u ecomodular -p ecomodular
tar -xzf ecomodular-files-YYYY-MM-DD.tar.gz
php artisan migrate --force        # applies any newer migrations
php artisan optimize:clear
php artisan up
```

Make sure the restored environment uses the **same `APP_KEY`** as the backup.

## Verification after restore

1. Sign in as the owner. 2FA must still work, which proves `APP_KEY` matches.
2. Check that Admin → System status shows "Database migrations applied: Up to date".
3. Spot-check the audit log for the most recent expected entries.

## Recommended schedule

- Database: daily full dump, plus binary logs for point-in-time recovery on busy stores
- Files: daily
- Retention: 7 daily, 4 weekly and 12 monthly copies
- Restore drill: quarterly, into a staging environment
