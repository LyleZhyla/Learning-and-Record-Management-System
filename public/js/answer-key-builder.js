(function () {
    const form = document.querySelector('[data-answer-key-builder]');
    if (!form) return;

    const countInput = form.querySelector('[data-item-count]');
    const choiceInput = form.querySelector('[data-choice-count]');
    const grid = form.querySelector('[data-answer-key-grid]');
    const saved = JSON.parse(form.querySelector('[data-old-answer-key]')?.textContent || '[]');
    const assessmentType = form.querySelector('[data-assessment-type]');
    const optionPanel = form.querySelector('[data-answer-sheet-option]');
    const setupPanel = form.querySelector('[data-answer-sheet-setup]');
    const answerSheetToggles = Array.from(form.querySelectorAll('input[name="create_answer_sheet"]'));

    function answerSheetIsActive() {
        const eligible = !assessmentType || ['quiz', 'exam'].includes(assessmentType.value);
        const selected = answerSheetToggles.find((input) => input.checked);
        return eligible && (!selected || selected.value === '1');
    }

    function syncAnswerSheetVisibility() {
        const eligible = !assessmentType || ['quiz', 'exam'].includes(assessmentType.value);
        const active = answerSheetIsActive();

        if (optionPanel) optionPanel.hidden = !eligible;
        if (setupPanel) setupPanel.hidden = !active;
        if (!eligible) {
            const noOption = answerSheetToggles.find((input) => input.value === '0');
            if (noOption) noOption.checked = true;
        }

        countInput.disabled = !active;
        choiceInput.disabled = !active;
        grid.querySelectorAll('input[type="radio"]').forEach((radio) => {
            radio.disabled = !active;
            radio.required = active;
        });
    }

    function render() {
        const previous = Array.from(grid.querySelectorAll('[data-answer-item]')).map((item) => item.querySelector('input:checked')?.value || '');
        const count = Math.min(30, Math.max(1, Number(countInput.value) || 1));
        const choices = Math.min(5, Math.max(2, Number(choiceInput.value) || 4));
        grid.innerHTML = '';

        for (let index = 0; index < count; index += 1) {
            const item = document.createElement('div');
            const number = document.createElement('span');
            const choiceGroup = document.createElement('div');
            const selected = previous[index] || saved[index] || '';

            item.className = 'answer-key-item';
            item.dataset.answerItem = String(index);
            number.className = 'answer-key-number';
            number.textContent = String(index + 1);
            choiceGroup.className = 'answer-choice-group';
            choiceGroup.setAttribute('role', 'radiogroup');
            choiceGroup.setAttribute('aria-label', `Correct answer for question ${index + 1}`);

            for (let choice = 0; choice < choices; choice += 1) {
                const letter = String.fromCharCode(65 + choice);
                const label = document.createElement('label');
                const radio = document.createElement('input');
                const text = document.createElement('span');

                label.className = 'answer-choice';
                radio.type = 'radio';
                radio.name = `answers[${index}]`;
                radio.value = letter;
                radio.required = true;
                radio.checked = selected === letter;
                text.textContent = letter;
                label.append(radio, text);
                choiceGroup.appendChild(label);
            }

            item.append(number, choiceGroup);
            grid.appendChild(item);
        }

        syncAnswerSheetVisibility();
    }

    countInput.addEventListener('input', render);
    choiceInput.addEventListener('change', render);
    assessmentType?.addEventListener('change', syncAnswerSheetVisibility);
    answerSheetToggles.forEach((input) => input.addEventListener('change', syncAnswerSheetVisibility));
    render();
})();
