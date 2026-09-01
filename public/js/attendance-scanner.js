(function () {
    const scanner = document.querySelector('[data-attendance-scanner]');
    if (!scanner) return;

    const video = scanner.querySelector('[data-scanner-video]');
    const placeholder = scanner.querySelector('[data-scanner-placeholder]');
    const startButton = scanner.querySelector('[data-scanner-start]');
    const message = scanner.querySelector('[data-scanner-message]');
    const form = scanner.querySelector('[data-scanner-form]');
    const input = scanner.querySelector('[data-scanner-input]');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    let stream = null;
    let detector = null;
    let fallbackCanvas = null;
    let fallbackContext = null;
    let scanning = false;
    let submitting = false;
    let lastScannedCode = '';
    let framesWithoutCode = 0;

    function setMessage(text, state) {
        message.textContent = text;
        message.className = `scanner-message ${state ? `is-${state}` : ''}`;
    }

    function stopCamera() {
        scanning = false;
        stream?.getTracks().forEach((track) => track.stop());
        stream = null;
        video.srcObject = null;
        video.classList.remove('active');
        placeholder.hidden = false;
        startButton.textContent = 'Open camera';
        lastScannedCode = '';
        framesWithoutCode = 0;
    }

    function updateAttendanceRecord(result) {
        const row = document.querySelector(`[data-attendance-student="${result.student_id}"]`);
        if (!row) return;

        const wasRecorded = row.dataset.attendanceRecorded === '1';
        const statusBadge = row.querySelector('[data-record-status]');
        const checkInCell = row.querySelector('[data-record-check-in]');
        const manualStatus = row.querySelector('select[name="status"]');

        row.dataset.attendanceRecorded = '1';
        if (statusBadge) {
            statusBadge.className = `status-badge ${['present', 'late'].includes(result.status) ? 'active' : 'inactive'}`;
            statusBadge.replaceChildren(document.createElement('i'), document.createTextNode(result.status_label));
        }
        if (checkInCell) checkInCell.textContent = result.checked_in_at || '—';
        if (manualStatus) manualStatus.value = result.status;

        const counter = document.querySelector('[data-attendance-record-count]');
        if (counter && result.recorded && !wasRecorded) {
            const total = Number(counter.dataset.attendanceRecordCount || 0) + 1;
            counter.dataset.attendanceRecordCount = String(total);
            counter.textContent = `${total} recorded`;
        }
    }

    async function submitCode(code) {
        if (submitting || !code.trim()) return false;
        submitting = true;
        setMessage('Verifying student QR…', 'working');

        try {
            const response = await fetch(scanner.dataset.endpoint, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify({ qr_code: code.trim() }),
            });
            const result = await response.json();

            if (!response.ok) {
                const validationMessage = result.errors?.qr_code?.[0] || result.message || 'Unable to record attendance.';
                throw new Error(validationMessage);
            }

            updateAttendanceRecord(result);
            setMessage(result.message, 'success');
            input.value = '';
            submitting = false;
            return true;
        } catch (error) {
            setMessage(error.message || 'Unable to read the student QR.', 'error');
            submitting = false;
            return false;
        }
    }

    function decodeWithFallback() {
        if (typeof window.jsQR !== 'function' || video.readyState < HTMLMediaElement.HAVE_CURRENT_DATA) return null;

        const sourceWidth = video.videoWidth;
        const sourceHeight = video.videoHeight;
        if (!sourceWidth || !sourceHeight) return null;

        const maximumWidth = 960;
        const scale = Math.min(1, maximumWidth / sourceWidth);
        const width = Math.max(1, Math.round(sourceWidth * scale));
        const height = Math.max(1, Math.round(sourceHeight * scale));

        fallbackCanvas ||= document.createElement('canvas');
        fallbackContext ||= fallbackCanvas.getContext('2d', { willReadFrequently: true });
        if (!fallbackContext) return null;

        if (fallbackCanvas.width !== width || fallbackCanvas.height !== height) {
            fallbackCanvas.width = width;
            fallbackCanvas.height = height;
        }

        fallbackContext.drawImage(video, 0, 0, width, height);
        const image = fallbackContext.getImageData(0, 0, width, height);

        return window.jsQR(image.data, width, height, { inversionAttempts: 'attemptBoth' });
    }

    async function scanFrame() {
        if (!scanning) return;

        if (submitting) {
            window.setTimeout(scanFrame, 220);
            return;
        }

        try {
            let code = null;
            if (detector) {
                try {
                    code = (await detector.detect(video))[0]?.rawValue || null;
                } catch (_) {
                    detector = null;
                }
            }
            code ||= decodeWithFallback()?.data;

            if (code) {
                framesWithoutCode = 0;
                if (code !== lastScannedCode) {
                    lastScannedCode = code;
                    await submitCode(code);
                }
            } else {
                framesWithoutCode += 1;
                if (framesWithoutCode >= 6) lastScannedCode = '';
            }
        } catch (_) {
            // A camera frame may be unavailable while the stream is starting.
        }

        if (scanning) window.setTimeout(scanFrame, 220);
    }

    async function startCamera() {
        if (stream) {
            stopCamera();
            setMessage('Camera stopped.');
            return;
        }

        if (!navigator.mediaDevices?.getUserMedia) {
            setMessage('Camera access requires HTTPS or localhost. Open SNAPIE through a secure connection, or enter the QR code manually.', 'error');
            input.focus();
            return;
        }

        const hasNativeDetector = 'BarcodeDetector' in window;
        const hasFallbackDetector = typeof window.jsQR === 'function';
        if (!hasNativeDetector && !hasFallbackDetector) {
            setMessage('The QR scanner could not load. Refresh the page, then try again or enter the code manually.', 'error');
            input.focus();
            return;
        }

        try {
            detector = null;
            if (hasNativeDetector) {
                try {
                    detector = new BarcodeDetector({ formats: ['qr_code'] });
                } catch (_) {
                    // The local decoder below remains available when the native API is incomplete.
                }
            }
            stream = await navigator.mediaDevices.getUserMedia({
                video: {
                    facingMode: { ideal: 'environment' },
                    width: { ideal: 1280 },
                    height: { ideal: 720 },
                },
                audio: false,
            });
            video.srcObject = stream;
            await video.play();
            scanning = true;
            placeholder.hidden = true;
            video.classList.add('active');
            startButton.textContent = 'Close camera';
            setMessage('Camera active. Hold the student QR inside the frame.', 'working');
            scanFrame();
        } catch (error) {
            stopCamera();
            const cameraError = {
                NotAllowedError: 'Camera permission was blocked. Allow camera access in the browser site settings, then try again.',
                NotFoundError: 'No camera was found on this device.',
                NotReadableError: 'The camera is being used by another application. Close it there, then try again.',
            }[error?.name];
            setMessage(cameraError || 'Camera access was unavailable. Check browser permission or enter the code manually.', 'error');
        }
    }

    startButton?.addEventListener('click', startCamera);
    form?.addEventListener('submit', (event) => {
        event.preventDefault();
        submitCode(input.value);
    });
    window.addEventListener('pagehide', stopCamera);
})();
