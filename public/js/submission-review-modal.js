(function () {
    const dialogs = document.querySelectorAll('[data-submission-dialog]');
    let activeTrigger = null;

    const openDialog = (dialog, trigger = null) => {
        if (!dialog || dialog.open) return;
        activeTrigger = trigger;
        dialog.showModal();
        document.documentElement.classList.add('submission-dialog-open');
    };

    const closeDialog = (dialog) => {
        if (!dialog?.open) return;
        dialog.close();
    };

    document.addEventListener('click', (event) => {
        const opener = event.target.closest('[data-submission-open]');
        if (opener) {
            const dialog = document.getElementById(opener.dataset.submissionOpen);
            openDialog(dialog, opener);
        }

        const closer = event.target.closest('[data-submission-close]');
        if (closer) closeDialog(closer.closest('dialog'));
    });

    dialogs.forEach((dialog) => {
        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) closeDialog(dialog);
        });
        dialog.addEventListener('close', () => {
            if (![...dialogs].some((item) => item.open)) {
                document.documentElement.classList.remove('submission-dialog-open');
            }
            activeTrigger?.focus();
            activeTrigger = null;
        });
        if (dialog.hasAttribute('data-auto-open')) openDialog(dialog);
    });
})();
