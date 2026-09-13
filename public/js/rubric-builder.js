(function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    document.querySelectorAll('[data-rubric-builder]').forEach((builder) => {
        const criteria = builder.querySelector('[data-rubric-criteria]');
        const template = builder.querySelector('[data-rubric-template]');
        const status = builder.querySelector('[data-rubric-status]');
        const form = builder.closest('form');

        const contextValue = (key, selector) => builder.dataset[key] || form?.querySelector(selector)?.value || '';
        const maximumScore = () => Number(contextValue('maxScore', '[name="max_score"]')) || 0;

        function renumber() {
            criteria.querySelectorAll('[data-rubric-row]').forEach((row, index) => {
                row.querySelector('[data-rubric-number]').textContent = index + 1;
                row.querySelectorAll('[name], [data-name]').forEach((field) => {
                    const key = field.dataset.name || field.name.match(/\[([^\]]+)]$/)?.[1];
                    if (key) field.name = `rubric_criteria[${index}][${key}]`;
                });
            });
        }

        function updateTotals() {
            const percentage = Array.from(criteria.querySelectorAll('[data-rubric-percentage]')).reduce((sum, input) => sum + (Number(input.value) || 0), 0);
            const score = Array.from(criteria.querySelectorAll('[data-rubric-score]')).reduce((sum, input) => sum + (Number(input.value) || 0), 0);
            const percentageOutput = builder.querySelector('[data-rubric-percentage-total]');
            const scoreOutput = builder.querySelector('[data-rubric-score-total]');
            const maxOutput = builder.querySelector('[data-rubric-max-label]');
            percentageOutput.textContent = `${percentage.toFixed(2)}%`;
            scoreOutput.textContent = score.toFixed(2);
            maxOutput.textContent = maximumScore().toFixed(2);
            builder.querySelector('[data-rubric-totals]').classList.toggle('invalid', Math.abs(percentage - 100) > 0.01 || Math.abs(score - maximumScore()) > 0.01);
        }

        function addRow(values = {}) {
            const row = template.content.firstElementChild.cloneNode(true);
            ['title', 'description', 'percentage', 'score'].forEach((key) => {
                const field = row.querySelector(`[data-name="${key}"]`);
                field.value = values[key] ?? '';
            });
            criteria.appendChild(row);
            renumber();
            updateTotals();
        }

        builder.addEventListener('click', (event) => {
            if (event.target.closest('[data-rubric-add]')) addRow();
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
                result.criteria.forEach(addRow);
                status.textContent = 'AI suggestion added. Review every criterion before saving.';
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
