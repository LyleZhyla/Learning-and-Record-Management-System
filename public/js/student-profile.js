(function () {
    const form = document.getElementById('student-profile-form');
    if (!form) return;

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

    function setupOther(selectId, inputId) {
        const select = document.getElementById(selectId);
        const input = document.getElementById(inputId);
        const sync = () => {
            const other = select.value === 'Others';
            input.hidden = !other;
            input.required = other;
            if (!other) input.value = '';
        };
        select.addEventListener('change', sync);
        sync();
    }
    setupOther('religion', 'religion-other');
    setupOther('year-section', 'year-section-other');

    form.querySelectorAll('[data-digits-only]').forEach(input => {
        input.addEventListener('input', () => {
            input.value = input.value.replace(/\D/g, '').slice(0, Number(input.maxLength) || undefined);
        });
    });

    const academics = window.studentProfileAcademics || {};
    const college = document.getElementById('academic-college');
    const course = document.getElementById('academic-course');
    const major = document.getElementById('academic-major');

    function escapeHtml(value) {
        return String(value).replace(/[&<>"']/g, character => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
        })[character]);
    }

    function setAcademicOptions(select, values, placeholder, selected = '') {
        select.innerHTML = `<option value="">${placeholder}</option>` + values
            .map(value => `<option value="${escapeHtml(value)}">${escapeHtml(value)}</option>`).join('');
        select.disabled = values.length === 0;
        if (values.includes(selected)) select.value = selected;
    }

    function loadMajors(selected = '') {
        const values = academics[college.value]?.[course.value] || [];
        setAcademicOptions(major, values, 'Select major', selected);
        if (values.length === 1 && values[0] === 'N/A') major.value = 'N/A';
    }

    function loadCourses(selectedCourse = '', selectedMajor = '') {
        setAcademicOptions(course, Object.keys(academics[college.value] || {}), 'Select course', selectedCourse);
        loadMajors(selectedMajor);
    }

    college.addEventListener('change', () => loadCourses());
    course.addEventListener('change', () => loadMajors());
    if (college.value) loadCourses(course.dataset.oldValue, major.dataset.oldValue);

    const endpoints = window.studentProfileLocationEndpoints || {};
    async function fetchPlaces(url) {
        const response = await fetch(url, { headers: { Accept: 'application/json' } });
        if (!response.ok) throw new Error('Location data could not be loaded.');
        return response.json();
    }

    function setLocationOptions(select, items, placeholder, selectedCode = '') {
        select.innerHTML = `<option value="">${placeholder}</option>` + items
            .sort((a, b) => a.name.localeCompare(b.name))
            .map(item => `<option value="${escapeHtml(item.code)}" data-name="${escapeHtml(item.name)}">${escapeHtml(item.name)}</option>`).join('');
        select.disabled = false;
        if (selectedCode) select.value = selectedCode;
    }

    function syncName(select, hidden) {
        hidden.value = select.selectedOptions[0]?.dataset.name || '';
    }

    async function setupAddress({ provinceId, cityId, barangayId, provinceNameId, cityNameId, barangayNameId }) {
        const province = document.getElementById(provinceId);
        const city = document.getElementById(cityId);
        const barangay = barangayId ? document.getElementById(barangayId) : null;
        const provinceName = document.getElementById(provinceNameId);
        const cityName = document.getElementById(cityNameId);
        const barangayName = barangayNameId ? document.getElementById(barangayNameId) : null;
        const initialProvince = province.dataset.oldCode || '';
        const initialCity = city.dataset.oldCode || '';
        const initialBarangay = barangay?.dataset.oldCode || '';

        async function loadBarangays(selected = '') {
            if (!barangay || !city.value) return;
            barangay.innerHTML = '<option value="">Loading barangays…</option>';
            barangay.disabled = true;
            try {
                const url = endpoints.barangays.replace('__CODE__', encodeURIComponent(city.value));
                setLocationOptions(barangay, await fetchPlaces(url), 'Select barangay', selected);
                syncName(barangay, barangayName);
            } catch (error) {
                barangay.innerHTML = '<option value="">Unable to load barangays — select the city again</option>';
            }
        }

        async function loadCities(selectedCity = '', selectedBarangay = '') {
            city.innerHTML = '<option value="">Loading cities…</option>';
            city.disabled = true;
            if (barangay) {
                barangay.innerHTML = '<option value="">Select city first</option>';
                barangay.disabled = true;
            }
            if (!province.value) return;
            try {
                const url = endpoints.cities.replace('__CODE__', encodeURIComponent(province.value));
                setLocationOptions(city, await fetchPlaces(url), 'Select city / municipality', selectedCity);
                syncName(city, cityName);
                if (selectedCity && barangay) await loadBarangays(selectedBarangay);
            } catch (error) {
                city.innerHTML = '<option value="">Unable to load cities — select the province again</option>';
            }
        }

        province.addEventListener('change', async () => {
            syncName(province, provinceName);
            await loadCities();
        });
        city.addEventListener('change', async () => {
            syncName(city, cityName);
            if (barangay) await loadBarangays();
        });
        if (barangay) barangay.addEventListener('change', () => syncName(barangay, barangayName));

        if (initialProvince) province.value = initialProvince;
        syncName(province, provinceName);
        if (province.value) await loadCities(initialCity, initialBarangay);
    }

    Promise.all([
        setupAddress({ provinceId: 'province', cityId: 'city', barangayId: 'barangay', provinceNameId: 'province-name', cityNameId: 'city-name', barangayNameId: 'barangay-name' }),
        setupAddress({ provinceId: 'birth-province', cityId: 'birth-city', provinceNameId: 'birth-province-name', cityNameId: 'birth-city-name' })
    ]);
})();
