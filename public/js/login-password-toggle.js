document.addEventListener('DOMContentLoaded', () => {
    const password = document.querySelector('[data-login-password]');
    const toggle = document.querySelector('[data-password-visibility-toggle]');
    const openEye = toggle?.querySelector('[data-eye-open]');
    const closedEye = toggle?.querySelector('[data-eye-closed]');

    if (!password || !toggle || !openEye || !closedEye) return;

    toggle.addEventListener('click', () => {
        const willShow = password.type === 'password';
        password.type = willShow ? 'text' : 'password';
        toggle.setAttribute('aria-pressed', String(willShow));
        toggle.setAttribute('aria-label', willShow ? 'Hide password' : 'Show password');
        toggle.title = willShow ? 'Hide password' : 'Show password';
        openEye.hidden = willShow;
        closedEye.hidden = !willShow;
        password.focus({ preventScroll: true });
    });
});
