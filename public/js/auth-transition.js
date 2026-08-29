(function () {
    let shell = document.querySelector('.auth-shell');
    const pageCache = new Map();
    let navigating = false;

    if (!shell) return;

    const loadPage = (url) => {
        if (!pageCache.has(url)) {
            pageCache.set(url, fetch(url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            }).then((response) => {
                if (!response.ok) throw new Error('Unable to load authentication page.');
                return response.text();
            }));
        }

        return pageCache.get(url);
    };

    const swapPage = async (url, updateHistory) => {
        if (navigating) return;
        navigating = true;
        shell.setAttribute('aria-busy', 'true');

        try {
            const html = await loadPage(url);
            const nextDocument = new DOMParser().parseFromString(html, 'text/html');
            const nextShell = nextDocument.querySelector('.auth-shell');

            if (!nextShell) throw new Error('Authentication panel is missing.');

            const updateMeta = () => {
                document.title = nextDocument.title;
                if (updateHistory) window.history.pushState({}, '', url);
            };

            const update = () => {
                document.body.classList.add('auth-switching');
                shell.className = nextShell.className;
                shell.innerHTML = nextShell.innerHTML;
                updateMeta();
            };

            if (document.startViewTransition && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                const transition = document.startViewTransition(update);
                await transition.finished;
            } else if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                document.body.classList.add('auth-switching');
                nextShell.classList.add('auth-switch-layer');
                nextShell.setAttribute('aria-hidden', 'true');
                document.body.appendChild(nextShell);

                const oldPanel = shell.querySelector('.auth-panel');
                const oldVisual = shell.querySelector('.auth-visual');
                const newPanel = nextShell.querySelector('.auth-panel');
                const newVisual = nextShell.querySelector('.auth-visual');
                const movingPairs = [];
                const transition = 'transform 680ms cubic-bezier(.76, 0, .24, 1), opacity 680ms ease';

                [
                    [oldPanel, newPanel],
                    [oldVisual, newVisual],
                ].forEach(([oldElement, newElement]) => {
                    if (!oldElement || !newElement || getComputedStyle(oldElement).display === 'none') return;

                    const oldLeft = oldElement.getBoundingClientRect().left;
                    const newLeft = newElement.getBoundingClientRect().left;
                    const distance = newLeft - oldLeft;

                    oldElement.style.transition = transition;
                    newElement.style.transition = transition;
                    newElement.style.opacity = '0';
                    newElement.style.transform = `translateX(${-distance}px)`;
                    movingPairs.push({ oldElement, newElement, distance });
                });

                await new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve)));
                movingPairs.forEach(({ oldElement, newElement, distance }) => {
                    oldElement.style.opacity = '0';
                    oldElement.style.transform = `translateX(${distance}px)`;
                    newElement.style.opacity = '1';
                    newElement.style.transform = 'translateX(0)';
                });
                await new Promise((resolve) => window.setTimeout(resolve, 700));
                shell.remove();
                nextShell.classList.remove('auth-switch-layer');
                nextShell.removeAttribute('aria-hidden');
                shell = nextShell;
                updateMeta();
            } else {
                update();
            }

            shell.removeAttribute('aria-busy');
            document.body.classList.remove('auth-switching');
            shell.querySelector('input[autofocus]')?.focus({ preventScroll: true });
        } catch (error) {
            window.location.assign(url);
        } finally {
            navigating = false;
        }
    };

    document.addEventListener('click', (event) => {
        const link = event.target.closest('[data-auth-direction]');
        if (!link || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;

        event.preventDefault();
        swapPage(link.href, true);
    });

    document.addEventListener('pointerover', (event) => {
        const link = event.target.closest('[data-auth-direction]');
        if (link) loadPage(link.href).catch(() => pageCache.delete(link.href));
    });

    window.addEventListener('popstate', () => swapPage(window.location.href, false));

    const alternatePage = document.querySelector('[data-auth-direction]')?.href;
    if (alternatePage) loadPage(alternatePage).catch(() => pageCache.delete(alternatePage));
})();
