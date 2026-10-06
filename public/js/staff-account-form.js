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
        const nameField = form.querySelector('[data-account-name-field]');
        const nameInput = form.querySelector('[data-account-name-input]');
        const statusField = form.querySelector('[data-account-status-field]');
        const statusInput = form.querySelector('[data-account-status-input]');
        const componentField = form.querySelector('[data-staff-component-field]');
        const component = form.querySelector('[data-staff-component-select]');
        const help = form.querySelector('[data-staff-component-help]');
        const facilitatorFields = form.querySelector('[data-facilitator-record-fields]');
        const facilitatorInputs = facilitatorFields?.querySelectorAll('[data-facilitator-record-input]') ?? [];

        if (!role || !componentField || !component || !help) return;

        const updateComponentField = () => {
            const isCoordinator = role.value === 'coordinator';
            const isFacilitator = role.value === 'facilitator';
            const usesComponent = isCoordinator || isFacilitator;

            form.classList.toggle('is-facilitator', isFacilitator);
            if (nameField && nameInput) {
                nameField.hidden = isFacilitator;
                nameInput.disabled = isFacilitator;
                nameInput.required = !isFacilitator;
            }
            if (statusField && statusInput) {
                statusField.hidden = isFacilitator;
                statusInput.disabled = isFacilitator;
                statusInput.required = !isFacilitator;
            }

            componentField.hidden = !usesComponent;
            component.disabled = !usesComponent;
            component.required = usesComponent;
            help.textContent = isCoordinator
                ? 'Required. The Coordinator can access records only for the selected component.'
                : 'Required. The Facilitator will be assigned to this NSTP component.';

            if (facilitatorFields) {
                facilitatorFields.hidden = !isFacilitator;
                facilitatorInputs.forEach((input) => {
                    input.disabled = !isFacilitator;
                    input.required = isFacilitator && input.hasAttribute('data-facilitator-required');
                });
            }

            if (!usesComponent) component.value = '';
        };

        role.addEventListener('change', updateComponentField);
        updateComponentField();
    });
});
