document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-landing-editor-form]');
    if (!form) return;

    const state = form.querySelector('[data-editor-save-state]');
    const video = document.querySelector('[data-hero-video]');
    const videoSource = video?.querySelector('source');
    let videoRefreshTimer;

    form.querySelectorAll('[data-landing-editor-field]').forEach((field) => {
        field.addEventListener('input', () => {
            const key = field.dataset.landingEditorField;
            document.querySelectorAll(`[data-landing-preview="${key}"]`).forEach((target) => {
                target.textContent = field.value;
            });

            if (key === 'hero_poster_url' && video) video.poster = field.value;
            if (key === 'hero_video_url' && videoSource) {
                window.clearTimeout(videoRefreshTimer);
                videoRefreshTimer = window.setTimeout(() => {
                    videoSource.src = field.value;
                    video.load();
                    video.play().catch(() => {});
                }, 600);
            }

            if (state) state.textContent = 'Unpublished changes';
        });
    });

    form.addEventListener('submit', () => {
        const button = form.querySelector('button[type="submit"]');
        if (button) {
            button.disabled = true;
            button.textContent = 'Publishing…';
        }
    });
});
