document.addEventListener('DOMContentLoaded', () => {
    const builder = document.querySelector('[data-document-builder]');
    if (!builder) return;
    const response = builder.querySelector('[data-requires-submission]');
    const uploadSettings = builder.querySelectorAll('[data-upload-setting], [data-required-setting]');
    const refresh = () => uploadSettings.forEach((field) => {
        const enabled = response.value === '1';
        field.hidden = !enabled;
    });
    response.addEventListener('change', refresh);
    refresh();
});
