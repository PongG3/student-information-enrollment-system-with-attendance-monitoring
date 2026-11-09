# Putting SIES on the school network

This guide takes the system from your development laptop to a real server that teachers, the registrar and students reach over the school network. Read it once through, then follow it in order. Every step that can go wrong has a check.

**Golden rules**

1. The system holds students' personal information. It stays on the school network. Never open it to the internet.
2. Test accounts (`@sies.test`) and the password `Sies-Test-2026!` must not exist on the real server.
3. Nobody types a password into a chat, email or the project folder. Passwords live in the `.env` file on the server and in people's heads.
4. A backup that has never been restored is not a backup. Do the restore test in section 8.

---

## 1. Choose the server

- A computer that stays on during school hours, on a **fixed IP address** (ask whoever runs the school network), plugged into a UPS if you can.
- Windows 10/11 or Windows Server with **PHP 8.4** and **MySQL 8.4** and **Apache**. Laragon or XAMPP gives you all three; pick a version that ships PHP 8.4.
- Enable these PHP extensions in `php.ini`: `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `gd`, `zip`, `xml`, `dom`, `curl`, `intl`.
- Turn on **BitLocker** for the disk. Backups and photos on a stolen computer are then unreadable.
- Only people who run the system should be able to sign in to this Windows computer.

## 2. Prepare the files (on your development laptop)

```powershell
cd C:\laravel-projects\sies-v2
php artisan test                    # everything must pass first
npm run build                       # builds the CSS and JavaScript into public\build
```

Copy the whole project folder to the server, for example `C:\sies`. **Do not copy** `node_modules`, `.env`, `storage\logs\*`, or `database\database.sqlite`.

On the server, inside `C:\sies`:

```powershell
composer install --no-dev --optimize-autoloader
```

## 3. Create the production database and its two users

The app gets an account that can only **read and write rows**. A second account, used only for
migrations and backups, may change tables. So even if an attacker ever got SQL through the app,
it could not drop or alter a table, or read other databases.

Open the MySQL console as root (`mysql -u root -p`) and run, with your own two different long random passwords:

```sql
CREATE DATABASE sies_prod CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- The app: rows only.
CREATE USER 'sies_app'@'localhost' IDENTIFIED BY 'PUT-A-LONG-RANDOM-PASSWORD-HERE';
GRANT SELECT, INSERT, UPDATE, DELETE ON sies_prod.* TO 'sies_app'@'localhost';

-- Migrations and backups only. Never put this one in DB_USERNAME.
CREATE USER 'sies_admin'@'localhost' IDENTIFIED BY 'PUT-A-DIFFERENT-LONG-RANDOM-PASSWORD-HERE';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP, REFERENCES,
      LOCK TABLES, SHOW VIEW, TRIGGER ON sies_prod.* TO 'sies_admin'@'localhost';

FLUSH PRIVILEGES;
```

Put `sies_app` in `DB_USERNAME`/`DB_PASSWORD` and `sies_admin` in `DB_ADMIN_USERNAME`/`DB_ADMIN_PASSWORD`.
Use letters and digits only (16 or more) so the backup script reads them correctly. The `root` account is never used by the app. Set a password on `root` too, and do not let MySQL listen on the network (`bind-address=127.0.0.1`).

## 4. Settings file

Copy `.env.production.example` to `.env` and fill it in. Then:

```powershell
php artisan key:generate
php artisan migrate --database=mysql_admin --force         # tables are created with the admin account
php artisan db:seed --class=ReferenceDataSeeder --force   # programs, requirements, settings only. No test accounts.
php artisan sies:create-admin                             # your first REAL administrator
```

Do not run a plain `php artisan db:seed` on the server. Sign in, then open **System Settings** and enter the real school name. Create the registrar and teacher accounts from **User Management**. Each person gets a one-time temporary password and must choose their own at first sign-in.

## 5. Web server and HTTPS

Without HTTPS, passwords cross the network in plain text.

1. Point Apache's `DocumentRoot` at `C:\sies\public`, set `AllowOverride All` for that folder, and make sure `mod_rewrite` and `mod_ssl` are on.
2. Get a certificate for the address people will type (for example `sies.school.local`):
   - The school's IT office may already issue certificates, or
   - Create your own with **mkcert**, then install its root certificate on the school computers.
3. Add a virtual host on port 443 using that certificate, and a second one on port 80 that only redirects to `https://`.
4. Ask the network administrator for a local DNS name that points to the server's fixed IP.
5. In **Windows Firewall** allow ports 80 and 443 from the school network only. Never forward these ports on the school router.

Then run:

```powershell
php artisan optimize
php artisan sies:check
```

