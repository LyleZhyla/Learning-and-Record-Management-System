document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-landing-editor-form]');
    if (!form) return;

    const state = form.querySelector('[data-editor-save-state]');
    const heroSlides = [...document.querySelectorAll('[data-hero-slide] img')];

    form.querySelectorAll('[data-landing-editor-field]').forEach((field) => {
        field.addEventListener('input', () => {
            const key = field.dataset.landingEditorField;
            document.querySelectorAll(`[data-landing-preview="${key}"]`).forEach((target) => {
                target.textContent = field.value;
            });
            document.querySelectorAll(`[data-landing-preview-image="${key}"]`).forEach((target) => {
                target.src = field.value;
            });

            const slideMatch = key.match(/^hero_slide_(\d)_url$/);
            if (slideMatch && heroSlides[Number(slideMatch[1]) - 1]) {
                heroSlides[Number(slideMatch[1]) - 1].src = field.value;
            }

            if (state) state.textContent = 'Unpublished changes';
        });
    });

    form.querySelectorAll('[data-landing-image-upload]').forEach((input) => {
        input.addEventListener('change', () => {
            const file = input.files?.[0];
            if (!file) return;

            const key = input.dataset.landingImageUpload;
            const previewUrl = URL.createObjectURL(file);
            const editorPreview = form.querySelector(`[data-landing-upload-preview="${key}"]`);
            if (editorPreview) editorPreview.src = previewUrl;
            document.querySelectorAll(`[data-landing-preview-image="${key}"]`).forEach((target) => {
                target.src = previewUrl;
            });

            const slideMatch = key.match(/^hero_slide_(\d)_url$/);
            if (slideMatch && heroSlides[Number(slideMatch[1]) - 1]) {
                heroSlides[Number(slideMatch[1]) - 1].src = previewUrl;
            }

            if (state) state.textContent = 'Unpublished image';
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
