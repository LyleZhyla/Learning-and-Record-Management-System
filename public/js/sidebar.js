(function () {
    const sidebar = document.getElementById('sidebar');

    if (!sidebar) {
        return;
    }

    const desktopQuery = window.matchMedia('(min-width: 761px)');
    const collapseButton = sidebar.querySelector('.sidebar-toggle');
    const menuButton = document.querySelector('.menu-button');
    const storageKey = 'snapie.sidebar.collapsed';

    const navItems = sidebar.querySelectorAll('.nav-link');
    navItems.forEach((item) => {
        const label = item.textContent.replace(/\s+/g, ' ').replace(/Soon$/i, '').trim();
        if (label) {
            item.title = label;
        }
    });

    function isCollapsed() {
        return document.body.classList.contains('sidebar-collapsed');
    }

    function syncControls() {
        const desktop = desktopQuery.matches;
        const expanded = desktop ? !isCollapsed() : sidebar.classList.contains('open');

        if (collapseButton) {
            collapseButton.setAttribute('aria-expanded', String(expanded));
            collapseButton.setAttribute('aria-label', desktop
                ? (expanded ? 'Collapse sidebar' : 'Expand sidebar')
                : (expanded ? 'Close navigation' : 'Open navigation'));
            collapseButton.textContent = desktop ? (expanded ? '‹' : '›') : '×';
        }

        if (menuButton) {
            menuButton.setAttribute('aria-expanded', String(expanded));
        }
    }

    function toggleSidebar() {
        if (desktopQuery.matches) {
            document.body.classList.toggle('sidebar-collapsed');
            localStorage.setItem(storageKey, isCollapsed() ? 'true' : 'false');
        } else {
            sidebar.classList.toggle('open');
        }

        syncControls();
    }

    if (localStorage.getItem(storageKey) === 'true' && desktopQuery.matches) {
        document.body.classList.add('sidebar-collapsed');
    }

    collapseButton?.addEventListener('click', toggleSidebar);
    menuButton?.addEventListener('click', toggleSidebar);

    desktopQuery.addEventListener('change', () => {
        sidebar.classList.remove('open');
        document.body.classList.toggle(
            'sidebar-collapsed',
            desktopQuery.matches && localStorage.getItem(storageKey) === 'true'
        );
        syncControls();
    });

    syncControls();
})();
