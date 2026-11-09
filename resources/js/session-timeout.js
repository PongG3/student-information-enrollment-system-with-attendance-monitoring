// Shows "Session timed out" on the page as soon as the server-side session has run out.
//
// The session lasts N idle minutes, counted from the last request to the server. Every page
// load counts as activity, in any open tab, so the time of the latest load is shared through
// localStorage: a person working in another tab is not shown the notice.

const modalEl = document.querySelector('[data-session-timeout]');

if (modalEl) {
    const KEY = 'sies.session.lastActivity';
    const lifetimeMs = parseInt(modalEl.dataset.lifetimeMinutes, 10) * 60 * 1000;
    const graceMs = 5000; // a little later than the server, so the session has surely ended
    const loadedAt = Date.now();
    let shown = false;

    const read = () => {
        try { return parseInt(localStorage.getItem(KEY) ?? '0', 10) || 0; } catch (e) { return 0; }
    };
    const write = (time) => {
        try { localStorage.setItem(KEY, String(time)); } catch (e) { /* private mode: this tab only */ }
    };

    // This page load is activity.
    write(Math.max(read(), loadedAt));

    function show() {
        if (shown) return;
        shown = true;
        clearInterval(timer);

        // Close anything else that is open (other dialogs, menus) so the notice is clear.
        document.querySelectorAll('.modal.show').forEach((el) => window.bootstrap?.Modal.getInstance(el)?.hide());
        document.body.classList.remove('sidebar-open');

        // Focus the button once the dialog has finished opening, so Enter signs in again.
        modalEl.addEventListener('shown.bs.modal', () => modalEl.querySelector('[data-session-relogin]')?.focus(), { once: true });
        window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }

    function check() {
        if (shown || !Number.isFinite(lifetimeMs) || lifetimeMs <= 0) return;
        const lastActivity = Math.max(read(), loadedAt);
        if (Date.now() - lastActivity >= lifetimeMs + graceMs) show();
    }

    const timer = setInterval(check, 15000);
    // Check at once when coming back to the tab or waking the computer.
    document.addEventListener('visibilitychange', () => { if (!document.hidden) check(); });
    window.addEventListener('focus', check);

    // Tests and support can call this to see the dialog without waiting.
    window.siesSessionTimeoutCheck = check;
}
