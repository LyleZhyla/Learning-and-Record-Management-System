(function () {
    const input = document.querySelector('input[name="inactivity_timeout_minutes"]');
    if (!input) return;

    document.querySelectorAll('[data-timeout-value]').forEach((button) => {
        button.addEventListener('click', () => {
            input.value = button.dataset.timeoutValue;
            input.focus();
        });
    });
})();
