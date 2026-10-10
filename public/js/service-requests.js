document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-service-request-form]');
    if (!form) return;

    const requestTypes = [...form.querySelectorAll('input[name="request_type"]')];
    const assistanceFields = form.querySelector('[data-assistance-fields]');
    const eventFields = form.querySelector('[data-event-fields]');
    const assistanceTypes = [...form.querySelectorAll('input[name="assistance_type"]')];
    const assistanceRequired = [...form.querySelectorAll('[data-assistance-required]')];
    const serialRequired = [...form.querySelectorAll('[data-serial-required]')];
    const completionYearHint = form.querySelector('[data-completion-year-hint]');
    const finalStep = form.querySelector('[data-final-step]');
    const purpose = form.querySelector('textarea[name="purpose"]');
    const purposeCount = form.querySelector('[data-purpose-count]');
    const attachment = form.querySelector('[data-attachment]');
    const attachmentLabel = form.querySelector('[data-attachment-label]');

    const syncType = () => {
        const selected = requestTypes.find((input) => input.checked)?.value;
        const isAssistance = selected === 'assistance';
        const isSerialNumber = selected === 'serial_number';
        assistanceFields.hidden = !isAssistance;
        eventFields.hidden = !isAssistance;
        assistanceTypes.forEach((input) => input.required = isAssistance);
        assistanceRequired.forEach((input) => input.required = isAssistance);
        serialRequired.forEach((input) => input.required = isSerialNumber);
        if (completionYearHint) completionYearHint.textContent = isSerialNumber ? 'required' : 'if applicable';
        if (finalStep) finalStep.textContent = isAssistance ? '04' : '03';
    };

    requestTypes.forEach((input) => input.addEventListener('change', syncType));
    syncType();

    const syncPurposeCount = () => {
        if (purposeCount && purpose) purposeCount.textContent = purpose.value.length.toLocaleString();
    };
    purpose?.addEventListener('input', syncPurposeCount);
    syncPurposeCount();

    attachment?.addEventListener('change', () => {
        const file = attachment.files?.[0];
        attachment.closest('.attachment-zone')?.classList.toggle('has-file', Boolean(file));
        if (attachmentLabel) attachmentLabel.textContent = file?.name || 'Attach a supporting file (optional)';
    });
});
