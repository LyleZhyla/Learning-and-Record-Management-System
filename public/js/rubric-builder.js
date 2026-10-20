(function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    document.querySelectorAll('[data-rubric-builder]').forEach((builder) => {
        const criteria = builder.querySelector('[data-rubric-criteria]');
        const template = builder.querySelector('[data-rubric-template]');
        const status = builder.querySelector('[data-rubric-status]');
        const form = builder.closest('form');
        const maxCriteria = Number(builder.dataset.maxCriteria) || 10;
        const rubricRequired = builder.dataset.rubricRequired === 'true';
        const libraryData = builder.querySelector('[data-rubric-library-data]');
        const librarySelect = builder.querySelector('[data-rubric-library-select]');
        const libraryApply = builder.querySelector('[data-rubric-library-apply]');
        let rubricTemplates = [];

        try {
            rubricTemplates = libraryData ? JSON.parse(libraryData.textContent) : [];
        } catch (error) {
            rubricTemplates = [];
        }

        const contextValue = (key, selector) => builder.dataset[key] || form?.querySelector(selector)?.value || '';
        const maximumScore = () => Number(contextValue('maxScore', '[name="max_score"]')) || 0;

        function renumber() {
            const rows = criteria.querySelectorAll('[data-rubric-row]');

            rows.forEach((row, index) => {
                row.querySelector('[data-rubric-number]').textContent = index + 1;
                row.querySelector('[data-rubric-remove]')?.setAttribute('aria-label', `Remove criterion ${index + 1}`);
                row.querySelectorAll('[name], [data-name]').forEach((field) => {
                    const key = field.dataset.name || field.name.match(/\[([^\]]+)]$/)?.[1];
                    if (key) {
                        field.name = `rubric_criteria[${index}][${key}]`;
                        field.required = rubricRequired;
                    }
                });
            });

            const countOutput = builder.querySelector('[data-rubric-count]');
            const addButton = builder.querySelector('[data-rubric-add]');
            if (countOutput) countOutput.textContent = rows.length;
            if (addButton) {
                addButton.disabled = rows.length >= maxCriteria;
                addButton.title = rows.length >= maxCriteria ? `Maximum of ${maxCriteria} criteria reached.` : '';
            }
        }

        function updateTotals() {
            const percentage = Array.from(criteria.querySelectorAll('[data-rubric-percentage]')).reduce((sum, input) => sum + (Number(input.value) || 0), 0);
            const score = Array.from(criteria.querySelectorAll('[data-rubric-score]')).reduce((sum, input) => sum + (Number(input.value) || 0), 0);
            const percentageOutput = builder.querySelector('[data-rubric-percentage-total]');
            const scoreOutput = builder.querySelector('[data-rubric-score-total]');
            const maxOutput = builder.querySelector('[data-rubric-max-label]');
            const progress = builder.querySelector('[data-rubric-progress]');
            const state = builder.querySelector('[data-rubric-state]');
            const totals = builder.querySelector('[data-rubric-totals]');
            const percentageComplete = Math.abs(percentage - 100) <= 0.01;
            const scoreComplete = Math.abs(score - maximumScore()) <= 0.01;
            const complete = percentageComplete && scoreComplete;
            percentageOutput.textContent = `${percentage.toFixed(2)}%`;
            scoreOutput.textContent = score.toFixed(2);
            maxOutput.textContent = maximumScore().toFixed(2);
            totals.classList.toggle('invalid', !complete);
            totals.classList.toggle('complete', complete);
            if (state) state.textContent = complete ? 'Ready to save' : 'Adjust totals before saving';
            if (progress) {
                progress.style.setProperty('--rubric-progress', `${Math.min(Math.max(percentage, 0), 100)}%`);
                progress.setAttribute('aria-valuenow', String(Math.min(Math.max(percentage, 0), 100)));
            }
        }

        function addRow(values = {}, focusTitle = false) {
            if (criteria.children.length >= maxCriteria) return;
            const row = template.content.firstElementChild.cloneNode(true);
            ['title', 'description', 'percentage', 'score'].forEach((key) => {
                const field = row.querySelector(`[data-name="${key}"]`);
                field.value = values[key] ?? '';
            });
            criteria.appendChild(row);
            renumber();
            updateTotals();
            if (focusTitle) {
                row.querySelector('[data-name="title"]')?.focus();
                row.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        }

        function upsertLibraryTemplate(template) {
            if (!template || !librarySelect) return;
            const existingIndex = rubricTemplates.findIndex((item) => Number(item.id) === Number(template.id));
            if (existingIndex >= 0) rubricTemplates[existingIndex] = template;
            else rubricTemplates.unshift(template);

            let option = librarySelect.querySelector(`option[value="${template.id}"]`);
            if (!option) {
                option = document.createElement('option');
                option.value = template.id;
                librarySelect.appendChild(option);
            }
            const typeLabel = String(template.assessment_type || 'rubric').replace(/(^|_)([a-z])/g, (_, separator, letter) => `${separator ? ' ' : ''}${letter.toUpperCase()}`);
            option.textContent = `${typeLabel} · ${template.name}`;
            librarySelect.disabled = false;
            libraryApply.disabled = false;
            librarySelect.options[0].textContent = 'Choose a saved rubric';
            librarySelect.value = String(template.id);
        }

        function applyLibraryTemplate(template) {
            const maxScore = maximumScore();
            if (!maxScore) {
                status.textContent = 'Enter the assessment maximum score before using a saved rubric.';
                status.className = 'rubric-suggestion-status error';
                return;
            }

            criteria.innerHTML = '';
            let allocatedScore = 0;
            template.criteria.slice(0, maxCriteria).forEach((criterion, index, items) => {
                const percentage = Number(criterion.percentage) || 0;
                const score = index === items.length - 1
                    ? Math.max(0, maxScore - allocatedScore)
                    : Math.round((percentage * maxScore / 100) * 100) / 100;
                allocatedScore += score;
                addRow({...criterion, percentage, score: score.toFixed(2)});
            });
            renumber();
            updateTotals();
            status.textContent = `“${template.name}” applied. Review the criteria before saving.`;
            status.className = 'rubric-suggestion-status success';
            criteria.querySelector('input')?.focus();
        }

        builder.addEventListener('click', (event) => {
            if (event.target.closest('[data-rubric-add]')) addRow({}, true);
            const remove = event.target.closest('[data-rubric-remove]');
            if (remove) {
                remove.closest('[data-rubric-row]').remove();
                if (!criteria.children.length) addRow();
                renumber();
                updateTotals();
            }
        });

        builder.addEventListener('input', (event) => {
            if (event.target.matches('[data-rubric-percentage]')) {
                const score = event.target.closest('[data-rubric-row]').querySelector('[data-rubric-score]');
                score.value = maximumScore() ? ((Number(event.target.value) || 0) * maximumScore() / 100).toFixed(2) : '';
            } else if (event.target.matches('[data-rubric-score]')) {
                const percentage = event.target.closest('[data-rubric-row]').querySelector('[data-rubric-percentage]');
                percentage.value = maximumScore() ? ((Number(event.target.value) || 0) / maximumScore() * 100).toFixed(2) : '';
            }
            updateTotals();
        });

        libraryApply?.addEventListener('click', () => {
            const template = rubricTemplates.find((item) => Number(item.id) === Number(librarySelect.value));
            if (template) applyLibraryTemplate(template);
        });
        librarySelect?.addEventListener('change', () => {
            libraryApply.disabled = !librarySelect.value;
        });

        builder.querySelector('[data-rubric-suggest]')?.addEventListener('click', async (event) => {
            const button = event.currentTarget;
            const payload = {
                title: contextValue('title', '[name="title"]'),
                type: contextValue('type', '[name="type"]'),
                instructions: contextValue('instructions', '[name="instructions"]'),
                max_score: maximumScore(),
            };
            if (!payload.title || !payload.max_score) {
                status.textContent = 'Enter the assessment title and maximum score first.';
                status.className = 'rubric-suggestion-status error';
                return;
            }
            button.disabled = true;
            status.textContent = 'AI is drafting rubric criteria…';
            status.className = 'rubric-suggestion-status loading';
            try {
                const response = await fetch(builder.dataset.suggestionUrl, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken},
                    body: JSON.stringify(payload),
                });
                const result = await response.json();
                if (!response.ok) throw new Error(result.message || Object.values(result.errors || {})[0]?.[0] || 'Unable to generate a suggestion.');
                criteria.innerHTML = '';
                result.criteria.forEach((criterion) => addRow(criterion));
                upsertLibraryTemplate(result.template);
                status.textContent = 'AI suggestion added and saved to your rubric library. Review every criterion before saving.';
                status.className = 'rubric-suggestion-status success';
            } catch (error) {
                status.textContent = error.message;
                status.className = 'rubric-suggestion-status error';
            } finally {
                button.disabled = false;
            }
        });

        form?.querySelector('[name="max_score"]')?.addEventListener('input', updateTotals);
        renumber();
        updateTotals();
    });
})();
