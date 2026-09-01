(function () {
    const widget = document.querySelector('[data-ai-widget]');
    if (!widget || widget.dataset.ready === '1') return;
    widget.dataset.ready = '1';

    const launcher = widget.querySelector('[data-ai-widget-toggle]');
    const panel = widget.querySelector('[data-ai-widget-panel]');
    const close = widget.querySelector('[data-ai-widget-close]');
    const newChat = widget.querySelector('[data-ai-widget-new]');
    const expand = widget.querySelector('[data-ai-widget-expand]');
    const form = widget.querySelector('[data-ai-widget-form]');
    const textarea = form.querySelector('textarea');
    const submit = form.querySelector('button[type="submit"]');
    const messages = widget.querySelector('[data-ai-widget-messages]');
    const thinking = widget.querySelector('[data-ai-widget-thinking]');
    const errorBox = widget.querySelector('[data-ai-widget-error]');

    function setOpen(open) {
        panel.hidden = !open;
        launcher.setAttribute('aria-expanded', String(open));
        widget.classList.toggle('open', open);
        if (open) {
            messages.scrollTop = messages.scrollHeight;
            if (!textarea.disabled) textarea.focus();
        }
    }

    function appendMessage(role, content) {
        widget.querySelector('[data-ai-widget-welcome]')?.remove();
        const article = document.createElement('article');
        article.className = `ai-widget-message ${role}`;
        const author = document.createElement('strong');
        author.textContent = role === 'user' ? 'You' : 'SNAPIE AI';
        const copy = document.createElement('p');
        copy.textContent = content;
        article.append(author, copy);
        messages.appendChild(article);
        messages.scrollTop = messages.scrollHeight;
    }

    function showError(message) {
        errorBox.textContent = message;
        errorBox.hidden = false;
    }

    launcher.addEventListener('click', () => setOpen(panel.hidden));
    close.addEventListener('click', () => setOpen(false));

    newChat.addEventListener('click', () => {
        form.action = form.dataset.newAction;
        messages.innerHTML = '<div class="ai-widget-welcome" data-ai-widget-welcome><span>✦</span><strong>New conversation</strong><p>What would you like help with?</p></div>';
        expand.href = `${form.dataset.newAction}?new=1`;
        errorBox.hidden = true;
        textarea.value = '';
        if (!textarea.disabled) textarea.focus();
    });

    textarea.addEventListener('keydown', event => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            form.requestSubmit();
        }
    });

    form.addEventListener('submit', async event => {
        event.preventDefault();
        const message = textarea.value.trim();
        if (!message || submit.disabled) return;
        const payload = new FormData(form);

        errorBox.hidden = true;
        appendMessage('user', message);
        textarea.value = '';
        textarea.disabled = true;
        submit.disabled = true;
        thinking.hidden = false;

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: payload,
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok) {
                throw new Error(data.message || data.errors?.message?.[0] || 'The AI Assistant could not reply.');
            }

            appendMessage('assistant', data.assistant_message.content);
            form.action = data.conversation_url;
            expand.href = data.conversation_url;
        } catch (error) {
            showError(error.message || 'The AI Assistant is temporarily unavailable.');
        } finally {
            thinking.hidden = true;
            textarea.disabled = false;
            submit.disabled = false;
            textarea.focus();
        }
    });

    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && !panel.hidden) setOpen(false);
    });
})();
