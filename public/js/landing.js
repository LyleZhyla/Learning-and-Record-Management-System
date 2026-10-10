document.addEventListener('DOMContentLoaded', () => {
    const header = document.querySelector('[data-header]');
    const menuToggle = document.querySelector('[data-menu-toggle]');
    const menu = document.querySelector('[data-menu]');

    const closeMenu = () => {
        if (!menuToggle || !menu) return;
        menuToggle.setAttribute('aria-expanded', 'false');
        menu.classList.remove('is-open');
        document.body.classList.remove('menu-open');
    };

    menuToggle?.addEventListener('click', () => {
        const open = menuToggle.getAttribute('aria-expanded') !== 'true';
        menuToggle.setAttribute('aria-expanded', String(open));
        menu?.classList.toggle('is-open', open);
        document.body.classList.toggle('menu-open', open);
    });

    menu?.querySelectorAll('a').forEach((link) => link.addEventListener('click', closeMenu));
    window.addEventListener('resize', () => {
        if (window.innerWidth > 1180) closeMenu();
    });
    window.addEventListener('scroll', () => header?.classList.toggle('is-scrolled', window.scrollY > 10), { passive: true });

    const tabs = [...document.querySelectorAll('[data-component-tab]')];
    const panels = [...document.querySelectorAll('[data-component-panel]')];
    const activateTab = (tab) => {
        const target = tab.dataset.componentTab;
        tabs.forEach((item) => item.setAttribute('aria-selected', String(item === tab)));
        panels.forEach((panel) => {
            const active = panel.dataset.componentPanel === target;
            panel.hidden = !active;
            panel.classList.toggle('is-active', active);
        });
    };
    tabs.forEach((tab, index) => {
        tab.addEventListener('click', () => activateTab(tab));
        tab.addEventListener('keydown', (event) => {
            if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
            event.preventDefault();
            let next = index;
            if (event.key === 'ArrowRight') next = (index + 1) % tabs.length;
            if (event.key === 'ArrowLeft') next = (index - 1 + tabs.length) % tabs.length;
            if (event.key === 'Home') next = 0;
            if (event.key === 'End') next = tabs.length - 1;
            activateTab(tabs[next]);
            tabs[next].focus();
        });
    });

    const filters = [...document.querySelectorAll('[data-gallery-filter]')];
    const cards = [...document.querySelectorAll('[data-categories]')];
    const empty = document.querySelector('[data-gallery-empty]');
    filters.forEach((button) => button.addEventListener('click', () => {
        const filter = button.dataset.galleryFilter;
        let visible = 0;
        filters.forEach((item) => item.classList.toggle('is-active', item === button));
        cards.forEach((card) => {
            const show = filter === 'all' || card.dataset.categories.split(' ').includes(filter);
            card.hidden = !show;
            if (show) visible += 1;
        });
        if (empty) empty.hidden = visible > 0;
    }));

    const navLinks = [...document.querySelectorAll('.nav-menu a[href^="#"]')];
    const sections = navLinks.map((link) => document.querySelector(link.getAttribute('href'))).filter(Boolean);
    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                navLinks.forEach((link) => link.classList.toggle('is-active', link.getAttribute('href') === `#${entry.target.id}`));
            });
        }, { rootMargin: '-25% 0px -65% 0px' });
        sections.forEach((section) => observer.observe(section));
    }
});
