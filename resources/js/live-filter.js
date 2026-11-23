// Live filters: a GET filter form marked [data-live-filter] updates its results without a page reload.
//  - Typing in the search box waits 300 ms after the last key, so fast typing sends one request, not ten.
//  - Dropdowns and checkboxes apply at once. Every field is sent together, so they always combine.
//  - Only the newest request counts (older ones are cancelled), and the address bar keeps the filters,
//    so Back, Refresh and sharing the link show the same list.
//  - The page fetches itself and swaps the parts marked [data-live-region="name"] (results, clear link...).
//    Links marked [data-live-query] (e.g. Export CSV) get the same filters.
// Without JavaScript the form still works with its normal button.
const DEBOUNCE_MS = 300;

document.querySelectorAll('form[data-live-filter]').forEach((form) => {
    let timer;
    let controller;

    const query = () => {
        const params = new URLSearchParams();
        new FormData(form).forEach((value, key) => {
            const text = String(value).trim();
            if (text !== '') params.append(key, text);
        });
        return params.toString();
    };

    const regions = () => document.querySelectorAll('[data-live-region]');

    const run = async () => {
        clearTimeout(timer);
        const qs = query();
        const url = form.action.split('?')[0] + (qs ? `?${qs}` : '');

        controller?.abort();
        controller = new AbortController();
        regions().forEach((el) => el.setAttribute('aria-busy', 'true'));

        try {
            const response = await fetch(url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' },
                credentials: 'same-origin',
                signal: controller.signal,
            });
            // Signed out, or anything unexpected: fall back to a normal page load.
            if (!response.ok || response.redirected) {
                window.location.href = url;
                return;
            }
            const page = new DOMParser().parseFromString(await response.text(), 'text/html');

            regions().forEach((el) => {
                const fresh = page.querySelector(`[data-live-region="${el.dataset.liveRegion}"]`);
                if (fresh) el.innerHTML = fresh.innerHTML;
            });
            document.querySelectorAll('a[data-live-query]').forEach((link) => {
                link.href = link.href.split('?')[0] + (qs ? `?${qs}` : '');
            });
            history.replaceState(history.state, '', url);
            document.dispatchEvent(new CustomEvent('live-filter:updated'));
        } catch (error) {
            if (error.name !== 'AbortError') window.location.href = url;
            return;
        } finally {
            regions().forEach((el) => el.removeAttribute('aria-busy'));
        }
    };

    const later = () => {
        clearTimeout(timer);
        timer = setTimeout(run, DEBOUNCE_MS);
    };

    form.addEventListener('input', (event) => {
        if (event.target.matches('input[type="search"], input[type="text"]')) later();
    });
    form.addEventListener('change', (event) => {
        if (event.target.matches('select, input[type="checkbox"], input[type="radio"], input[type="date"]')) run();
    });
    // Enter (or the button) applies at once instead of reloading the page.
    form.addEventListener('submit', (event) => {
        event.preventDefault();
        run();
    });
    // "Clear filters" (in the bar or in an empty result): empty every field and show everything.
    document.addEventListener('click', (event) => {
        if (!event.target.closest('[data-live-clear]')) return;
        event.preventDefault();
        // Fields marked data-live-keep (e.g. the term) are not filters to clear.
        form.querySelectorAll('input[type="search"]:not([data-live-keep]), input[type="text"]:not([data-live-keep])').forEach((el) => { el.value = ''; });
        form.querySelectorAll('select:not([data-live-keep])').forEach((el) => { el.selectedIndex = 0; });
        form.querySelectorAll('input[type="checkbox"]:not([data-live-keep]), input[type="radio"]:not([data-live-keep])').forEach((el) => { el.checked = false; });
        run();
        form.querySelector('input[type="search"]')?.focus();
    });
});
