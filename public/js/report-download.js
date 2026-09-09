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

    controls.forEach((control) => {
        const formatSelect = control.querySelector('[data-report-format]');
        const saveButton = control.querySelector('[data-report-save]');
        const status = control.querySelector('[data-report-save-status]');

        if (!formatSelect || !saveButton || !status) {
            return;
        }

        if (!canChooseLocation) {
            saveButton.textContent = 'Download records';
            status.textContent = 'Your browser will use its configured Downloads location or Save As prompt.';
        }

        saveButton.addEventListener('click', async () => {
            const selected = formatDetails[formatSelect.value] || formatDetails.pdf;
            const url = control.dataset[selected.urlAttribute];

            if (!url) {
                status.textContent = 'The selected download is not available.';
                return;
            }

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
                saveButton.disabled = false;
                formatSelect.disabled = false;
            }
        });
    });
})();
