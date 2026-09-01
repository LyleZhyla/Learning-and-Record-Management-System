(function () {
    const form = document.querySelector('[data-assessment-builder]');
    if (!form) return;

    const section = form.querySelector('[data-assessment-section]');
    const category = form.querySelector('[data-assessment-category]');
    if (!section || !category) return;

    function filterCategories() {
        const sectionId = section.value;
        const selected = category.selectedOptions[0];

        Array.from(category.options).forEach((option) => {
            if (!option.dataset.section) return;
            const belongsToSection = option.dataset.section === sectionId;
            option.hidden = !belongsToSection;
            option.disabled = !belongsToSection;
        });

        if (selected?.dataset.section !== sectionId) category.value = '';
        category.disabled = !sectionId;
    }

    section.addEventListener('change', filterCategories);
    filterCategories();
})();
