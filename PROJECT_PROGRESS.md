# SIES Campus Portal — UI/UX Redesign Progress

Last updated: 2026-11-10. Updated after every redesign step; a module is ticked only after it was changed **and** tested.

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
5. **Progressive disclosure** — report filters fold away once a report is shown ("Change filters").

## Viewport audit (after this pass)

No page scrolls sideways at any size. All 38 audited pages fit at 1366×768, 1440×900 and 1920×1080.

| Page | Role | 1366×768 before → after | Fix |
|---|---|---|---|
| Enrollment requests | admin/registrar | +924 → fits | Fitted table |
| Audit logs | admin | +910 → fits | Fitted table |
| Enrollment report | admin/registrar | +970 → fits | Fitted table, folded filters |
| Master-list report | admin/registrar | +318, table scrolled sideways → fits | Compact report table (headers wrap), folded filters |
| Registrar dashboard | registrar | +758 → fits | Overview / Trends tabs; 5 charts in one row |
| Teacher dashboard | teacher | +427 → fits | Overview / Trends tabs |
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
- ✅ Student records list · ✅ Student record (tabs) · ✅ Register / edit student (tabs)
- ✅ Enrollment requests · ✅ New enrollment request
- ✅ Class list · ✅ Class detail · ✅ Add / edit class (two columns)
- ✅ Subjects (major/minor, wide dialog) · ✅ Programs · ✅ Academic years & terms · ✅ Holidays
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
