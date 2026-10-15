(function () {
    const input = document.querySelector('input[name="inactivity_timeout_minutes"]');
    if (!input) return;

    document.querySelectorAll('[data-timeout-value]').forEach((button) => {
        button.addEventListener('click', () => {
            input.value = button.dataset.timeoutValue;
            input.focus();
        });
    });
})();

(function () {
    const preview = document.querySelector('[data-system-color-preview]');
    if (!preview) return;

    [
        ['system_primary_color', '--system-preview-primary'],
        ['system_secondary_color', '--system-preview-secondary'],
        ['system_accent_color', '--system-preview-accent'],
    ].forEach(([name, property]) => {
        document.querySelector(`[name="${name}"]`)?.addEventListener('input', (event) => {
            preview.style.setProperty(property, event.target.value);
        });
    });
})();

(function () {
    const preview = document.querySelector('[data-id-design-preview]');
    if (!preview) return;

    const bindings = [
        ['student_id_primary_color', '--preview-primary'],
        ['student_id_secondary_color', '--preview-secondary'],
        ['student_id_accent_color', '--preview-accent'],
    ];
    bindings.forEach(([name, property]) => {
        document.querySelector(`[name="${name}"]`)?.addEventListener('input', (event) => {
            preview.style.setProperty(property, event.target.value);
        });
    });

    document.querySelector('[name="student_id_title"]')?.addEventListener('input', (event) => {
        preview.querySelector('[data-id-preview-title]').textContent = event.target.value || 'STUDENT ID';
    });
    document.querySelector('[name="student_id_footer_text"]')?.addEventListener('input', (event) => {
        preview.querySelector('[data-id-preview-footer]').textContent = event.target.value;
    });
})();
