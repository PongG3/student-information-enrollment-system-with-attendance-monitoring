// Bulk actions on a list ([data-bulk-form]): tick rows, then press one of the bulk buttons.
// Each ticked item says how many requests it holds (data-bulk-pending / data-bulk-approved), so the
// buttons only turn on when there is something to approve or enroll, and the count says what will happen.
// Set up every bulk form on the page that is not set up yet. Runs on load, and again when a live filter
// replaces the list (the new list brings a new bulk form and new tick boxes).
const wireBulkForms = () => document.querySelectorAll('[data-bulk-form]:not([data-bulk-wired])').forEach((form) => {
    form.dataset.bulkWired = '';
    const items = () => [...document.querySelectorAll(`[data-bulk-item][form="${form.id}"]`)];
    const all = form.querySelector('[data-bulk-all]') || document.querySelector('[data-bulk-all]');
    const count = form.querySelector('[data-bulk-count]');
    const buttons = form.querySelectorAll('[data-bulk-button]');
    const idle = count.textContent;
    const plural = (n, word) => `${n} ${word}${n === 1 ? '' : 's'}`;

    const update = () => {
        const ticked = items().filter((box) => box.checked);
        const pending = ticked.reduce((sum, box) => sum + Number(box.dataset.bulkPending || 0), 0);
        const approved = ticked.reduce((sum, box) => sum + Number(box.dataset.bulkApproved || 0), 0);

        buttons.forEach((button) => {
            // Approve needs pending requests, Enroll needs approved ones.
            const n = button.value === 'approve' ? pending : approved;
            button.disabled = n === 0;
            button.textContent = (button.value === 'approve' ? 'Approve' : 'Enroll') + (n ? ` ${n}` : ' selected');
        });

        const parts = [pending && `${pending} pending`, approved && `${approved} approved`].filter(Boolean).join(', ');
        count.textContent = ticked.length === 0 ? idle : `${plural(ticked.length, 'student')} selected` + (parts ? ` (${parts})` : '');

        if (all) {
            all.checked = ticked.length > 0 && ticked.length === items().length;
            all.indeterminate = ticked.length > 0 && ticked.length < items().length;
        }
    };

    all?.addEventListener('change', () => {
        items().forEach((box) => { box.checked = all.checked; });
        update();
    });
    form.bulkUpdate = update;
    update();
});

// One listener for every tick box, whichever list it came with.
document.addEventListener('change', (event) => {
    if (!event.target.matches?.('[data-bulk-item]')) return;
    document.getElementById(event.target.getAttribute('form'))?.bulkUpdate?.();
});
wireBulkForms();
document.addEventListener('live-filter:updated', wireBulkForms);

// Grouped lists: the arrow beside a student shows or hides their class lines.
document.addEventListener('click', (event) => {
    const toggle = event.target.closest('[data-request-toggle]');
    if (toggle) {
        const group = document.getElementById(toggle.dataset.requestToggle);
        const open = group.classList.toggle('is-collapsed') === false;
        toggle.setAttribute('aria-expanded', String(open));
        return;
    }

    const all = event.target.closest('[data-request-toggle-all]');
    if (all) {
        const collapse = all.dataset.requestToggleAll === 'collapse';
        document.querySelectorAll('[data-request-toggle]').forEach((button) => {
            document.getElementById(button.dataset.requestToggle)?.classList.toggle('is-collapsed', collapse);
            button.setAttribute('aria-expanded', String(!collapse));
        });
        all.dataset.requestToggleAll = collapse ? 'expand' : 'collapse';
        all.textContent = collapse ? 'Expand all' : 'Collapse all';
    }
});
