(function () {
    const scanner = document.querySelector('[data-omr-scanner]');
    if (!scanner) return;

    const video = scanner.querySelector('[data-omr-video]');
    const canvas = scanner.querySelector('[data-omr-canvas]');
    const context = canvas.getContext('2d', { willReadFrequently: true });
    const placeholder = scanner.querySelector('[data-omr-placeholder]');
    const cameraButton = scanner.querySelector('[data-omr-camera]');
    const captureButton = scanner.querySelector('[data-omr-capture]');
    const manualButton = scanner.querySelector('[data-omr-manual]');
    const upload = scanner.querySelector('[data-omr-upload]');
    const student = scanner.querySelector('[data-omr-student]');
    const message = scanner.querySelector('[data-omr-message]');
    const review = scanner.querySelector('[data-omr-review]');
    const answerGrid = scanner.querySelector('[data-omr-answers]');
    const itemCount = Number(scanner.dataset.items);
    const choiceCount = Number(scanner.dataset.choices);
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    let stream = null;
    let detectedConfidence = null;

    function setMessage(text, state) {
        message.textContent = text;
        message.className = `scanner-message ${state ? `is-${state}` : ''}`;
    }

    function stopCamera() {
        stream?.getTracks().forEach((track) => track.stop());
        stream = null;
        video.srcObject = null;
        video.classList.remove('active');
        captureButton.disabled = true;
        cameraButton.textContent = 'Open camera';
    }

    async function openCamera() {
        if (stream) {
            stopCamera();
            placeholder.hidden = false;
            setMessage('Camera closed.');
            return;
        }

        if (!navigator.mediaDevices?.getUserMedia) {
            setMessage('Camera access requires HTTPS or localhost. You can upload a photo instead.', 'error');
            return;
        }

        try {
            canvas.classList.remove('active');
            stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' }, width: { ideal: 1920 }, height: { ideal: 1080 } }, audio: false });
            video.srcObject = stream;
            await video.play();
            video.classList.add('active');
            placeholder.hidden = true;
            captureButton.disabled = false;
            cameraButton.textContent = 'Close camera';
            setMessage('Camera ready. Align all four black markers, then capture.', 'working');
        } catch (_) {
            stopCamera();
            setMessage('Camera permission was unavailable. Check browser permission or upload a photo.', 'error');
        }
    }

    function drawA4(source, sourceWidth, sourceHeight) {
        canvas.width = 1000;
        canvas.height = 1414;
        const targetRatio = canvas.width / canvas.height;
        const sourceRatio = sourceWidth / sourceHeight;
        let sx = 0;
        let sy = 0;
        let sw = sourceWidth;
        let sh = sourceHeight;

        if (sourceRatio > targetRatio) {
            sw = sourceHeight * targetRatio;
            sx = (sourceWidth - sw) / 2;
        } else {
            sh = sourceWidth / targetRatio;
            sy = (sourceHeight - sh) / 2;
        }

        context.drawImage(source, sx, sy, sw, sh, 0, 0, canvas.width, canvas.height);
        canvas.classList.add('active');
        video.classList.remove('active');
        placeholder.hidden = true;
    }

    function findMarker(image, region) {
        let sumX = 0;
        let sumY = 0;
        let count = 0;
        const xStart = Math.floor(region[0] * image.width);
        const yStart = Math.floor(region[1] * image.height);
        const xEnd = Math.floor(region[2] * image.width);
        const yEnd = Math.floor(region[3] * image.height);

        for (let y = yStart; y < yEnd; y += 2) {
            for (let x = xStart; x < xEnd; x += 2) {
                const offset = (y * image.width + x) * 4;
                const luminance = (image.data[offset] * .299) + (image.data[offset + 1] * .587) + (image.data[offset + 2] * .114);
                if (luminance < 75) {
                    sumX += x;
                    sumY += y;
                    count += 1;
                }
            }
        }

        if (count < 20) throw new Error('The four corner markers were not detected. Flatten the paper, improve the lighting, and try again.');
        return { x: sumX / count, y: sumY / count };
    }

    function mappedPoint(markers, templateX, templateY) {
        const u = (templateX - 70) / 860;
        const v = (templateY - 70) / 1274;
        const top = { x: markers.tl.x + ((markers.tr.x - markers.tl.x) * u), y: markers.tl.y + ((markers.tr.y - markers.tl.y) * u) };
        const bottom = { x: markers.bl.x + ((markers.br.x - markers.bl.x) * u), y: markers.bl.y + ((markers.br.y - markers.bl.y) * u) };
        return { x: top.x + ((bottom.x - top.x) * v), y: top.y + ((bottom.y - top.y) * v) };
    }

    function darknessAt(image, point, radius) {
        let total = 0;
        let samples = 0;
        const r = Math.max(4, Math.round(radius));
        for (let dy = -r; dy <= r; dy += 1) {
            for (let dx = -r; dx <= r; dx += 1) {
                if ((dx * dx) + (dy * dy) > r * r) continue;
                const x = Math.round(point.x + dx);
                const y = Math.round(point.y + dy);
                if (x < 0 || y < 0 || x >= image.width || y >= image.height) continue;
                const offset = (y * image.width + x) * 4;
                const luminance = (image.data[offset] * .299) + (image.data[offset + 1] * .587) + (image.data[offset + 2] * .114);
                total += 1 - (luminance / 255);
                samples += 1;
            }
        }
        return samples ? total / samples : 0;
    }

    function analyzeSheet() {
        const image = context.getImageData(0, 0, canvas.width, canvas.height);
        const markers = {
            tl: findMarker(image, [.035, .035, .11, .105]),
            tr: findMarker(image, [.89, .035, .965, .105]),
            bl: findMarker(image, [.035, .925, .11, .975]),
            br: findMarker(image, [.89, .925, .965, .975]),
        };
        const sheetWidth = Math.hypot(markers.tr.x - markers.tl.x, markers.tr.y - markers.tl.y);
        const radius = (sheetWidth / 860) * 10;
        const answers = [];
        const confidenceScores = [];

        for (let item = 0; item < itemCount; item += 1) {
            const values = [];
            for (let choice = 0; choice < choiceCount; choice += 1) {
                const point = mappedPoint(markers, 330 + (choice * 100), 245 + (item * 34));
                values.push(darknessAt(image, point, radius));
            }
            const ranked = values.map((value, index) => ({ value, index })).sort((a, b) => b.value - a.value);
            const margin = ranked[0].value - (ranked[1]?.value || 0);
            const clear = ranked[0].value >= .28 && margin >= .055;
            answers.push(clear ? String.fromCharCode(65 + ranked[0].index) : null);
            confidenceScores.push(Math.min(1, Math.max(0, (ranked[0].value - .18) * 2.2 + margin)));
        }

        detectedConfidence = (confidenceScores.reduce((sum, value) => sum + value, 0) / confidenceScores.length) * 100;
        return answers;
    }

    function renderAnswers(answers) {
        answerGrid.innerHTML = '';
        answers.forEach((answer, index) => {
            const label = document.createElement('label');
            const select = document.createElement('select');
            label.innerHTML = `<span>${index + 1}</span>`;
            select.innerHTML = '<option value="">Blank / unclear</option>';
            for (let choice = 0; choice < choiceCount; choice += 1) {
                const letter = String.fromCharCode(65 + choice);
                select.insertAdjacentHTML('beforeend', `<option value="${letter}">${letter}</option>`);
            }
            select.value = answer || '';
            label.appendChild(select);
            answerGrid.appendChild(label);
        });
        review.hidden = false;
        review.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function requireStudent() {
        if (student.value) return true;
        setMessage('Select the student before scanning or saving answers.', 'error');
        student.focus();
        return false;
    }

    function processCurrentImage() {
        if (!requireStudent()) return;
        try {
            const answers = analyzeSheet();
            renderAnswers(answers);
            const unclear = answers.filter((answer) => !answer).length;
            setMessage(`Sheet read successfully. Review ${unclear} blank or unclear answer${unclear === 1 ? '' : 's'} before saving.`, unclear ? 'working' : 'success');
        } catch (error) {
            review.hidden = true;
            setMessage(error.message || 'The answer sheet could not be read.', 'error');
        }
    }

    cameraButton.addEventListener('click', openCamera);
    captureButton.addEventListener('click', () => {
        if (!video.videoWidth || !requireStudent()) return;
        drawA4(video, video.videoWidth, video.videoHeight);
        stopCamera();
        processCurrentImage();
    });
    manualButton.addEventListener('click', () => {
        if (!requireStudent()) return;
        detectedConfidence = null;
        renderAnswers(Array(itemCount).fill(null));
        setMessage('Manual answer entry opened. Select each visible student response.', 'working');
    });
    upload.addEventListener('change', async () => {
        const file = upload.files?.[0];
        if (!file || !requireStudent()) return;
        try {
            stopCamera();
            const bitmap = await createImageBitmap(file);
            drawA4(bitmap, bitmap.width, bitmap.height);
            bitmap.close?.();
            processCurrentImage();
        } catch (_) {
            setMessage('The selected image could not be opened. Choose a clear JPG, PNG, or camera photo.', 'error');
        }
    });
    review.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!requireStudent()) return;
        const answers = Array.from(answerGrid.querySelectorAll('select')).map((select) => select.value || null);
        setMessage('Checking answers and saving the grade…', 'working');
        try {
            const response = await fetch(scanner.dataset.endpoint, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify({ student_id: Number(student.value), answers, confidence: detectedConfidence }),
            });
            const result = await response.json();
            if (!response.ok) throw new Error(result.message || Object.values(result.errors || {})[0]?.[0] || 'Unable to save the result.');
            stopCamera();
            setMessage(`${result.message} Score: ${result.score}/${result.max_score}.`, 'success');
            window.setTimeout(() => window.location.reload(), 1400);
        } catch (error) {
            setMessage(error.message || 'Unable to save the scan result.', 'error');
        }
    });
    window.addEventListener('pagehide', stopCamera);
})();
