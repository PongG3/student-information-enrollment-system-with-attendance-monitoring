# SIES Campus Portal — UI/UX Redesign Progress

Last updated: 2026-11-12. Updated after every redesign step; a module is ticked only after it was changed **and** tested.

## How things are checked

- **Automated tests:** `php artisan test` — 304 feature tests (routes, roles, CRUD, validation, reports, security). All pass.
- **Viewport audit:** every page loaded as each role at **1366×768, 1440×900 and 1920×1080**; measures page height vs. screen, horizontal page overflow, and sideways scrolling inside tables.
- **Phone check:** 375 px wide, no horizontal overflow.
- **Crawl:** every page reachable by each role loads with no server error.

## Design system (done)

| Piece | Where |
|---|---|
| Tokens: colours, radii, shadows, type | `resources/scss/_tokens.scss` |
| Buttons, forms, cards, tables, badges, dialogs, toasts, pagination, tabs, steppers, skeletons, fitted tables | `resources/scss/_components.scss` |
| Sidebar, top bar, breadcrumbs, notifications, footer | `resources/scss/_shell.scss` |
| Sign-in page | `resources/scss/_auth.scss` |
| Page-specific pieces | `resources/scss/_modules.scss` |
| Blade components | `page-header` (actions slot), `table-footer`, `pagination-links`, `empty-state`, `status-badge`, `toasts`, `form-section`, `form-actions` (lead slot), `form-modal` (size, scrolls inside), `confirm-modal`, `logout-modal`, `session-timeout-modal`, `field`, `select`, `check` |
| Behaviour (`resources/js/app.js`) | double-submit guard, confirm/form dialogs, tab Previous/Next, **tables fit the screen** (`data-fit-viewport`), copy buttons, sidebar hold, attendance tally, enrollment unit count |
| Charts (`resources/js/charts.js`) | Charts in hidden tabs are drawn when their tab opens |

## Viewport-fit patterns used

1. **Fitted tables** — `data-fit-viewport`: on desktop a list table fills the space left on screen, scrolls inside, header row stays put. Pagination stays. Off on phones / when < 240 px would remain.
2. **Tabs** — long forms (student, settings), record pages (student), dashboards (Overview / Trends).
3. **Two columns** — class form (details | weekly schedule), subject dialog.
4. **Sticky action bars** — Save/Cancel always visible on long forms and pickers.
5. **Progressive disclosure** — once a report is shown its filters move into a "Change filters" dialog.
6. **Pages in a dialog** — `data-dialog` links open an add/edit page in the shared `#pageDialog` (`?embed=1`, no menu; `resources/js/page-dialog.js`). The dialog fits its content and never the window. A failed save shows its errors inside; a successful one moves the main window on with its message (`App\Http\Middleware\DialogEmbed`). A page that redirects on opening (closed term, signed out) moves the main window too. A page can set its width (`<x-app-layout dialog-size="lg|xl">`): new enrollment is lg for the search step, xl for choosing classes. Used for edit class (two columns from 992 px of frame width), add/edit term and academic year, add/edit student, new enrollment request (both steps), add/edit user, and the dashboard quick actions. Ctrl+click still opens the full page.

## Viewport audit (after this pass)

No page scrolls sideways at any size. All 38 audited pages fit at 1366×768, 1440×900 and 1920×1080.

| Page | Role | 1366×768 before → after | Fix |
|---|---|---|---|
| Enrollment requests | admin/registrar | +924 → fits | Fitted table |
| Audit logs | admin | +910 → fits | Fitted table |
| Enrollment report | admin/registrar | +970 → fits | Fitted table, folded filters |
| Master-list report | admin/registrar | +318, table scrolled sideways → fits | Compact report table (headers wrap), folded filters |
| Registrar dashboard | registrar | +758 → fits | Overview / Trends tabs; Trends = weekly attendance, enrollment by month (term months), students to follow up |
| Teacher dashboard | teacher | +427 → fits | Overview / Trends tabs; Trends = weekly attendance (needs 2 weeks of sheets), attendance by class; Next class card |
| Student record | admin/registrar | +266 → fits | Profile + tabs (Requirements, Enrollment history, Guardians, Portal account) |
| New enrollment (step 2) | admin/registrar | +350 → fits | Slim student strip, fitted class picker, sticky bar |
| Student records list | admin/registrar | +118 → fits | Fitted table |
| Class list | admin/registrar | +80 → fits | Fitted table |
| Attendance sheet | admin/teacher | +74 → fits | Fitted table, class days list scrolls inside |
| Available courses | student | +37 → fits | Fitted table |
| Attendance monitoring | all staff | +14 → fits | Slimmer page padding/footer |

