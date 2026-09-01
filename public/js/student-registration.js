(function () {
    const form = document.getElementById('registration-form');
    if (!form) return;

    const panels = [...document.querySelectorAll('[data-step]')];
    const progress = [...document.querySelectorAll('[data-progress-step]')];
    const next = document.getElementById('registration-next');
    const back = document.getElementById('registration-back');
    const submit = document.getElementById('registration-submit');
    const stepNumber = document.getElementById('current-step-number');
    let current = 0;

    function showStep(index) {
        current = index;
        panels.forEach((panel, i) => {
            panel.hidden = i !== index;
            panel.classList.toggle('active', i === index);
        });
        progress.forEach((item, i) => {
            item.classList.toggle('active', i === index);
            item.classList.toggle('complete', i < index);
        });
        stepNumber.textContent = index + 1;
        back.hidden = index === 0;
        next.hidden = index === panels.length - 1;
        submit.hidden = index !== panels.length - 1;
        next.textContent = index === 0 ? 'Continue to Part I  →' : 'Continue  →';
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function panelIsValid(panel) {
        const fields = [...panel.querySelectorAll('input, select, textarea')].filter(field => !field.disabled && !field.hidden && field.type !== 'hidden');
        for (const field of fields) {
            if (!field.checkValidity()) {
                field.reportValidity();
                field.focus();
                return false;
            }
        }
        return true;
    }

    next.addEventListener('click', () => {
        if (panelIsValid(panels[current])) showStep(Math.min(current + 1, panels.length - 1));
    });
    back.addEventListener('click', () => showStep(Math.max(current - 1, 0)));

    const cor = document.getElementById('cor');
    const corName = document.getElementById('cor-file-name');
    const corZone = document.getElementById('cor-zone');
    cor.addEventListener('change', () => {
        const file = cor.files[0];
        const validType = file && ['application/pdf', 'image/jpeg', 'image/png'].includes(file.type);
        const validSize = file && file.size <= 5 * 1024 * 1024;
        next.disabled = !(validType && validSize);
        corZone.classList.toggle('ready', !next.disabled);
        corName.textContent = file ? file.name : 'Choose your Certificate of Registration';
        cor.setCustomValidity(file && !validType ? 'Choose a PDF, JPG, or PNG file.' : file && !validSize ? 'The COR must not exceed 5 MB.' : '');
    });
    ['dragenter', 'dragover'].forEach(eventName => corZone.addEventListener(eventName, event => {
        event.preventDefault();
        corZone.classList.add('ready');
    }));
    ['dragleave', 'drop'].forEach(eventName => corZone.addEventListener(eventName, event => {
        event.preventDefault();
        corZone.classList.remove('ready');
    }));
    corZone.addEventListener('drop', event => {
        if (!event.dataTransfer.files.length) return;
        cor.files = event.dataTransfer.files;
        cor.dispatchEvent(new Event('change'));
    });

    function setupNA(checkboxId, inputId) {
        const checkbox = document.getElementById(checkboxId);
        const input = document.getElementById(inputId);
        const sync = () => {
            input.disabled = checkbox.checked;
            input.required = !checkbox.checked;
            if (checkbox.checked) input.value = '';
        };
        checkbox.addEventListener('change', sync);
        sync();
    }
    setupNA('extension-na', 'extension-name');
    setupNA('middle-na', 'middle-name');

    const sameAddress = document.getElementById('same-address');
    const emergencyAddress = document.getElementById('emergency-address');
    function syncEmergencyAddress() {
        emergencyAddress.disabled = sameAddress.checked;
        emergencyAddress.required = !sameAddress.checked;
        if (sameAddress.checked) emergencyAddress.value = '';
    }
    sameAddress.addEventListener('change', syncEmergencyAddress);
    syncEmergencyAddress();

    const religion = document.getElementById('religion');
    const religionOther = document.getElementById('religion-other');
    function syncReligion() {
        const isOther = religion.value === 'Others';
        religionOther.hidden = !isOther;
        religionOther.required = isOther;
        if (!isOther) religionOther.value = '';
    }
    religion.addEventListener('change', syncReligion);
    syncReligion();

    const photoInput = document.getElementById('formal-photo');
    const photoPreview = document.getElementById('photo-preview');
    const photoPlaceholder = document.getElementById('photo-placeholder');
    photoInput.addEventListener('change', () => {
        const file = photoInput.files[0];
        if (!file) return;
        const validType = ['image/jpeg', 'image/png'].includes(file.type);
        const validSize = file.size <= 3 * 1024 * 1024;
        photoInput.setCustomValidity(!validType ? 'Choose a JPG or PNG image.' : !validSize ? 'The photo must not exceed 3 MB.' : '');
        if (validType) {
            photoPreview.src = URL.createObjectURL(file);
            photoPreview.hidden = false;
            photoPlaceholder.hidden = true;
        }
    });

    const API = 'https://psgc.gitlab.io/api';
    async function fetchPlaces(url) {
        const response = await fetch(url);
        if (!response.ok) throw new Error('PSGC data could not be loaded.');
        return response.json();
    }
    function setOptions(select, items, placeholder, selectedCode) {
        select.innerHTML = `<option value="">${placeholder}</option>` + items
            .sort((a, b) => a.name.localeCompare(b.name))
            .map(item => `<option value="${item.code}" data-name="${item.name.replace(/"/g, '&quot;')}">${item.name}</option>`).join('');
        select.disabled = false;
        if (selectedCode) select.value = selectedCode;
    }
    function syncName(select, hidden) {
        hidden.value = select.selectedOptions[0]?.dataset.name || '';
    }
    async function citiesFor(provinceCode) {
        if (provinceCode === '130000000') return fetchPlaces(`${API}/regions/130000000/cities-municipalities/`);
        return fetchPlaces(`${API}/provinces/${provinceCode}/cities-municipalities/`);
    }
    async function setupAddress({ provinceId, cityId, barangayId, provinceNameId, cityNameId, barangayNameId }) {
        const province = document.getElementById(provinceId);
        const city = document.getElementById(cityId);
        const provinceName = document.getElementById(provinceNameId);
        const cityName = document.getElementById(cityNameId);
        const barangay = barangayId ? document.getElementById(barangayId) : null;
        const barangayName = barangayNameId ? document.getElementById(barangayNameId) : null;
        const oldProvince = province.dataset.oldCode;
        const oldCity = city.dataset.oldCode;
        const oldBarangay = barangay?.dataset.oldCode;
        try {
            const provinces = await fetchPlaces(`${API}/provinces/`);
            provinces.push({ code: '130000000', name: 'Metro Manila (NCR)' });
            setOptions(province, provinces, 'Select province', oldProvince);
            syncName(province, provinceName);
            if (oldProvince) await loadCities(oldCity, oldBarangay);
        } catch (error) {
            province.innerHTML = '<option value="">Unable to load locations — refresh the page</option>';
            province.disabled = true;
        }

        async function loadCities(selectedCity, selectedBarangay) {
            city.innerHTML = '<option value="">Loading cities…</option>';
            city.disabled = true;
            if (barangay) { barangay.innerHTML = '<option value="">Select city first</option>'; barangay.disabled = true; }
            if (!province.value) return;
            setOptions(city, await citiesFor(province.value), 'Select city / municipality', selectedCity);
            syncName(city, cityName);
            if (selectedCity && barangay) await loadBarangays(selectedBarangay);
        }
        async function loadBarangays(selectedBarangay) {
            barangay.innerHTML = '<option value="">Loading barangays…</option>';
            barangay.disabled = true;
            if (!city.value) return;
            setOptions(barangay, await fetchPlaces(`${API}/cities-municipalities/${city.value}/barangays/`), 'Select barangay', selectedBarangay);
            syncName(barangay, barangayName);
        }
        province.addEventListener('change', async () => { syncName(province, provinceName); await loadCities('', ''); });
        city.addEventListener('change', async () => { syncName(city, cityName); if (barangay) await loadBarangays(''); });
        if (barangay) barangay.addEventListener('change', () => syncName(barangay, barangayName));
    }

    Promise.all([
        setupAddress({ provinceId: 'province', cityId: 'city', barangayId: 'barangay', provinceNameId: 'province-name', cityNameId: 'city-name', barangayNameId: 'barangay-name' }),
        setupAddress({ provinceId: 'birth-province', cityId: 'birth-city', provinceNameId: 'birth-province-name', cityNameId: 'birth-city-name' })
    ]);

    form.addEventListener('submit', event => {
        if (!form.checkValidity()) {
            event.preventDefault();
            const invalid = form.querySelector(':invalid');
            const panel = invalid?.closest('[data-step]');
            if (panel) showStep(Number(panel.dataset.step));
            invalid?.reportValidity();
        }
    });
})();
