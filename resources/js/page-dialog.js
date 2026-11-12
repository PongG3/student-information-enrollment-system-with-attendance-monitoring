// Pages shown inside a dialog.
//
// Outside (the normal window): a link or button with data-dialog opens its page in the shared
// #pageDialog, as "?embed=1" (no menu, no top bar). data-dialog-size="lg|xl" picks the width.
//
// Inside (the embedded page):
//  - forms keep working; a failed save comes back inside the dialog with its errors,
//    a successful one tells the window behind to move on (App\Http\Middleware\DialogEmbed);
//  - links to the same page (search, "Choose", "Change student") stay inside the dialog,
//    other links open in the main window; [data-dialog-close] (Cancel) closes the dialog;
//  - the page reports its height so the dialog fits its content (and never the window's height).
//  - a page may set its own width: <x-app-layout dialog-size="lg|xl">.

const ORIGIN = window.location.origin;
const withEmbed = (href) => {
    const url = new URL(href, window.location.href);
    url.searchParams.set('embed', '1');
    return url.toString();
};

if (document.body.hasAttribute('data-embedded')) {
    // ---------- Inside the dialog ----------
    const tell = (message) => window.parent.postMessage(message, ORIGIN);
    const meta = (name) => document.querySelector(`meta[name="${name}"]`)?.content || '';
    tell({ type: 'sies-dialog-title', title: meta('dialog-title'), subtitle: meta('dialog-subtitle'), size: meta('dialog-size') });

    // Height of the content (not of the frame), sent whenever it changes (tabs, errors, steps).
    const content = document.querySelector('.embed-content');
    const report = () => tell({ type: 'sies-dialog-size', height: Math.ceil(content.getBoundingClientRect().height) });
    new ResizeObserver(report).observe(content);
    window.addEventListener('load', report);
    report();

    // Forms: GET forms (search, filters) stay embedded; others say they come from a dialog.
    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || form.target) return;
        const method = (form.getAttribute('method') || 'get').toLowerCase();
        const name = method === 'get' ? 'embed' : '_embed';
        if (!form.querySelector(`input[name="${name}"]`)) {
            const marker = document.createElement('input');
            Object.assign(marker, { type: 'hidden', name, value: '1' });
            form.appendChild(marker);
        }
    }, true);

    document.addEventListener('click', (event) => {
        const close = event.target.closest('[data-dialog-close]');
        if (close) { event.preventDefault(); tell({ type: 'sies-dialog-close' }); return; }

        const link = event.target.closest('a[href]');
        if (!link || link.target || link.hasAttribute('download') || link.getAttribute('href').startsWith('#')
            || link.dataset.bsToggle || event.ctrlKey || event.metaKey || event.shiftKey) return;
        const url = new URL(link.href, window.location.href);
        if (url.origin !== ORIGIN) return;

        event.preventDefault();
        if (url.pathname === window.location.pathname) {
            window.location.href = withEmbed(url);          // next step of the same task: stay inside
        } else {
            tell({ type: 'sies-dialog-navigate', url: url.toString() }); // anything else: the main window
        }
    });
} else {
    // ---------- The normal window ----------
    const dialogEl = document.getElementById('pageDialog');
    if (dialogEl) {
        const frame = dialogEl.querySelector('[data-page-dialog-frame]');
        const sizeBox = dialogEl.querySelector('[data-page-dialog-size]');
        const title = dialogEl.querySelector('[data-page-dialog-title]');
        const subtitle = dialogEl.querySelector('[data-page-dialog-subtitle]');
        const loading = dialogEl.querySelector('[data-page-dialog-loading]');
        const modal = () => window.bootstrap.Modal.getOrCreateInstance(dialogEl);
        // Room for the dialog's own margins and header.
        const maxHeight = () => Math.max(240, window.innerHeight - 150);

        const setSize = (size) => {
            sizeBox.className = 'modal-dialog modal-dialog-centered' + (size ? ` modal-${size}` : '');
        };

        const open = (href, size, label) => {
            setSize(size);
            title.textContent = label || 'Loading…';
            subtitle.textContent = '';
            loading.hidden = false;
            frame.style.height = '0px';
            frame.src = withEmbed(href);
            modal().show();
        };

        document.addEventListener('click', (event) => {
            const trigger = event.target.closest('[data-dialog]');
            if (!trigger || event.ctrlKey || event.metaKey || event.shiftKey) return; // Ctrl+click: open as a page
            const href = trigger.getAttribute('href') || trigger.dataset.dialog;
            if (!href) return;
            event.preventDefault();
            open(href, trigger.dataset.dialogSize, trigger.dataset.dialogTitle);
        });

        // Size the frame as soon as its page is in (the page's own reports then keep it in step).
        const fit = (height) => { frame.style.height = Math.min(height, maxHeight()) + 'px'; };
        frame.addEventListener('load', () => {
            loading.hidden = true;
            const content = frame.contentDocument?.querySelector('.embed-content');
            if (content) fit(Math.ceil(content.getBoundingClientRect().height));
        });

        window.addEventListener('message', (event) => {
            if (event.origin !== ORIGIN || event.source !== frame.contentWindow || !event.data) return;
            const message = event.data;
            if (message.type === 'sies-dialog-size') {
                fit(message.height);
            } else if (message.type === 'sies-dialog-title') {
                if (message.title) title.textContent = message.title;
                subtitle.textContent = message.subtitle || '';
                // A page can ask for its own width (e.g. a narrow search step, then a wide picker).
                if (message.size) setSize(message.size);
            } else if (message.type === 'sies-dialog-close') {
                modal().hide();
            } else if (message.type === 'sies-dialog-navigate' || message.type === 'sies-dialog-done') {
                window.location.href = message.url;
            }
        });

        // Closing empties the frame, so the next opening starts fresh.
        dialogEl.addEventListener('hidden.bs.modal', () => { frame.src = 'about:blank'; });
    }
}
