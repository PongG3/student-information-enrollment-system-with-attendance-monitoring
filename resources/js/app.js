import '@fontsource-variable/inter';
import * as bootstrap from 'bootstrap';

window.bootstrap = bootstrap;

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

    // Toast notifications appear automatically.
    document.querySelectorAll('.toast').forEach((el) => bootstrap.Toast.getOrCreateInstance(el).show());

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
        sheet.addEventListener('change', (event) => {
            const radio = event.target;
            if (radio.type === 'radio' && radio.value === 'excused') {
                const remarks = radio.closest('tr')?.querySelector('input[name$="[remarks]"]');
                if (remarks && !remarks.value) remarks.focus();
            }
            tally();
        });
        sheet.querySelector('[data-mark-all]')?.addEventListener('click', (event) => {
            const value = event.currentTarget.dataset.markAll;
            sheet.querySelectorAll(`input[type="radio"][value="${value}"]`).forEach((r) => { r.checked = true; });
            tally();
        });
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
