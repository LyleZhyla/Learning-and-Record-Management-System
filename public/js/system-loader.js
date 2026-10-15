(() => {
    const loader = document.querySelector('[data-system-loader]');

    if (!loader) return;

    let fallbackTimer;

    const hide = () => {
        window.clearTimeout(fallbackTimer);
        loader.hidden = true;
        loader.setAttribute('aria-hidden', 'true');
        document.documentElement.classList.remove('system-is-loading');
    };

    const show = () => {
        window.clearTimeout(fallbackTimer);
        loader.hidden = false;
        loader.setAttribute('aria-hidden', 'false');
        document.documentElement.classList.add('system-is-loading');
        fallbackTimer = window.setTimeout(hide, 15000);
    };

    const isSamePageLink = (url) =>
        url.origin === window.location.origin
        && url.pathname === window.location.pathname
        && url.search === window.location.search
        && Boolean(url.hash);

    document.addEventListener('click', (event) => {
        if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

        const link = event.target.closest('a[href]');
        if (!link || link.hasAttribute('download') || link.dataset.noLoader !== undefined || link.target === '_blank') return;

        const url = new URL(link.href, window.location.href);
        if (!['http:', 'https:'].includes(url.protocol) || url.origin !== window.location.origin || isSamePageLink(url)) return;

        show();
    });

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (event.defaultPrevented || !(form instanceof HTMLFormElement) || form.dataset.noLoader !== undefined || form.target === '_blank') return;

        show();
    });

    window.addEventListener('beforeunload', show);
    window.addEventListener('pageshow', hide);
    window.addEventListener('pagehide', () => window.clearTimeout(fallbackTimer));
})();
