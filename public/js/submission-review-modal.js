(function () {
    const dialogs = document.querySelectorAll('[data-submission-dialog]');

    document.addEventListener('click', (event) => {
        const opener = event.target.closest('[data-submission-open]');
        if (opener) {
            const dialog = document.getElementById(opener.dataset.submissionOpen);
            if (dialog && !dialog.open) dialog.showModal();
        }

        const closer = event.target.closest('[data-submission-close]');
        if (closer) closer.closest('dialog')?.close();
    });

    dialogs.forEach((dialog) => {
        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) dialog.close();
        });
        if (dialog.hasAttribute('data-auto-open')) dialog.showModal();
    });
})();
