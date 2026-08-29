(function () {
    const form = document.querySelector('[data-answer-key-builder]');
    if (!form) return;

    const countInput = form.querySelector('[data-item-count]');
    const choiceInput = form.querySelector('[data-choice-count]');
    const grid = form.querySelector('[data-answer-key-grid]');
    const saved = JSON.parse(form.querySelector('[data-old-answer-key]')?.textContent || '[]');

    function render() {
        const previous = Array.from(grid.querySelectorAll('select')).map((select) => select.value);
        const count = Math.min(30, Math.max(1, Number(countInput.value) || 1));
        const choices = Math.min(5, Math.max(2, Number(choiceInput.value) || 4));
        grid.innerHTML = '';

        for (let index = 0; index < count; index += 1) {
            const label = document.createElement('label');
            const select = document.createElement('select');
            label.innerHTML = `<span>${index + 1}</span>`;
            select.name = `answers[${index}]`;
            select.required = true;
            select.innerHTML = '<option value="">—</option>';
            for (let choice = 0; choice < choices; choice += 1) {
                const letter = String.fromCharCode(65 + choice);
                select.insertAdjacentHTML('beforeend', `<option value="${letter}">${letter}</option>`);
            }
            select.value = previous[index] || saved[index] || '';
            label.appendChild(select);
            grid.appendChild(label);
        }
    }

    countInput.addEventListener('input', render);
    choiceInput.addEventListener('change', render);
    render();
})();
