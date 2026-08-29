(function () {
    const input = document.querySelector('[data-assessment-file]');
    const fileName = document.querySelector('[data-assessment-file-name]');

    if (!input || !fileName) {
        return;
    }

    input.addEventListener('change', () => {
        const selectedFile = input.files?.[0];
        fileName.textContent = selectedFile
            ? selectedFile.name
            : 'PDF, Office document, image, or text · Max 10 MB';
        fileName.classList.toggle('has-file', Boolean(selectedFile));
    });
})();
