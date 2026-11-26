import '@fontsource-variable/inter';
import * as bootstrap from 'bootstrap';
import './page-dialog.js';
import './bulk-select.js';
import './file-drop.js';
import './live-filter.js';

window.bootstrap = bootstrap;

// Ask before an action done on the page itself (no form to send), in the system's own confirmation window
// (#confirmModal, the one Restore and Verify use) instead of the browser's plain pop-up.
// Resolves true for the confirm button, false for Cancel, Esc or a click outside.
window.siesConfirm = ({ title = 'Are you sure?', message = '', button = 'Confirm', variant = 'warning' } = {}) => new Promise((resolve) => {
    const modal = document.getElementById('confirmModal');
    if (!modal) { resolve(true); return; }
    const form = modal.querySelector('form');
    const submit = modal.querySelector('[data-confirm-submit]');
    const danger = variant === 'danger';

    modal.querySelector('[data-confirm-title]').textContent = title;
    modal.querySelector('[data-confirm-message]').textContent = message;
    submit.textContent = button;
    submit.className = 'btn flex-fill ' + (danger ? 'btn-danger' : 'btn-navy');
    modal.querySelector('[data-confirm-icon]').className = 'dialog-icon ' + (danger ? 'is-danger' : 'is-warning');

    let answer = false;
    // The confirm button only closes the window here; nothing is sent to the server.
    const onSubmit = (event) => {
        event.preventDefault();
        event.stopImmediatePropagation();
        answer = true;
        bootstrap.Modal.getInstance(modal)?.hide();
    };
    form.addEventListener('submit', onSubmit, true);
    modal.addEventListener('hidden.bs.modal', () => {
        form.removeEventListener('submit', onSubmit, true);
        resolve(answer);
    }, { once: true });
    bootstrap.Modal.getOrCreateInstance(modal).show();
});

