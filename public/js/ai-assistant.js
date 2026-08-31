(() => {
    const form = document.querySelector('[data-ai-form]');
    const list = document.querySelector('[data-ai-message-list]');
    const input = form?.querySelector('textarea');
    const submit = form?.querySelector('button[type="submit"]');
    const typing = document.querySelector('[data-ai-typing]');

    const scrollToLatest = () => {
        if (list) list.scrollTop = list.scrollHeight;
    };

    const appendMessage = ({ role, content, time }) => {
        document.querySelector('[data-ai-welcome]')?.remove();
        const article = document.createElement('article');
        article.className = `ai-message ${role}`;
        const icon = document.createElement('span');
        icon.className = 'ai-message-icon';
        icon.setAttribute('aria-hidden', 'true');
        icon.textContent = role === 'user' ? 'Y' : '✦';
        const copy = document.createElement('div');
        const heading = document.createElement('strong');
        heading.textContent = role === 'user' ? 'You' : 'SNAPIE AI';
        const body = document.createElement('p');
        body.textContent = content;
        const timestamp = document.createElement('small');
        timestamp.textContent = time;
        copy.append(heading, body, timestamp);
        article.append(icon, copy);
        list.append(article);
    };

    document.querySelectorAll('[data-ai-suggestion]').forEach((button) => {
        button.addEventListener('click', () => {
            if (!input || input.disabled) return;
            input.value = button.dataset.aiSuggestion;
            input.focus();
        });
    });

    document.querySelector('[data-ai-clear-form]')?.addEventListener('submit', (event) => {
        if (!window.confirm('Clear your entire AI conversation?')) event.preventDefault();
    });

    form?.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!input.value.trim() || submit.disabled) return;

        submit.disabled = true;
        input.disabled = true;
        typing.hidden = false;

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: { Accept: 'application/json' },
                body: new FormData(form),
            });
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || Object.values(data.errors || {}).flat()[0] || 'Unable to reach the AI Assistant.');
            appendMessage(data.user_message);
            appendMessage(data.assistant_message);
            input.value = '';
        } catch (error) {
            window.alert(error.message);
        } finally {
            typing.hidden = true;
            submit.disabled = false;
            input.disabled = false;
            input.focus();
            scrollToLatest();
        }
    });

    scrollToLatest();
})();
