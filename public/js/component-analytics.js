document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-component-analytics-filter]');
    const componentSelect = form?.querySelector('[data-component-select]');
    const msLevelField = form?.querySelector('[data-ms-level-field]');
    const msLevelSelect = form?.querySelector('[data-ms-level-select]');

    if (!componentSelect || !msLevelField || !msLevelSelect) return;

    const updateMsLevel = () => {
        const isRotc = componentSelect.selectedOptions[0]?.dataset.componentCode === 'ROTC';
        msLevelField.hidden = !isRotc;
        msLevelSelect.disabled = !isRotc;
        if (!isRotc) msLevelSelect.value = '';
    };

    componentSelect.addEventListener('change', updateMsLevel);
    updateMsLevel();
});
