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
    let scanning = false;
    let submitting = false;

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
        startButton.textContent = 'Start camera scanner';
    }

    async function submitCode(code) {
        if (submitting || !code.trim()) return;
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

            stopCamera();
            setMessage(result.message, 'success');
            input.value = '';
            window.setTimeout(() => window.location.reload(), 1200);
        } catch (error) {
            setMessage(error.message || 'Unable to read the student QR.', 'error');
            submitting = false;
        }
    }

    async function scanFrame() {
        if (!scanning || !detector || submitting) return;

        try {
            const codes = await detector.detect(video);
            if (codes.length) {
                await submitCode(codes[0].rawValue);
                return;
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

        if (!('BarcodeDetector' in window)) {
            setMessage('QR camera scanning is not supported by this browser. Use Chrome or enter the QR code manually.', 'error');
            input.focus();
            return;
        }

        try {
            detector = new BarcodeDetector({ formats: ['qr_code'] });
            stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: { ideal: 'environment' } },
                audio: false,
            });
            video.srcObject = stream;
            await video.play();
            scanning = true;
            placeholder.hidden = true;
            video.classList.add('active');
            startButton.textContent = 'Stop camera';
            setMessage('Camera active. Hold the student QR inside the frame.', 'working');
            scanFrame();
        } catch (_) {
            stopCamera();
            setMessage('Camera access was unavailable. Check browser permission or enter the code manually.', 'error');
        }
    }

    startButton?.addEventListener('click', startCamera);
    form?.addEventListener('submit', (event) => {
        event.preventDefault();
        submitCode(input.value);
    });
    window.addEventListener('pagehide', stopCamera);
})();
