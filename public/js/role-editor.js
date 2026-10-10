document.addEventListener('DOMContentLoaded', () => {
    const editor = document.querySelector('[data-role-editor]');
    if (!editor) return;

    const base = editor.querySelector('[data-role-base]');
    const options = [...editor.querySelectorAll('[data-permission-bases]')];
    const toggle = editor.querySelector('[data-toggle-permissions]');

    const refresh = () => {
        const selectedBase = base?.value;
        options.forEach((option) => {
            const allowed = JSON.parse(option.dataset.permissionBases || '[]').includes(selectedBase);
            option.hidden = !allowed;
            option.querySelector('input').disabled = !allowed;
        });
    };

    base?.addEventListener('change', refresh);
    toggle?.addEventListener('click', () => {
        const visible = options.filter((option) => !option.hidden);
        const shouldCheck = visible.some((option) => !option.querySelector('input').checked);
        visible.forEach((option) => { option.querySelector('input').checked = shouldCheck; });
        toggle.textContent = shouldCheck ? 'Clear visible' : 'Select visible';
    });
    refresh();
});
