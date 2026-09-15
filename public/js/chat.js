(() => {
    const messages = document.querySelector('[data-chat-messages]');

    if (messages) {
        messages.scrollTop = messages.scrollHeight;
    }

    document.querySelectorAll('[data-group-chat-form]').forEach((form) => {
        const section = form.querySelector('[data-group-section]');
        const students = Array.from(form.querySelectorAll('[data-group-student]'));
        const empty = form.querySelector('[data-group-student-empty]');

        if (!section) {
            return;
        }

        const refreshStudents = () => {
            let visibleCount = 0;
            students.forEach((student) => {
                const visible = section.value !== '' && student.dataset.sectionId === section.value;
                student.hidden = !visible;
                student.querySelector('input').disabled = !visible;
                if (!visible) {
                    student.querySelector('input').checked = false;
                } else {
                    visibleCount += 1;
                }
            });
            if (empty) {
                empty.hidden = visibleCount > 0;
                empty.textContent = section.value === ''
                    ? 'Select a section to see its students.'
                    : 'No active students are enrolled in this section.';
            }
        };

        section.addEventListener('change', refreshStudents);
        refreshStudents();
    });
})();
