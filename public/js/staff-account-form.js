document.addEventListener('DOMContentLoaded', () => {
    const copyButton = document.querySelector('[data-copy-temporary-password]');
    const password = document.querySelector('[data-temporary-password]');

    copyButton?.addEventListener('click', async () => {
        if (!password) return;

        try {
            await navigator.clipboard.writeText(password.textContent.trim());
            copyButton.textContent = 'Copied';
        } catch {
            const selection = window.getSelection();
            const range = document.createRange();
            range.selectNodeContents(password);
            selection.removeAllRanges();
            selection.addRange(range);
            copyButton.textContent = 'Select and copy';
        }
    });

    document.querySelectorAll('[data-staff-account-form]').forEach((form) => {
        const role = form.querySelector('[data-account-role]');
        const componentField = form.querySelector('[data-staff-component-field]');
        const component = form.querySelector('[data-staff-component-select]');
        const help = form.querySelector('[data-staff-component-help]');

        if (!role || !componentField || !component || !help) return;

        const updateComponentField = () => {
            const isCoordinator = role.value === 'coordinator';
            const isFacilitator = role.value === 'facilitator';
            const usesComponent = isCoordinator || isFacilitator;

            componentField.hidden = !usesComponent;
            component.disabled = !usesComponent;
            component.required = isCoordinator;
            help.textContent = isCoordinator
                ? 'Required. The Coordinator can access records only for the selected component.'
                : 'Optional. Select a component now or assign the Facilitator to sections later.';

            if (!usesComponent) component.value = '';
        };

        role.addEventListener('change', updateComponentField);
        updateComponentField();
    });
});
