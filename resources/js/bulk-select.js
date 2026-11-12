// Bulk actions on a list ([data-bulk-form]): tick rows, then press one of the bulk buttons.
// The buttons stay disabled until something is ticked; the count says what will happen.
document.querySelectorAll('[data-bulk-form]').forEach((form) => {
    const items = () => [...document.querySelectorAll(`[data-bulk-item][form="${form.id}"]`)];
    const all = document.querySelector('[data-bulk-all]');
    const count = form.querySelector('[data-bulk-count]');
    const buttons = form.querySelectorAll('[data-bulk-button]');
    const idle = count.textContent;

    const update = () => {
        const ticked = items().filter((box) => box.checked);
        const pending = ticked.filter((box) => box.dataset.bulkItem === 'pending').length;
        const approved = ticked.length - pending;
        buttons.forEach((button) => {
            // Approve needs pending requests, Enroll needs approved ones.
            button.disabled = button.value === 'approve' ? pending === 0 : approved === 0;
        });
        count.textContent = ticked.length === 0
            ? idle
            : `${ticked.length} selected` + (pending && approved ? ` (${pending} pending, ${approved} approved)` : '');
        if (all) {
            all.checked = ticked.length > 0 && ticked.length === items().length;
            all.indeterminate = ticked.length > 0 && ticked.length < items().length;
        }
    };

    all?.addEventListener('change', () => {
        items().forEach((box) => { box.checked = all.checked; });
        update();
    });
    document.addEventListener('change', (event) => {
        if (event.target.matches?.('[data-bulk-item]')) update();
    });
    update();
});
