(function () {
    const input = document.querySelector('[data-profile-photo-input]');
    const preview = document.querySelector('[data-profile-photo-preview]');
    const fileName = document.querySelector('[data-profile-photo-name]');

    if (!input || !preview || !fileName || input.dataset.ready) return;
    input.dataset.ready = 'true';

    input.addEventListener('change', () => {
        const file = input.files?.[0];
        if (!file) return;

        const image = document.createElement('img');
        image.alt = 'Selected profile photo preview';
        image.src = URL.createObjectURL(file);
        image.addEventListener('load', () => URL.revokeObjectURL(image.src), { once: true });
        preview.replaceChildren(image);
        fileName.textContent = file.name;
        fileName.classList.add('has-file');
    });
})();