document.addEventListener('DOMContentLoaded', () => {
    // Charts load only on pages that contain one.
    if (document.querySelector('canvas[data-chart]')) {
        import('./charts.js');
    }
    // Signed-in pages: tell the user on the spot when the session has timed out.
    if (document.querySelector('[data-session-timeout]')) {
        import('./session-timeout.js');
    }
    // System Settings: live preview and unsaved-changes bar.
    if (document.querySelector('[data-settings-form]')) {
        import('./settings.js');
    }

    // Sidebar: keep it open (pin) and, on small screens, slide it in and out.
    const root = document.documentElement;
    const pin = document.getElementById('sidebarPin');
    const syncPin = () => {
        if (!pin) return;
        const pinned = root.classList.contains('sidebar-pinned');
        pin.setAttribute('aria-pressed', pinned ? 'true' : 'false');
        pin.setAttribute('aria-label', pinned ? 'Let the menu collapse' : 'Keep the menu open');
        pin.title = pin.getAttribute('aria-label');
    };
    syncPin();
    pin?.addEventListener('click', () => {
        const pinned = root.classList.toggle('sidebar-pinned');
        try { localStorage.setItem('sies.sidebar.pinned', pinned ? '1' : '0'); } catch (e) { /* private mode: fine */ }
        syncPin();
        pin.blur();
    });

    // Desktop: keep the menu open across a page change while the pointer is on it.
    // Clicking inside it marks the next page; that page starts expanded (see the layout's <head>)
    // and lets go once the pointer is really outside the menu.
    const panel = document.querySelector('.sidebar-panel');
    const desktop = window.matchMedia('(min-width: 992px)');
    panel?.addEventListener('click', (event) => {
        const link = event.target.closest('a[href]');
        // Only links that open another page here (not buttons such as Log out, nor new-tab clicks).
        if (desktop.matches && link && !link.target && !event.ctrlKey && !event.metaKey && !event.shiftKey && link.origin === location.origin) {
            try { sessionStorage.setItem('sies.sidebar.hold', '1'); } catch (e) { /* private mode: fine */ }
        }
    });
    if (root.classList.contains('sidebar-hold') && panel) {
        const release = () => root.classList.remove('sidebar-hold');
        // The first pointer movement tells where the cursor is: outside the menu, close it;
        // inside, keep it open until the pointer leaves.
        document.addEventListener('pointermove', (event) => {
            if (!panel.contains(event.target)) release();
            else panel.addEventListener('pointerleave', release, { once: true });
        }, { once: true });
        // Leaving the window, or using the keyboard elsewhere, also lets go.
        document.documentElement.addEventListener('pointerleave', release, { once: true });
        document.addEventListener('focusin', (event) => { if (!panel.contains(event.target)) release(); }, { once: true });
    }

    const closeMenu = () => document.body.classList.remove('sidebar-open');
    document.getElementById('sidebarToggle')?.addEventListener('click', () => document.body.classList.toggle('sidebar-open'));
    document.getElementById('sidebarBackdrop')?.addEventListener('click', closeMenu);
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape') closeMenu(); });
    document.querySelectorAll('.sidebar .nav-link').forEach((link) => link.addEventListener('click', closeMenu));

    // Toast notifications: slide in, close by themselves after 5 seconds (data-bs-delay), and slide out.
    // The bottom bar shows the time left. Pointing at a toast (or focusing its close button) pauses the timer,
    // and leaving it carries on with the time that was left, not the full 5 seconds again. The CSS pauses the bar
    // the same way. We keep our own timer because Bootstrap's restarts from the full delay.
    document.querySelectorAll('.toast').forEach((el) => {
        const delay = Number(el.dataset.bsDelay || 5000);
        el.style.setProperty('--toast-delay', `${delay}ms`);
        const toast = bootstrap.Toast.getOrCreateInstance(el, { autohide: false });
        let remaining = delay;
        let startedAt = 0;
        let timer = null;
        const resume = () => {
            if (timer || ! el.classList.contains('is-timing')) return;
            startedAt = Date.now();
            timer = setTimeout(() => toast.hide(), Math.max(remaining, 0));
        };
        const pause = () => {
            if (! timer) return;
            clearTimeout(timer);
            timer = null;
            remaining -= Date.now() - startedAt;
        };
        el.addEventListener('shown.bs.toast', () => {
            el.classList.add('is-timing');
            if (! el.matches(':hover') && ! el.contains(document.activeElement)) resume();
        });
        el.addEventListener('mouseenter', pause);
        el.addEventListener('focusin', pause);
        el.addEventListener('mouseleave', () => { if (! el.contains(document.activeElement)) resume(); });
        el.addEventListener('focusout', (event) => { if (! el.contains(event.relatedTarget) && ! el.matches(':hover')) resume(); });
        toast.show();
    });

    // Every form that saves something (POST/PUT/DELETE) can only be sent once: a second press is
    // ignored and the pressed button shows a spinner. Search/filter (GET) forms and forms that open
    // in a new tab are left alone. Buttons are not "disabled", so their name/value is still sent.
    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || event.defaultPrevented) return;
        if ((form.getAttribute('method') || 'get').toLowerCase() === 'get' || form.target) return;
        if (form.dataset.submitting === '1') { event.preventDefault(); return; }
        form.dataset.submitting = '1';
        const button = event.submitter || form.querySelector('[type="submit"]');
        button?.classList.add('is-busy');
        button?.setAttribute('aria-busy', 'true');
        // A download keeps the page open: let the form be used again after a while.
        setTimeout(() => releaseForm(form), 10000);
    });
    const releaseForm = (form) => {
        delete form.dataset.submitting;
        form.querySelectorAll('.is-busy').forEach((el) => { el.classList.remove('is-busy'); el.removeAttribute('aria-busy'); });
    };
    // Coming back with the browser's Back button: forms work again.
    window.addEventListener('pageshow', () => document.querySelectorAll('form[data-submitting]').forEach(releaseForm));

    // Attendance sheet: live tally of the choices, "Mark everyone present", and a nudge to the
    // remarks box when Excused is chosen (remarks are required for Excused).
    document.querySelectorAll('[data-attendance-sheet]').forEach((sheet) => {
        const tally = () => {
            const counts = {};
            sheet.querySelectorAll('input[type="radio"][name$="[status]"]:checked').forEach((r) => { counts[r.value] = (counts[r.value] || 0) + 1; });
            sheet.querySelectorAll('[data-tally]').forEach((el) => { el.textContent = counts[el.dataset.tally] || 0; });
        };
        // On phones the remarks box only shows for a student who is not simply present (or who already has a remark).
        const syncRow = (row) => {
            const status = row.querySelector('input[type="radio"][name$="[status]"]:checked')?.value;
            const remarks = row.querySelector('input[name$="[remarks]"]');
            row.classList.toggle('show-remarks', (status && status !== 'present') || !!remarks?.value);
        };
        const markInvalid = (row, bad) => {
            const remarks = row.querySelector('input[name$="[remarks]"]');
            remarks?.classList.toggle('is-invalid', bad);
            row.classList.toggle('row-invalid', bad);
        };
        let dirty = false;
        sheet.addEventListener('change', (event) => {
            const radio = event.target;
            const row = radio.closest('[data-student-row]');
            if (radio.type === 'radio' && row) {
                syncRow(row);
                if (radio.value === 'excused') {
                    const remarks = row.querySelector('input[name$="[remarks]"]');
                    if (remarks && !remarks.value) remarks.focus({ preventScroll: true });
                } else {
                    markInvalid(row, false);
                }
            }
            dirty = true;
            tally();
        });
        sheet.addEventListener('input', (event) => {
            const row = event.target.closest?.('[data-student-row]');
            if (row && event.target.matches('input[name$="[remarks]"]')) {
                syncRow(row);
                if (event.target.value.trim().length >= 3) markInvalid(row, false);
                dirty = true;
            }
        });
        sheet.querySelectorAll('[data-mark-all]').forEach((button) => button.addEventListener('click', async () => {
            const message = button.dataset.markConfirm;
            if (message && !(await window.siesConfirm({
                title: button.dataset.markTitle || 'Are you sure?',
                message,
                button: button.dataset.markButton || 'Confirm',
                variant: 'danger',
            }))) return;
            sheet.querySelectorAll(`input[type="radio"][value="${button.dataset.markAll}"]`).forEach((r) => {
                r.checked = true;
                const row = r.closest('[data-student-row]');
                if (row) { syncRow(row); markInvalid(row, false); }
            });
            dirty = true;
            tally();
        }));

        // Excused needs a reason: show it on the row itself, jump to the first one, and do not send yet.
        sheet.addEventListener('submit', (event) => {
            let first = null;
            sheet.querySelectorAll('[data-student-row]').forEach((row) => {
                const excused = row.querySelector('input[type="radio"][value="excused"]:checked');
                const remarks = row.querySelector('input[name$="[remarks]"]');
                const bad = !!excused && (remarks?.value.trim().length ?? 0) < 3;
                markInvalid(row, bad);
                if (bad && !first) first = row;
            });
            if (first) {
                event.preventDefault();
                event.stopImmediatePropagation();
                first.scrollIntoView({ block: 'center', behavior: 'smooth' });
                first.querySelector('input[name$="[remarks]"]')?.focus({ preventScroll: true });
                return;
            }
            dirty = false;
        }, true);

        // Leaving with marks that were not saved: ask first.
        window.addEventListener('beforeunload', (event) => {
            if (dirty) { event.preventDefault(); event.returnValue = ''; }
        });

        sheet.querySelectorAll('[data-student-row]').forEach(syncRow);
        tally();
    });

    // Class form: show the block being built, e.g. "BSCS 1-C" or "Section C (any block)".
    const blockPreview = document.querySelector('[data-block-preview]');
    if (blockPreview) {
        const part = (name) => document.querySelector(`[data-block-part="${name}"]`);
        const update = () => {
            const program = part('program');
            const code = program?.value ? program.options[program.selectedIndex].text.split(' · ')[0] : '';
            const year = part('year')?.value || '';
            const section = (part('section')?.value || '').trim().toUpperCase();
            blockPreview.textContent = !section ? '' : (code && year ? `${code} ${year}-${section}` : `Section ${section} (any block)`);
        };
        ['program', 'year', 'section'].forEach((name) => part(name)?.addEventListener('input', update));
        ['program', 'year'].forEach((name) => part(name)?.addEventListener('change', update));
        update();
    }

    // Subject form: the program list only applies to major subjects.
    document.querySelectorAll('[data-category-choice]').forEach((choice) => {
        const form = choice.closest('form');
        const programs = form?.querySelector('[data-major-only]');
        if (!programs) return;
        const sync = () => { programs.hidden = form.querySelector('input[name="category"]:checked')?.value !== 'major'; };
        choice.addEventListener('change', sync);
        // The edit dialog fills the form when it opens; sync after that.
        choice.closest('.modal')?.addEventListener('shown.bs.modal', sync);
        sync();
    });

    // Tabbed forms: "← Previous" / "Next →" in the save bar move between tabs; each hides at its end.
    document.querySelectorAll('[data-tab-steps]').forEach((steps) => {
        const tabs = [...document.querySelectorAll(`${steps.dataset.tabSteps} [data-bs-toggle="tab"]`)];
        const current = () => tabs.findIndex((t) => t.classList.contains('active'));
        const sync = () => steps.querySelectorAll('[data-tab-step]').forEach((b) => {
            const target = current() + Number(b.dataset.tabStep);
            b.hidden = target < 0 || target >= tabs.length;
        });
        steps.addEventListener('click', (event) => {
            const button = event.target.closest('[data-tab-step]');
            const tab = button && tabs[current() + Number(button.dataset.tabStep)];
            if (!tab) return;
            window.bootstrap.Tab.getOrCreateInstance(tab).show();
            tab.closest('.form-tabs')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        });
        tabs.forEach((t) => t.addEventListener('shown.bs.tab', sync));
        sync();
    });

    // New enrollment request: live count of chosen classes and units; the total turns red past the unit limit.
    const selection = document.querySelector('[data-selection-summary]');
    if (selection) {
        const form = selection.closest('form');
        const fmt = (n) => String(Math.round(n * 10) / 10);
        const update = () => {
            const chosen = [...form.querySelectorAll('input[name="class_ids[]"]:checked')];
            const units = chosen.reduce((sum, box) => sum + parseFloat(box.dataset.units || 0), 0);
            const total = parseFloat(selection.dataset.held) + units;
            selection.querySelector('[data-selected-count]').textContent = chosen.length;
            selection.querySelector('[data-selected-units]').textContent = fmt(units);
            selection.querySelector('[data-total-units]').textContent = fmt(total);
            selection.classList.toggle('text-danger', total > parseFloat(selection.dataset.max));
        };
        form.addEventListener('change', update);
        update();
    }

    // List tables fill the screen and scroll inside, so the page itself does not scroll.
    // Height = window height − where the table starts − everything below it (row count, page links, footer).
    // Skipped on narrow screens, and whenever that would leave less than ~240px for the rows.
    const fitTables = () => {
        const wide = window.matchMedia('(min-width: 992px) and (min-height: 600px)').matches;
        document.querySelectorAll('[data-fit-viewport]').forEach((box) => {
            box.classList.remove('table-fit');
            box.style.maxHeight = '';
            if (!wide || !box.offsetParent) return;
            const doc = document.documentElement;
            const top = box.getBoundingClientRect().top + window.scrollY;
            const below = doc.scrollHeight - (top + box.offsetHeight);
            const room = Math.floor(window.innerHeight - top - below) - 2; // 2px for rounding
            if (room >= 240 && box.scrollHeight > room) {
                box.style.maxHeight = room + 'px';
                box.classList.add('table-fit');
            }
        });
    };
    fitTables();
    let fitTimer;
    const refit = () => { clearTimeout(fitTimer); fitTimer = setTimeout(fitTables, 60); };
    window.addEventListener('resize', refit);
    window.addEventListener('load', refit);                 // images done
    document.fonts?.ready.then(refit);                      // web font changes line heights
    document.addEventListener('shown.bs.tab', refit);
    document.addEventListener('shown.bs.collapse', refit);  // e.g. report filters opened
    document.addEventListener('hidden.bs.collapse', refit);
    document.addEventListener('live-filter:updated', refit); // a live filter swapped the rows

    // Form dialogs: put the cursor in the first field when they open, so typing can start at once.
    // (Dialogs without fields, such as confirmations, are left alone.)
    document.addEventListener('shown.bs.modal', (event) => {
        const field = event.target.querySelector('form input:not([type="hidden"]):not([disabled]):not([readonly]), form select:not([disabled]), form textarea:not([disabled])');
        if (field && !event.target.contains(document.activeElement?.closest('input, select, textarea'))) field.focus();
    });

    // "Copy" buttons (temporary passwords): copy the text and say so on the button.
    document.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-copy]');
        if (!button) return;
        const source = document.querySelector(button.dataset.copy);
        if (!source) return;
        try {
            await navigator.clipboard.writeText(source.textContent.trim());
            const label = button.textContent;
            button.textContent = 'Copied';
            setTimeout(() => { button.textContent = label; }, 2000);
        } catch (e) {
            window.getSelection()?.selectAllChildren(source); // no clipboard access: select it for Ctrl+C
        }
    });

    // One shared confirmation dialog for dangerous or important actions.
    const confirmModal = document.getElementById('confirmModal');
    if (confirmModal) {
        confirmModal.addEventListener('show.bs.modal', (event) => {
            const button = event.relatedTarget;
            if (!button) return;

            confirmModal.querySelector('[data-confirm-title]').textContent = button.dataset.confirmTitle || 'Are you sure?';
            confirmModal.querySelector('[data-confirm-message]').textContent = button.dataset.confirmMessage || '';

            const form = confirmModal.querySelector('form');
            form.action = button.dataset.confirmAction;
            form.querySelector('input[name="_method"]').value = button.dataset.confirmMethod || 'POST';
            // Extra values to send with the confirmed action, e.g. data-confirm-fields='{"status":"verified"}'.
            form.querySelectorAll('[data-confirm-extra]').forEach((el) => el.remove());
            Object.entries(JSON.parse(button.dataset.confirmFields || '{}')).forEach(([name, value]) => {
                const input = document.createElement('input');
                Object.assign(input, { type: 'hidden', name, value });
                input.dataset.confirmExtra = '';
                form.appendChild(input);
            });

            const submit = confirmModal.querySelector('[data-confirm-submit]');
            submit.textContent = button.dataset.confirmButton || 'Confirm';
            const danger = button.dataset.confirmVariant === 'danger';
            submit.className = 'btn flex-fill ' + (danger ? 'btn-danger' : 'btn-navy');
            confirmModal.querySelector('[data-confirm-icon]').className = 'dialog-icon ' + (danger ? 'is-danger' : 'is-warning');
        });
    }

    // Dialog that asks for a written reason (reject or cancel a request).
    const reasonModal = document.getElementById('reasonModal');
    if (reasonModal) {
        reasonModal.addEventListener('show.bs.modal', (event) => {
            const button = event.relatedTarget;
            if (!button) return;

            reasonModal.querySelector('[data-reason-title]').textContent = button.dataset.reasonTitle || 'Reason';
            reasonModal.querySelector('[data-reason-message]').textContent = button.dataset.reasonMessage || '';
            reasonModal.querySelector('form').action = button.dataset.reasonAction;
            reasonModal.querySelector('textarea').value = '';

            const submit = reasonModal.querySelector('[data-reason-submit]');
            submit.textContent = button.dataset.reasonButton || 'Save';
        });
        reasonModal.addEventListener('shown.bs.modal', () => reasonModal.querySelector('textarea').focus());
    }

    // Add / edit forms that open in a modal.
    document.querySelectorAll('[data-form-modal]').forEach((modal) => {
        modal.addEventListener('show.bs.modal', (event) => {
            const button = event.relatedTarget;
            if (!button) return; // reopened after a validation error: keep what the server rendered

            const form = modal.querySelector('form');
            form.reset();
            form.querySelectorAll('.is-invalid').forEach((el) => el.classList.remove('is-invalid'));
            form.querySelectorAll('.invalid-feedback').forEach((el) => el.remove());

            const method = form.querySelector('input[name="_method"]');
            const editId = form.querySelector('input[name="_edit_id"]');
            const title = modal.querySelector('.modal-title');

            if (button.dataset.action) {
                form.action = button.dataset.action;
                method.value = 'PUT';
                editId.value = button.dataset.id || '';
                title.textContent = modal.dataset.editTitle;

                const values = JSON.parse(button.dataset.values || '{}');
                Object.entries(values).forEach(([name, value]) => {
                    // A list (e.g. program_ids) ticks the matching boxes of a "name[]" checkbox group.
                    if (Array.isArray(value)) {
                        const wanted = value.map(String);
                        form.querySelectorAll(`input[name="${name}[]"]`).forEach((box) => { box.checked = wanted.includes(box.value); });
                        return;
                    }
                    const input = form.elements[name];
                    if (!input) return;
                    if (input.type === 'checkbox') {
                        input.checked = Boolean(value);
                    } else {
                        input.value = value ?? '';
                    }
                });
            } else {
                form.action = modal.dataset.storeAction;
                method.value = 'POST';
                editId.value = '';
                title.textContent = modal.dataset.addTitle;
            }
        });
    });
});

// The photo cropper (and its library) loads only on pages that have one.
if (document.querySelector('[data-photo-crop]')) {
    import('./photo-crop.js');
}

// Downloads that take a moment to build (PDF and Excel exports): show "Preparing…" so nobody clicks twice.
document.addEventListener('click', (event) => {
    const link = event.target.closest('a[data-download]');
    if (!link || link.getAttribute('aria-busy') === 'true') return;
    const label = link.innerHTML;
    link.setAttribute('aria-busy', 'true');
    link.classList.add('is-busy');
    link.textContent = 'Preparing…';   // the spinner itself comes from .is-busy (one shared style)
    // The page does not change on a download, so put the label back after a few seconds.
    setTimeout(() => { link.innerHTML = label; link.removeAttribute('aria-busy'); link.classList.remove('is-busy'); }, 4000);
});
