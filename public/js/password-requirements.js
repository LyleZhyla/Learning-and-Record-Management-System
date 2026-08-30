(function () {
    const rules = {
        length: (value) => value.length >= 12,
        uppercase: (value) => /[A-Z]/.test(value),
        lowercase: (value) => /[a-z]/.test(value),
        number: (value) => /[0-9]/.test(value),
        symbol: (value) => /[^A-Za-z0-9]/.test(value),
    };

    document.querySelectorAll('form[data-password-rules]').forEach((form) => {
        const password = form.querySelector('input[name="password"]');
        const confirmation = form.querySelector('input[name="password_confirmation"]');
        const submit = form.querySelector('button[type="submit"]');
        const checklist = form.querySelector('.password-requirements');

        if (!password || !confirmation || !submit || !checklist) return;

        const setState = (name, passed) => {
            const item = checklist.querySelector(`[data-password-check="${name}"]`);
            if (!item) return;
            item.classList.toggle('passed', passed);
            item.querySelector('span').textContent = passed ? '✓' : '○';
        };

        const validate = () => {
            const value = password.value;
            let valid = true;

            Object.entries(rules).forEach(([name, check]) => {
                const passed = check(value);
                setState(name, passed);
                valid = valid && passed;
            });

            const matches = confirmation.value.length > 0 && value === confirmation.value;
            setState('match', matches);
            valid = valid && matches;

            const requiredFieldsComplete = Array.from(form.querySelectorAll('[required]'))
                .every((field) => field.value.trim().length > 0);

            submit.disabled = !(valid && requiredFieldsComplete);
            form.dataset.passwordValid = valid ? 'true' : 'false';

            return valid && requiredFieldsComplete;
        };

        form.addEventListener('input', validate);
        form.addEventListener('submit', (event) => {
            if (!validate()) {
                event.preventDefault();
                password.focus();
            }
        });

        validate();
    });
})();
