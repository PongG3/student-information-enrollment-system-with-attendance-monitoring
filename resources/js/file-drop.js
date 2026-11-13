// Drop boxes ([data-file-drop], see components/file-drop.blade.php): drag a file in or browse,
// then the chosen file shows as a chip with its size and a remove button.
const sizeText = (bytes) => (bytes < 1024 * 1024 ? `${Math.max(1, Math.round(bytes / 1024))} KB` : `${(bytes / 1024 / 1024).toFixed(1)} MB`);

document.querySelectorAll('[data-file-drop]').forEach((box) => {
    const input = box.querySelector('input[type="file"]');
    const zone = box.querySelector('[data-file-drop-zone]');
    const chip = box.querySelector('[data-file-drop-chip]');
    const error = box.querySelector('[data-file-drop-error]');
    const maxBytes = Number(box.dataset.maxMb || 5) * 1024 * 1024;
    const accepted = (input.accept || '').split(',').map((s) => s.trim().toLowerCase()).filter(Boolean);

    const fits = (file) => !accepted.length || accepted.some((rule) => (rule.startsWith('.')
        ? file.name.toLowerCase().endsWith(rule)
        : file.type === rule || (rule.endsWith('/*') && file.type.startsWith(rule.slice(0, -1)))));

    const show = () => {
        const file = input.files[0];
        error.hidden = true;
        input.classList.remove('is-invalid');
        if (file && !fits(file)) {
            error.textContent = 'This kind of file is not accepted.';
        } else if (file && file.size > maxBytes) {
            error.textContent = `The file is larger than ${box.dataset.maxMb} MB.`;
        } else {
            zone.hidden = !!file;
            chip.hidden = !file;
            if (file) {
                chip.querySelector('[data-file-drop-name]').textContent = file.name;
                chip.querySelector('[data-file-drop-size]').textContent = sizeText(file.size);
            }
            return;
        }
        // Not accepted: say why and clear the choice.
        error.hidden = false;
        input.value = '';
        zone.hidden = false;
        chip.hidden = true;
    };

    input.addEventListener('change', show);
    box.querySelector('[data-file-drop-remove]').addEventListener('click', () => {
        input.value = '';
        show();
        zone.focus?.();
    });

    ['dragenter', 'dragover'].forEach((type) => zone.addEventListener(type, (event) => {
        event.preventDefault();
        zone.classList.add('is-over');
    }));
    ['dragleave', 'dragend'].forEach((type) => zone.addEventListener(type, () => zone.classList.remove('is-over')));
    zone.addEventListener('drop', (event) => {
        event.preventDefault();
        zone.classList.remove('is-over');
        const file = event.dataTransfer?.files?.[0];
        if (!file) return;
        const list = new DataTransfer();
        list.items.add(file);
        input.files = list.files;
        show();
    });

    // A form reset (e.g. the dialog reopened for another day) clears the chip too.
    input.form?.addEventListener('reset', () => setTimeout(show));

    // A required file: sending with nothing chosen is stopped here, so the window stays open and says why
    // (otherwise the page reloads, the window closes, and the error is hidden behind it).
    if (box.dataset.required) {
        input.form?.addEventListener('submit', (event) => {
            if (input.files.length) return;
            event.preventDefault();
            event.stopImmediatePropagation();
            error.textContent = box.dataset.required;
            error.hidden = false;
            input.classList.add('is-invalid');
            zone.focus?.();
        }, true);
        // Opening the window again starts clean.
        box.closest('.modal')?.addEventListener('hidden.bs.modal', () => { input.value = ''; show(); });
    }
});
