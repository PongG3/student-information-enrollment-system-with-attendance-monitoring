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
