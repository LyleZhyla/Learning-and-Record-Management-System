(function () {
    const form = document.querySelector('[data-assessment-builder]');
    if (!form) return;

    const section = form.querySelector('[data-assessment-section]');
    const category = form.querySelector('[data-assessment-category]');
    const type = form.querySelector('[data-assessment-type]');
    const maxScore = form.querySelector('[data-assessment-max-score]');
    const note = form.querySelector('[data-assessment-profile-note]');
    const profilesNode = document.querySelector('[data-component-assessment-profiles]');
    const oldValuesNode = document.querySelector('[data-assessment-old-values]');
    if (!section || !category || !type) return;

    let profiles = {};
    let oldValues = {};
    try { profiles = JSON.parse(profilesNode?.textContent || '{}'); } catch (_) { profiles = {}; }
    try { oldValues = JSON.parse(oldValuesNode?.textContent || '{}'); } catch (_) { oldValues = {}; }

    const typeLabels = { activity: 'Activity', project: 'Project', quiz: 'Quiz', exam: 'Exam' };

    function filterCategories() {
        const sectionId = section.value;
        const selected = category.selectedOptions[0];
        const selectedType = type.value;

        Array.from(category.options).forEach((option) => {
            if (!option.dataset.section) return;
            const isAvailable = option.dataset.section === sectionId
                && (!option.dataset.type || option.dataset.type === selectedType);
            option.hidden = !isAvailable;
            option.disabled = !isAvailable;
        });

        if (selected?.disabled || selected?.dataset.section !== sectionId) {
            category.value = Array.from(category.options).find((option) => !option.disabled && option.dataset.section)?.value || '';
        }
        category.disabled = !sectionId;
    }

    function showProfileNote(profile) {
        if (!note || !profile) {
            if (note) note.hidden = true;
            return;
        }

        const allowed = (profile.allowed_types || []).map((value) => typeLabels[value] || value).join(', ');
        const rubricRequired = (profile.rubric_required_types || []).includes(type.value);
        note.textContent = `${profile.component} profile · Allowed: ${allowed}. ${rubricRequired ? `${typeLabels[type.value] || type.value} requires a scoring rubric.` : 'A scoring rubric is optional for this type.'}`;
        note.classList.toggle('requires-rubric', rubricRequired);
        note.hidden = false;
    }

    function applySectionProfile(initial = false) {
        const profile = profiles[section.value];
        const allowedTypes = profile?.allowed_types || Object.keys(typeLabels);

        Array.from(type.options).forEach((option) => {
            const allowed = allowedTypes.includes(option.value);
            option.hidden = !allowed;
            option.disabled = !allowed;
        });

        if (!allowedTypes.includes(type.value)) {
            type.value = profile?.default_type || allowedTypes[0] || 'activity';
            type.dispatchEvent(new Event('change', { bubbles: true }));
        }

        if (maxScore && profile && (!initial || oldValues.max_score === null || oldValues.max_score === '')) {
            maxScore.value = profile.default_max_score;
            maxScore.dispatchEvent(new Event('input', { bubbles: true }));
        }

        filterCategories();
        showProfileNote(profile);
    }

    section.addEventListener('change', () => applySectionProfile(false));
    type.addEventListener('change', () => {
        filterCategories();
        showProfileNote(profiles[section.value]);
    });
    applySectionProfile(true);
})();