## Module checklist

Legend: ✅ redesigned and tested · ⬜ not yet

### Shared
- ✅ App shell (hover/pin sidebar, top bar, breadcrumbs, notification bell, account menu)
- ✅ Sign-in (session-timeout notice) · ✅ Change password · ✅ Global search

### Administrator / Registrar
- ✅ Admin dashboard · ✅ Registrar dashboard
- Dashboard review (2026-11-12): tab title "Dashboard"; quick actions are tasks only (admin: Add user, Terms & enrollment, Add student); today's sheets are no longer a card (said in Needs attention, listed in Today); admin cards = students + accounts; registrar activity grouped per student; student: honest attendance under 3 meetings, quick actions, notification bell.
- System review (2026-11-12): missed past sheets (Needs attention chip for the last 2 weeks + "Missing attendance sheets" report, `AppServicesMissingSheets`); attendance sheet is phone-first (list before dates, stacked rows under 576 px); bulk Approve/Enroll selected on enrollment requests; student CSV import with checked preview (`StudentImportController`); excuse letters (student sends for an absence/late, teacher accepts by saving Excused, `absence_excuses` table); privacy notice page + consent recorded on students (`privacy_consent_at`); percentages say how late/excused are counted; unused Breeze auth controllers removed; bell badge moved off the icon.
- Staff data privacy policy (2026-11-12): `App\Support\PrivacyPolicy`; admin writes/publishes at /privacy-policy/edit (Markdown, HTML escaped); once published every admin/registrar/teacher must agree to the current version (gate in RoleMiddleware, recorded on users.privacy_policy_version/agreed_at and in Audit Logs); account menu link; admin reminder while it is a draft.
- Quality-audit follow-up (2026-11-13): backups (`sies:backup` / `sies:restore`, daily schedule, docs/BACKUP.md); emails for decided requests, excuse letters, rejected photos, absence alerts (student + guardians) and temporary passwords (docs/EMAIL.md; guardian email field); phone layouts (stacked tables, full-screen dialogs); students upload admission documents; photo rejection reason; excuse letters over a date range with a teacher note; registrar Trends class filter; Export shows "Preparing…"; "+N more" on a long Needs-attention bar; Data Privacy hub (breach register, DPO setting, personal-data PDF for staff and students, audit-log retention in the notice); accessibility (reduced motion, keyboard cropper, scope=col); polish (print stylesheet, last sign-in, who has not agreed, long date, 'Nothing changed' as info).
- ✅ Student records list · ✅ Student record (tabs) · ✅ Register / edit student (tabs)
- ✅ Enrollment requests · ✅ New enrollment request
- ✅ Class list (Add class opens a dialog, 1140×536, fits all desktop sizes) · ✅ Class detail · ✅ Add / edit class page (two columns; fields shared with the dialog via `classes/_fields`)
- ✅ Subjects (major/minor, wide dialog) · ✅ Programs · ✅ Academic years & terms (Add academic year opens a dialog, 520×370) · ✅ Holidays
- ✅ Attendance monitoring · ✅ Attendance sheet · ✅ Attendance summary
- ✅ Reports list · ✅ Report pages
- ✅ User management · ✅ Audit logs · ✅ System settings (tabs)

### Teacher
- ✅ Dashboard · ✅ My classes · ✅ Class roster · ✅ Attendance sheet

### Student
- ✅ Dashboard · ✅ My profile · ✅ Available courses · ✅ My enrollments · ✅ My schedule · ✅ My attendance

## Not done yet (from the master prompt)

- **Sortable table columns** (click a header to sort) — lists sort by a fixed sensible order today.
- **Row action menus ("⋯")** — rows still show their buttons inline (they fit).
- **Bulk actions** (e.g. approve several requests) — would need backend changes; not started.
- **Skeleton loading screens** — style exists (`.skeleton`), not yet used; pages render server-side, so there is little loading to show.

## Features that do not exist (not invented)

Registration, password reset by email, role/permission editor, announcements, grades. Notifications are the existing "needs attention" checks.

## Known issues

- Browser-pane screenshots often time out during testing; layouts are verified by measurement.
- Dev data: school name is still the placeholder; session timeout is 5 minutes.

## Next steps

1. Sortable columns on the main lists (students, classes, enrollments, users, audit log).
2. Decide on bulk approve/enroll for enrollment requests (backend change — ask first).
