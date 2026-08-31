(() => {
    const messages = document.querySelector('[data-chat-messages]');

    if (messages) {
        messages.scrollTop = messages.scrollHeight;
    }
})();
