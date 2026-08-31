(function () {
    const dialog = document.querySelector('[data-student-qr-dialog]');

    if (!dialog) {
        return;
    }

    const image = dialog.querySelector('[data-student-qr-image]');
    const loading = dialog.querySelector('[data-student-qr-loading]');
    const studentName = dialog.querySelector('[data-student-qr-name]');

    document.querySelectorAll('[data-student-qr-preview]').forEach((button) => {
        button.addEventListener('click', () => {
            const name = button.dataset.studentName || 'Student';
            studentName.textContent = name;
            loading.hidden = false;
            image.hidden = true;
            image.alt = `Attendance QR for ${name}`;
            image.onload = () => {
                loading.hidden = true;
                image.hidden = false;
            };
            image.src = button.dataset.qrUrl;
            dialog.showModal();
        });
    });

    dialog.querySelector('[data-student-qr-close]')?.addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) {
            dialog.close();
        }
    });
})();
