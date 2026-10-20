(function () {
    const root = document.body;

    if (!root || !root.classList.contains('portal-super-admin')) {
        return;
    }

    const minimumFontSize = window.matchMedia('(max-width: 760px)').matches ? 13 : 12;
    const textSelector = '*';

    function containsReadableText(element) {
        if (element.matches('input, option, select, textarea')) {
            return true;
        }

        return [...element.childNodes].some((node) => (
            node.nodeType === Node.TEXT_NODE && node.textContent.trim() !== ''
        ));
    }

    function shouldSkip(element) {
        return element.matches('script, style, noscript, template, svg, [aria-hidden="true"], .nav-icon, .metric-icon')
            || Boolean(element.closest('svg, [aria-hidden="true"]'));
    }

    function improveElement(element) {
        if (shouldSkip(element) || !containsReadableText(element)) {
            return;
        }

        const renderedSize = Number.parseFloat(window.getComputedStyle(element).fontSize);

        if (Number.isFinite(renderedSize) && renderedSize < minimumFontSize) {
            element.classList.add('super-admin-readable-text');
        }
    }

    function improveArea(area) {
        if (area.nodeType === Node.TEXT_NODE) {
            if (area.parentElement) {
                improveElement(area.parentElement);
            }

            return;
        }

        if (area.nodeType !== Node.ELEMENT_NODE) {
            return;
        }

        if (area.matches(textSelector)) {
            improveElement(area);
        }

        area.querySelectorAll(textSelector).forEach(improveElement);
    }

    improveArea(root);

    new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            mutation.addedNodes.forEach(improveArea);

            if (mutation.type === 'attributes') {
                improveArea(mutation.target);
            }
        });
    }).observe(root, {
        attributeFilter: ['class', 'style'],
        attributes: true,
        childList: true,
        subtree: true,
    });
})();
