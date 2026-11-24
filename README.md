# SIES Campus Portal

**Student Information Enrollment System with Attendance Monitoring**

A web-based system for a college to keep student records, run class enrollment from request to official enrollment, and take and monitor class attendance, with separate workspaces for the administrator, the registrar, teachers and students.

Built with Laravel 13, Bootstrap 5 and MySQL as a Software Engineering capstone project.

---

## Contents

- [Features](#features)
- [Roles and what each one can do](#roles-and-what-each-one-can-do)
- [Tech stack](#tech-stack)
- [Getting started](#getting-started)
- [Demo accounts](#demo-accounts)
- [Useful commands](#useful-commands)
- [Testing](#testing)
- [Project structure](#project-structure)
- [Security and data privacy](#security-and-data-privacy)
- [Documentation](#documentation)

---

## Features

### Student information
- Student records with personal details, guardians and emergency contacts, program, year level and block.
- Admission requirements (PSA birth certificate, Form 138, good moral, ID photo): students upload scans, the registrar opens them in a built-in viewer, then verifies or **returns** them with a reason the student is told.
- Student photos with a cropper; a new photo waits for the registrar's approval.
- Import students from CSV with a checked preview; export the list to CSV.
- **Credited subjects** for transferees (subjects passed at another school).
- **Promote year level** at the end of the academic year, with a preview.
- **Archive** instead of delete: archived records keep their full history and can be restored.

### Enrollment
- Request → **approve** → **enroll** workflow, with reject, cancel/drop and reopen (each needs a reason).
- Every request is checked automatically:
  - seats left in the class,
  - schedule conflicts with the student's other classes,
  - unit limit per term,
  - the subject belongs to the student's program (major subjects),
  - **prerequisites** completed in an earlier term (or credited),
  - required documents verified (for approval).
- Database locks stop two staff members from booking the last seat, or double-booking a student, at the same moment.
- Requests are grouped by student, with bulk approve and enroll.
- **Certificate of Registration** (study load) as a printable PDF.

### Classes and curriculum
- Academic years and terms (open/close enrollment, close a term to lock its records).
- Programs, subjects (major/minor, prerequisites) and classes with block sections, teacher, capacity, rooms and a weekly schedule.
- Teacher and room clash detection.
- **Copy classes from another term** to start a new term quickly.
- Holidays and class suspensions, which are never counted as absences.
- Printable **class list** PDF.

### Attendance
- Teachers take attendance per class meeting: Present, Late, Absent or Excused, in a phone-friendly sheet.
- An edit window for teachers (7 days by default); older changes need an administrator and a written reason.
- **Excuse letters** from students, with a file; the teacher accepts or declines them on the sheet.
- Attendance Monitoring with daily, weekly and monthly totals, attendance rate and frequent-absence alerts.
- Alerts by email to the student and guardians when absences pass the school's limit.
- Missed attendance sheets are flagged.

### Reports
Student master list, enrollment report, daily and monthly attendance, student attendance history, absentees, late students, missing sheets and class summary. Each is viewable on screen and exportable to **PDF** and **Excel**, with a print view.

### Everyday use
- Dashboards for each role, with "needs attention" items and a notification bell.
- **Live search and filters** on every list (results update as you type).
- Confirmation windows for important actions, toast messages, clear empty states, and layouts that work on phones.
- Audit log of every important action, who did it and when.

---

## Roles and what each one can do

| Role | Main tasks |
|---|---|
| **Administrator** | Everything the registrar can do, plus user accounts, programs, terms and academic years, holidays, system settings, audit logs, data privacy tools and permanent deletion of archived records. |
| **Registrar** | Student records, documents, enrollment requests, classes and subjects, attendance monitoring, reports. |
| **Teacher** | Own classes and rosters, taking attendance, excuse letters, attendance summaries and reports for own classes. |
| **Student** | Own profile, documents, available courses, enrollments, schedule, attendance, excuse letters, Certificate of Registration. |

Every page checks the role on the server. A teacher only ever reaches their own classes, and a student only their own records.

---

## Tech stack

| Part | Technology |
|---|---|
| Back end | PHP 8.3+, Laravel 13 |
| Database | MySQL 8 (SQLite in memory for tests) |
| Front end | Blade templates, Bootstrap 5.3, Sass, Vite |
| Charts | Chart.js |
| PDF / Excel | barryvdh/laravel-dompdf, maatwebsite/excel |
| Photo cropping | Cropper.js |
| Tests | PHPUnit (Laravel feature tests) |

---

## Getting started

### Requirements
- PHP 8.3 or newer with the `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `gd`, `zip`, `xml`, `dom` and `intl` extensions
- Composer
- Node.js 20+ and npm
- MySQL 8

### Installation

```bash
git clone https://github.com/PongG3/student-information-enrollment-system-with-attendance-monitoring.git
cd student-information-enrollment-system-with-attendance-monitoring
```

```bash
composer install
npm install
```

Create your environment file and app key:

```bash
cp .env.example .env
php artisan key:generate
```

Create an empty MySQL database, then set `DB_DATABASE`, `DB_USERNAME` and `DB_PASSWORD` in `.env`.

Create the tables and the starting data (programs, subjects, requirements, settings and the demo accounts):

```bash
php artisan migrate --seed
```

Optional sample classes, students and attendance for trying the system:

```bash
php artisan db:seed --class=DemoDataSeeder
```

Build the styles and scripts, then start the app:

```bash
npm run build
```

```bash
php artisan serve
```

Open http://localhost:8000.

For everyday use, `composer run start` does the build, pre-compiles the pages and starts the server on port 8001. While editing CSS or JavaScript, `composer run dev` reloads styles live.

---

## Demo accounts

Created by the seeder **for local testing only**. They are skipped automatically when `APP_ENV=production`.

| Role | Email | Password |
|---|---|---|
| Administrator | `admin@sies.test` | `Sies-Test-2026!` |
| Registrar | `registrar@sies.test` | `Sies-Test-2026!` |
| Teacher | `teacher@sies.test` | `Sies-Test-2026!` |
| Student | `student@sies.test` | `Sies-Test-2026!` |

> Never use these accounts or this password on a real server. Create the first real administrator with `php artisan sies:create-admin`.

---

## Useful commands

| Command | What it does |
|---|---|
| `php artisan sies:create-admin` | Create an administrator account (the first real account on a new server). |
| `php artisan sies:backup` | Back up the database and every uploaded file into one dated `.zip`. Also scheduled daily. |
| `php artisan sies:restore` | Replace all data and uploaded files with a backup. |
| `php artisan sies:check` | Check that the installation is safe to put on the school network. |
| `php artisan view:cache` | Pre-compile all pages so the first visit to each page is fast. |

---

## Testing

```bash
php artisan test
```

More than 400 feature tests cover roles and access, the enrollment rules (seats, schedule conflicts, units, prerequisites, locking), attendance, excuse letters, reports, file uploads, privacy tools and protection against SQL injection.

---

## Project structure

```
app/
  Http/Controllers/   pages and actions (one controller per module)
  Http/Middleware/    role checks, student portal, session timeout
  Models/             Eloquent models (Student, Enrollment, ClassOffering, ...)
  Policies/           ClassOfferingPolicy (teachers see only their own classes)
  Services/           business rules: EnrollmentRules, EnrollmentWorkflow,
                      AttendanceService, ReportService, Backup, ...
  Support/            audit logger, notifications, privacy policy, branding
config/sidebar.php    the menu of each role
database/
  migrations/         the database schema
  seeders/            starting data and demo data
resources/
  views/              Blade pages, grouped by module
  scss/, js/          design system, live filters, dialogs, attendance sheet
routes/               web, student portal, reports, auth
tests/Feature/        feature tests
docs/                 ERD, deployment, backup and email guides
```

---

## Security and data privacy

- **Passwords and accounts:** passwords are hashed, sign-in attempts are rate-limited, and inactive accounts are signed out at once. New accounts and password resets use a one-time "choose my password" link instead of emailing a password.
- **Forgot password:** students enter their student number (teachers and staff their email); the link goes to the email on the account, works once and expires in 60 minutes.
- **One email per student:** the profile email is also the sign-in email. A new email only takes effect after the student opens a confirmation link sent to it, and the old address is told. The page never reveals whether an account exists, requests are rate-limited, and after a reset the user is signed out everywhere else and emailed that the password changed.
- **Access:** every page checks the role on the server, and teachers and students only reach their own records.
- **Files:** uploads are checked by their content, stored privately, and served only to the people allowed to see them.
- **Data Privacy Act of 2012 (RA 10173):** a privacy notice, consent recorded on each student, a staff privacy policy that staff agree to, a breach register with the 72-hour deadline, and a "download my data" PDF.
- **Audit log:** a permanent record of who did what and when.

---

## Documentation

- [Entity relationship diagram](docs/ERD.md)
- [Putting the system on the school network](docs/DEPLOYMENT.md)
- [Backups and restore](docs/BACKUP.md)
- [Setting up email](docs/EMAIL.md)
