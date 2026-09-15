(function () {
    const controls = document.querySelectorAll('[data-report-download]');

    if (!controls.length) {
        return;
    }

    const canChooseLocation = 'showSaveFilePicker' in window && window.isSecureContext;

    const timestamp = () => {
        const date = new Date();
        const pad = (value) => String(value).padStart(2, '0');

        return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}-${pad(date.getHours())}${pad(date.getMinutes())}${pad(date.getSeconds())}`;
    };

    const formatDetails = {
        pdf: {
            description: 'PDF document',
            extension: '.pdf',
            mime: 'application/pdf',
            urlAttribute: 'pdfUrl',
        },
        xlsx: {
            description: 'Excel workbook',
            extension: '.xlsx',
            mime: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            urlAttribute: 'excelUrl',
        },
    };

    const startBrowserDownload = (url) => {
        const link = document.createElement('a');
        link.href = url;
        link.hidden = true;
        document.body.appendChild(link);
        link.click();
        link.remove();
    };

    const withSelectedColumns = (url, fields) => {
        const downloadUrl = new URL(url, window.location.origin);
        downloadUrl.searchParams.delete('columns');
        downloadUrl.searchParams.delete('columns[]');
        fields.filter((field) => field.checked).forEach((field) => {
            downloadUrl.searchParams.append('columns[]', field.value);
        });

        return downloadUrl.toString();
    };

    controls.forEach((control) => {
        const formatSelect = control.querySelector('[data-report-format]');
        const saveButton = control.querySelector('[data-report-save]');
        const saveButtonLabel = control.querySelector('[data-report-save-label]');
        const status = control.querySelector('[data-report-save-status]');
        const fieldInputs = Array.from(control.querySelectorAll('[data-report-field]'));
        const fieldCount = control.querySelector('[data-report-field-count]');
        const selectAllButton = control.querySelector('[data-report-fields-all]');
        const clearAllButton = control.querySelector('[data-report-fields-clear]');

        if (!formatSelect || !saveButton || !status) {
            return;
        }

        const selectedFormat = () => formatDetails[formatSelect.value] || formatDetails.pdf;

        const updateSaveButton = () => {
            const selected = selectedFormat();
            const action = canChooseLocation ? 'Save' : 'Download';
            if (saveButtonLabel) {
                saveButtonLabel.textContent = `${action} ${selected.extension.slice(1).toUpperCase()} report`;
            }
        };

        const updateFieldCount = () => {
            const selectedCount = fieldInputs.filter((field) => field.checked).length;
            const selected = selectedFormat();
            fieldCount.textContent = `${selectedCount} selected`;
            saveButton.disabled = selectedCount === 0;
            status.textContent = selectedCount === 0
                ? 'Select at least one data field to download.'
                : (canChooseLocation
                    ? `${selected.extension.slice(1).toUpperCase()} · ${selectedCount} field${selectedCount === 1 ? '' : 's'} selected. Choose the save folder next.`
                    : `${selected.extension.slice(1).toUpperCase()} · ${selectedCount} field${selectedCount === 1 ? '' : 's'} selected. Saves to Downloads.`);
        };

        fieldInputs.forEach((field) => field.addEventListener('change', updateFieldCount));
        formatSelect.addEventListener('change', () => {
            updateSaveButton();
            updateFieldCount();
        });
        selectAllButton?.addEventListener('click', () => {
            fieldInputs.forEach((field) => { field.checked = true; });
            updateFieldCount();
        });
        clearAllButton?.addEventListener('click', () => {
            fieldInputs.forEach((field) => { field.checked = false; });
            updateFieldCount();
        });

        if (!canChooseLocation) {
            updateSaveButton();
        }

        updateFieldCount();

        saveButton.addEventListener('click', async () => {
            const selected = selectedFormat();
            const selectedFields = fieldInputs.filter((field) => field.checked);
            const baseUrl = control.dataset[selected.urlAttribute];

            if (!selectedFields.length) {
                status.textContent = 'Select at least one data field to download.';
                return;
            }

            if (!baseUrl) {
                status.textContent = 'The selected download is not available.';
                return;
            }

            const url = withSelectedColumns(baseUrl, fieldInputs);

            if (!canChooseLocation) {
                startBrowserDownload(url);
                status.textContent = `Downloading the ${selected.description}.`;
                return;
            }

            try {
                const fileHandle = await window.showSaveFilePicker({
                    suggestedName: `${control.dataset.baseFilename || 'student-records'}-${timestamp()}${selected.extension}`,
                    types: [{
                        description: selected.description,
                        accept: { [selected.mime]: [selected.extension] },
                    }],
                });

                saveButton.disabled = true;
                formatSelect.disabled = true;
                status.textContent = `Preparing the ${selected.description}...`;

                const response = await fetch(url, {
                    credentials: 'same-origin',
                    headers: { Accept: selected.mime },
                });

                if (!response.ok) {
                    throw new Error(`Download failed with status ${response.status}.`);
                }

                const writable = await fileHandle.createWritable();
                await writable.write(await response.blob());
                await writable.close();
                status.textContent = `${selected.description} saved successfully.`;
            } catch (error) {
                if (error && error.name === 'AbortError') {
                    status.textContent = 'Save cancelled. No file was downloaded.';
                } else {
                    status.textContent = 'The file could not be saved. Please try again.';
                    console.error('Report save failed:', error);
                }
            } finally {
                formatSelect.disabled = false;
                saveButton.disabled = fieldInputs.every((field) => !field.checked);
            }
        });
    });
})();
