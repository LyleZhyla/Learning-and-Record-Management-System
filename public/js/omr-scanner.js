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
    const templateBottomMarkerY = Number(scanner.dataset.templateBottom);
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

    function drawImage(source, sourceWidth, sourceHeight) {
        canvas.width = 1000;
        canvas.height = Math.max(460, Math.round(canvas.width * (sourceHeight / sourceWidth)));
        context.drawImage(source, 0, 0, sourceWidth, sourceHeight, 0, 0, canvas.width, canvas.height);
        canvas.classList.add('active');
        video.classList.remove('active');
        placeholder.hidden = true;
    }

    function findSideMarkers(image, startRatio, endRatio) {
        const xStart = Math.floor(startRatio * image.width);
        const xEnd = Math.ceil(endRatio * image.width);
        const regionWidth = xEnd - xStart;
        const visited = new Uint8Array(regionWidth * image.height);
        const candidates = [];
        const isDark = (x, y) => {
            const offset = (y * image.width + x) * 4;
            return (image.data[offset] * .299) + (image.data[offset + 1] * .587) + (image.data[offset + 2] * .114) < 75;
        };

        for (let y = 0; y < image.height; y += 1) {
            for (let x = xStart; x < xEnd; x += 1) {
                const startIndex = (y * regionWidth) + (x - xStart);
                if (visited[startIndex] || !isDark(x, y)) continue;

                const queue = [[x, y]];
                visited[startIndex] = 1;
                let head = 0;
                let area = 0;
                let sumX = 0;
                let sumY = 0;
                let minX = x;
                let maxX = x;
                let minY = y;
                let maxY = y;

                while (head < queue.length) {
                    const [currentX, currentY] = queue[head++];
                    area += 1;
                    sumX += currentX;
                    sumY += currentY;
                    minX = Math.min(minX, currentX);
                    maxX = Math.max(maxX, currentX);
                    minY = Math.min(minY, currentY);
                    maxY = Math.max(maxY, currentY);

                    [[1, 0], [-1, 0], [0, 1], [0, -1]].forEach(([dx, dy]) => {
                        const nextX = currentX + dx;
                        const nextY = currentY + dy;
                        if (nextX < xStart || nextX >= xEnd || nextY < 0 || nextY >= image.height) return;
                        const index = (nextY * regionWidth) + (nextX - xStart);
                        if (visited[index] || !isDark(nextX, nextY)) return;
                        visited[index] = 1;
                        queue.push([nextX, nextY]);
                    });
                }

                const width = maxX - minX + 1;
                const height = maxY - minY + 1;
                const ratio = width / height;
                const density = area / (width * height);
                if (width >= 6 && height >= 6 && ratio >= .55 && ratio <= 1.8 && density >= .45) {
                    candidates.push({ x: sumX / area, y: sumY / area, area });
                }
            }
        }

        const largestArea = Math.max(0, ...candidates.map((candidate) => candidate.area));
        const likelyMarkers = candidates.filter((candidate) => candidate.area >= largestArea * .35);
        if (likelyMarkers.length < 2) throw new Error('The four corner markers were not detected. Keep the complete answer-sheet image visible, flatten the paper, and improve the lighting.');

        likelyMarkers.sort((a, b) => a.y - b.y);
        const top = likelyMarkers[0];
        const bottom = likelyMarkers[likelyMarkers.length - 1];
        if (bottom.y - top.y < image.height * .15) throw new Error('The top and bottom markers are too close or unclear. Capture the complete answer-sheet image.');

        return { top, bottom };
    }

    function mappedPoint(markers, templateX, templateY) {
        const u = (templateX - 70) / 860;
        const v = (templateY - 70) / (templateBottomMarkerY - 70);
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
        const leftMarkers = findSideMarkers(image, 0, .44);
        const rightMarkers = findSideMarkers(image, .56, 1);
        const markers = { tl: leftMarkers.top, tr: rightMarkers.top, bl: leftMarkers.bottom, br: rightMarkers.bottom };
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
        drawImage(video, video.videoWidth, video.videoHeight);
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
            drawImage(bitmap, bitmap.width, bitmap.height);
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
