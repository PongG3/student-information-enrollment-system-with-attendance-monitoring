// System Settings page: live attendance preview, logo preview, and an unsaved-changes bar.
// The percentage rules below mirror App\Services\AttendanceStats so the preview matches real reports.

const form = document.querySelector('[data-settings-form]');

if (form) {
    const field = (name) => form.elements.namedItem(name);
    const out = (name) => form.querySelectorAll(`[data-out="${name}"]`);
    const setText = (name, text) => out(name).forEach((el) => { el.textContent = text; });
    const int = (el, fallback = 0) => {
        const n = parseInt(el?.value ?? '', 10);
        return Number.isFinite(n) && n >= 0 ? n : fallback;
    };
    const fmt = (n) => `${(Math.round(n * 10) / 10).toFixed(1)}%`;

    // ---- Attendance preview ----
    const preview = form.querySelector('[data-attendance-preview]');
    const minMeetings = parseInt(preview?.dataset.minMeetings ?? '3', 10);
    const count = (key) => int(preview?.querySelector(`[data-count="${key}"]`));

    function updatePreview() {
        if (!preview) return;
        const c = { present: count('present'), late: count('late'), absent: count('absent'), excused: count('excused') };
        const lateCounts = field('late_counts_as_attended')?.checked ?? true;
        const excused = field('excused_in_percentage')?.value ?? 'excluded';
        const threshold = int(field('absentee_threshold_percent'), 20);

        const total = c.present + c.late + c.absent + c.excused;
        const counted = excused === 'excluded' ? total - c.excused : total;
        const attended = c.present + (lateCounts ? c.late : 0) + (excused === 'attended' ? c.excused : 0);

        if (counted <= 0) {
            setText('percentage', '—');
            setText('absence', '—');
            setText('formula', 'No meetings count toward the percentage yet.');
            out('flag').forEach((el) => { el.innerHTML = '<span class="badge text-bg-secondary">Not enough meetings</span>'; });
            return;
        }

        const parts = [`${c.present} present`];
        if (lateCounts && c.late) parts.push(`${c.late} late`);
        if (excused === 'attended' && c.excused) parts.push(`${c.excused} excused`);
        const left = excused === 'excluded' && c.excused ? ` (${c.excused} excused left out)` : '';

        const rate = c.absent / counted * 100;
        const flagged = counted >= minMeetings && Math.round(rate * 10) / 10 > threshold;

        setText('percentage', fmt(attended / counted * 100));
        const attendedText = parts.length > 1 ? `(${parts.join(' + ')})` : parts[0];
        setText('formula', `${attendedText} ÷ ${counted} counted meetings${left}`);
        setText('absence', `${fmt(rate)} (alert above ${threshold}%)`);
        out('flag').forEach((el) => {
            el.innerHTML = flagged
                ? '<span class="badge text-bg-danger">Flagged for frequent absence</span>'
                : counted < minMeetings
                    ? `<span class="badge text-bg-secondary">Needs ${minMeetings}+ meetings for an alert</span>`
                    : '<span class="badge text-bg-success">Not flagged</span>';
        });
    }

    // ---- "What this means today" ----
    function updateEffects() {
        const days = int(field('attendance_edit_window_days'), 0);
        const date = new Date();
        date.setDate(date.getDate() - days);
        setText('window-date', date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }));

        const minutes = int(field('session_lifetime_minutes'), 0);
        setText('session', minutes >= 60 && minutes % 60 === 0
            ? `${minutes / 60} hour${minutes === 60 ? '' : 's'}`
            : `${minutes} minutes`);
    }

    // ---- Logo preview ----
    const logoInput = form.querySelector('[data-logo-input]');
    const logoImg = form.querySelector('[data-logo-preview]');
    const logoEmpty = form.querySelector('[data-logo-empty]');
    const logoBox = form.querySelector('[data-logo-box]');
    const logoRemove = form.querySelector('[data-logo-remove]');
    const originalLogo = logoImg?.getAttribute('src') || '';
    let objectUrl = null;

    function showLogo(src) {
        if (!logoImg) return;
        logoImg.hidden = !src;
        if (logoEmpty) logoEmpty.hidden = !!src;
        if (src) logoImg.src = src; else logoImg.removeAttribute('src');
    }

    logoInput?.addEventListener('change', () => {
        if (objectUrl) URL.revokeObjectURL(objectUrl);
        const file = logoInput.files?.[0];
        objectUrl = file ? URL.createObjectURL(file) : null;
        showLogo(objectUrl || originalLogo);
        if (file && logoRemove) logoRemove.checked = false;
        logoBox?.classList.toggle('is-removing', !!logoRemove?.checked);
    });
    logoRemove?.addEventListener('change', () => logoBox?.classList.toggle('is-removing', logoRemove.checked));

    // ---- Unsaved changes ----
    const savebar = form.querySelector('[data-settings-savebar]');
    const snapshot = () => JSON.stringify([...new FormData(form)]
        .filter(([name]) => name !== '_token' && name !== '_method')
        .map(([name, value]) => [name, value instanceof File ? `${value.name}:${value.size}` : value]));
    let initial = snapshot();
    let submitting = false;
    const isDirty = () => snapshot() !== initial;

    function refresh() {
        if (savebar) savebar.hidden = !isDirty();
        updatePreview();
        updateEffects();
    }

    form.addEventListener('input', refresh);
    form.addEventListener('change', refresh);
    form.addEventListener('submit', () => { submitting = true; });
    window.addEventListener('beforeunload', (event) => {
        if (!submitting && isDirty()) {
            event.preventDefault();
            event.returnValue = '';
        }
    });

    form.querySelectorAll('[data-settings-discard]').forEach((link) => link.addEventListener('click', (event) => {
        event.preventDefault();
        form.reset();
        if (objectUrl) { URL.revokeObjectURL(objectUrl); objectUrl = null; }
        showLogo(originalLogo);
        logoBox?.classList.remove('is-removing');
        initial = snapshot();
        refresh();
    }));

    refresh();
}
