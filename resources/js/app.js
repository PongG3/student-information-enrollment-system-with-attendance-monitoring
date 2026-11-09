import '@fontsource-variable/inter';
import * as bootstrap from 'bootstrap';

window.bootstrap = bootstrap;

document.addEventListener('DOMContentLoaded', () => {
    // Charts load only on pages that contain one.
    if (document.querySelector('canvas[data-chart]')) {
        import('./charts.js');
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

    const closeMenu = () => document.body.classList.remove('sidebar-open');
    document.getElementById('sidebarToggle')?.addEventListener('click', () => document.body.classList.toggle('sidebar-open'));
    document.getElementById('sidebarBackdrop')?.addEventListener('click', closeMenu);
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape') closeMenu(); });
    document.querySelectorAll('.sidebar .nav-link').forEach((link) => link.addEventListener('click', closeMenu));

    // Toast notifications appear automatically.
    document.querySelectorAll('.toast').forEach((el) => bootstrap.Toast.getOrCreateInstance(el).show());

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
            submit.className = 'btn px-4 ' + (button.dataset.confirmVariant === 'danger' ? 'btn-danger' : 'btn-navy');
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
