(function () {
    const sidebar = document.getElementById('sidebar');

    if (!sidebar) {
        return;
    }

    const desktopQuery = window.matchMedia('(min-width: 761px)');
    const collapseButton = sidebar.querySelector('.sidebar-toggle');
    const menuButton = document.querySelector('.menu-button');
    let backdrop = document.querySelector('[data-sidebar-backdrop]');
    const storageKey = 'snapie.sidebar.collapsed';
    const navigation = sidebar.querySelector('.main-nav');

    function initializeNavigationGroups() {
        if (!navigation) return;

        const labels = [...navigation.querySelectorAll(':scope > .nav-label')];
        const portal = [...document.body.classList].find(className => className.startsWith('portal-')) || 'portal';
        const groupStorageKey = `snapie.sidebar.groups.${portal}`;
        let savedGroups = {};

        try {
            savedGroups = JSON.parse(localStorage.getItem(groupStorageKey)) || {};
        } catch (error) {
            savedGroups = {};
        }

        labels.forEach((label, index) => {
            const groupName = label.textContent.trim();
            const groupId = `sidebar-group-${portal}-${index}`;
            const content = document.createElement('div');
            content.className = 'nav-group-content';
            content.id = groupId;

            let sibling = label.nextElementSibling;
            while (sibling && !sibling.classList.contains('nav-label')) {
                const nextSibling = sibling.nextElementSibling;
                content.appendChild(sibling);
                sibling = nextSibling;
            }
            label.insertAdjacentElement('afterend', content);

            const indicator = document.createElement('span');
            indicator.className = 'nav-group-indicator';
            indicator.setAttribute('aria-hidden', 'true');
            indicator.textContent = '⌄';
            label.appendChild(indicator);
            label.setAttribute('role', 'button');
            label.setAttribute('tabindex', '0');
            label.setAttribute('aria-controls', groupId);
            label.title = `Toggle ${groupName}`;

            const hasActivePage = Boolean(content.querySelector('.nav-link.active'));
            const expanded = Object.prototype.hasOwnProperty.call(savedGroups, groupName)
                ? Boolean(savedGroups[groupName])
                : hasActivePage;

            function setExpanded(nextExpanded, persist = true) {
                content.hidden = !nextExpanded;
                label.classList.toggle('collapsed', !nextExpanded);
                label.setAttribute('aria-expanded', String(nextExpanded));
                if (persist) {
                    savedGroups[groupName] = nextExpanded;
                    localStorage.setItem(groupStorageKey, JSON.stringify(savedGroups));
                }
            }

            function toggleGroup() {
                setExpanded(label.getAttribute('aria-expanded') !== 'true');
            }

            setExpanded(expanded, false);
            label.addEventListener('click', toggleGroup);
            label.addEventListener('keydown', event => {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    toggleGroup();
                }
            });
        });
    }

    initializeNavigationGroups();

    if (!backdrop) {
        backdrop = document.createElement('button');
        backdrop.className = 'sidebar-backdrop';
        backdrop.type = 'button';
        backdrop.hidden = true;
        backdrop.setAttribute('aria-label', 'Close navigation');
        backdrop.setAttribute('data-sidebar-backdrop', '');
        sidebar.insertAdjacentElement('afterend', backdrop);
    }

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

        document.body.classList.toggle('mobile-nav-open', !desktop && expanded);
        if (backdrop) {
            backdrop.hidden = desktop || !expanded;
        }

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
    backdrop?.addEventListener('click', () => {
        sidebar.classList.remove('open');
        syncControls();
    });

    sidebar.querySelectorAll('a.nav-link').forEach((link) => {
        link.addEventListener('click', () => {
            if (!desktopQuery.matches) {
                sidebar.classList.remove('open');
                syncControls();
            }
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !desktopQuery.matches && sidebar.classList.contains('open')) {
            sidebar.classList.remove('open');
            menuButton?.focus();
            syncControls();
        }
    });

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
