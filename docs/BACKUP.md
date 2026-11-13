# Backups and restore

The system keeps everything in two places: the MySQL database and the uploaded files
(student photos, excuse letters, staff photos, the school logo) in `storage/app/private`.
One command backs up both into a single dated `.zip`.

## Make a backup

```bash
php artisan sies:backup
```

The file goes to `storage/app/backups/sies-backup-YYYYMMDD-HHMMSS.zip`. The 14 newest are kept
(`--keep=30` keeps more). **Copy backups off this computer** as well: a USB drive kept elsewhere,
or the school's cloud storage. A backup on the same disk does not survive a dead disk.

`php artisan sies:check` warns when the newest backup is more than two days old.

## Back up every day automatically

A backup runs every evening at 6:00 PM, but only while Laravel's scheduler is running.

**Windows (Task Scheduler):** create a task that runs every minute:

```
Program:   C:\path\to\php.exe
Arguments: artisan schedule:run
Start in:  C:\laravel-projects\sies-v2
```

**Linux (cron):**

```
* * * * * cd /var/www/sies-v2 && php artisan schedule:run >> /dev/null 2>&1
```

## Restore a backup

Restoring **replaces every record** with the backup's. Do it outside school hours.

```bash
php artisan down
php artisan migrate --database=mysql_admin --force
php artisan sies:restore storage/app/backups/sies-backup-20261112-180000.zip
php artisan up
```

- The database must exist and be migrated first (the second line). A backup from an older
  version restores into a newer one: columns added later get their default values.
- Everyone has to sign in again afterwards.
- The restore is recorded in Audit Logs.

## Test your backups

Once a term, restore the newest backup into a **copy** of the system (another database) and open
a few student records. A backup that was never tested may not work when it is needed.
