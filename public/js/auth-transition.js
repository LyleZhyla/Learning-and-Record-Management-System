(function () {
    const shell = document.querySelector('.auth-shell');
    if (!shell) return;

    document.querySelectorAll('[data-auth-direction]').forEach((link) => {
        link.addEventListener('click', (event) => {
            if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

            event.preventDefault();
            shell.classList.add(`auth-leave-to-${link.dataset.authDirection}`);
            window.setTimeout(() => {
                window.location.href = link.href;
            }, 430);
        });
    });
})();