`sies:check` lists everything that is not safe yet. Fix every **FAIL** line. Fix the **WARN** lines too unless you know why not. Run it again after every change to the server.

## 6. First look

From a second computer on the network, open the HTTPS address. Check:

- The padlock shows, with no certificate warning.
- Sign in as each role (create one test teacher, delete it afterwards).
- A report downloads as PDF and Excel.
- `http://` (without the s) jumps to `https://`.
- After logout, the browser's Back button does not show the previous page.

## 7. Everyday account rules

- **New staff:** create the account in User Management. Hand over the temporary password in person. It is shown once.
- **New students:** open the student's record and use **Portal account**. Same rule.
- **Someone leaves:** deactivate the account the same day. Never delete it, because history in the audit log must stay.
- **Forgotten password:** use Reset password. Check the person's identity first.
- Review the **Audit Logs** weekly: failed sign-ins in bursts, exports of reports, and account changes.
- On computers that several people use, tell staff to click **Logout** and close the browser.

## 8. Backups

The script `scripts\backup.ps1` saves the database and the private student photos into one zip file and removes zips older than 30 days.

Try it once by hand:

```powershell
powershell -ExecutionPolicy Bypass -File C:\sies\scripts\backup.ps1 -OutDir D:\sies-backups
```

`mysqldump` must be reachable. If PowerShell says it cannot find it, add the `-MysqlDump 'C:\path\to\mysqldump.exe'` option.

Schedule it every night: **Task Scheduler → Create Basic Task → Daily at 11:30 PM → Start a program**:

- Program: `powershell.exe`
- Arguments: `-ExecutionPolicy Bypass -File C:\sies\scripts\backup.ps1 -OutDir D:\sies-backups`

Also copy the backup folder to a second place every week (an external drive that is kept locked away, or another computer). Keep a copy of the `.env` file in a sealed place too: the backup does not contain it.

**Restore test** (do this once on a spare computer or a test database, before go-live):

```powershell
Expand-Archive D:\sies-backups\sies-backup-2026-11-09_2330.zip -DestinationPath C:\restore-test
mysql -u root -p -e "CREATE DATABASE sies_restore_test CHARACTER SET utf8mb4"
mysql -u root -p --default-character-set=utf8mb4 sies_restore_test < C:\restore-test\sies-database-2026-11-09_2330.sql
```

Photos are in `C:\restore-test\private`. Copy that folder's contents back to `storage\app\private` when restoring for real. Never practise on the live database.

## 9. Updating the system later

1. `php artisan down` (visitors see a maintenance page)
2. Run `scripts\backup.ps1`
3. Copy the new files over the old ones (never over `.env` or `storage`)
4. `composer install --no-dev --optimize-autoloader` (only if new libraries came with the update)
5. `php artisan migrate --database=mysql_admin --force`
6. `php artisan optimize:clear` then `php artisan optimize`
7. `php artisan sies:check` and a quick look around
8. `php artisan up`

## 10. Privacy (Data Privacy Act of 2012, RA 10173)

This is a checklist for the people responsible, not legal advice. Ask the school's **Data Protection Officer** to confirm each point.

- The school publishes a **privacy notice** to students and parents saying what is collected (names, birth dates, addresses, guardians, photos, attendance) and why.
- Only staff who need the data have accounts, and each role sees only its own pages. Teachers see only their own classes, and students see only themselves.
- Photos and records are stored on the school's own server and never sent to outside services.
- Every export of a report is written to the audit log with who and when.
- Decide how long old records are kept, and who may approve archiving or deleting them. Students are archived, never erased, so academic history stays.
- Have a written plan for a **data breach** (lost laptop, wrong person given access): who to tell, how fast, and how to reset every password. The law has strict reporting deadlines. Your DPO knows them.
- Check with the DPO whether the school must register the system with the National Privacy Commission.
- Printed reports and downloaded files are as sensitive as the system. Tell staff to keep them in a locked place and not on personal phones, USB sticks or chat apps.

## 11. Go-live checklist

- [ ] `php artisan test` passes on the laptop
- [ ] `.env` has `APP_ENV=production`, `APP_DEBUG=false`, an `https://` URL, a strong database password and a non-root database user
- [ ] The app's database user (`DB_USERNAME`) has only SELECT, INSERT, UPDATE, DELETE; migrations use `DB_ADMIN_USERNAME`
- [ ] `php artisan sies:check` shows no FAIL
- [ ] No `@sies.test` account exists and the real school name is set
- [ ] HTTPS works with no warning on a teacher's computer
- [ ] Windows Firewall allows the school network only
- [ ] Nightly backup is scheduled and a restore was practised
- [ ] Registrar and teachers have their own accounts and have changed their passwords
- [ ] Privacy notice and breach plan are agreed with the DPO
