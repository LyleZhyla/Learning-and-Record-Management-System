(function () {
    const root = document.documentElement;
    const toggles = document.querySelectorAll('[data-theme-toggle]');
    const systemTheme = window.matchMedia('(prefers-color-scheme: dark)');
    const storageKey = 'snapie.theme';

    function currentTheme() {
        return root.dataset.theme === 'dark' ? 'dark' : 'light';
    }

    function syncControls() {
        const dark = currentTheme() === 'dark';

        toggles.forEach((toggle) => {
            const label = dark ? 'Switch to light mode' : 'Switch to dark mode';
            toggle.setAttribute('aria-label', label);
            toggle.setAttribute('title', label);
            toggle.setAttribute('aria-pressed', String(dark));

            const icon = toggle.querySelector('[data-theme-icon]');
            if (icon) {
                icon.textContent = dark ? '☀' : '☾';
            }
        });
    }

    function setTheme(theme, persist) {
        root.dataset.theme = theme;

        if (persist) {
            localStorage.setItem(storageKey, theme);
        }

        syncControls();
    }

    toggles.forEach((toggle) => toggle.addEventListener('click', () => {
        setTheme(currentTheme() === 'dark' ? 'light' : 'dark', true);
    }));

    systemTheme.addEventListener('change', (event) => {
        if (!localStorage.getItem(storageKey)) {
            setTheme(event.matches ? 'dark' : 'light', false);
        }
    });

    syncControls();
})();
