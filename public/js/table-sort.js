(function () {
    const datePattern = /^(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\s+\d{1,2},\s+\d{4}(?:\s+\d{1,2}:\d{2}(?::\d{2})?\s*(?:AM|PM))?/i;
    const isoDatePattern = /^\d{4}-\d{2}-\d{2}(?:[T\s]\d{1,2}:\d{2}(?::\d{2})?)?/;
    const timePattern = /^(\d{1,2}):(\d{2})(?::(\d{2}))?\s*(AM|PM)$/i;
    const numberPattern = /^[₱$]?\s*-?[\d,]+(?:\.\d+)?\s*(?:%|pts?)?$/i;
    const fractionPattern = /^(-?[\d,]+(?:\.\d+)?)\s*(?:\/|of)\s*(-?[\d,]+(?:\.\d+)?)/i;
    const excludedHeaders = /^(?:action|actions|attendance qr|manual update|score and feedback)$/i;

    const normalize = (value) => value.replace(/\s+/g, ' ').trim();

    const sortableValue = (cell) => {
        const raw = normalize(cell.dataset.sortValue ?? cell.textContent ?? '');

        if (raw === '' || raw === '—' || raw === '-') return { type: 'empty', value: '' };

        const fraction = raw.match(fractionPattern);
        if (fraction) {
            const numerator = Number(fraction[1].replaceAll(',', ''));
            const denominator = Number(fraction[2].replaceAll(',', ''));

            return { type: 'number', value: denominator === 0 ? numerator : numerator / denominator };
        }

        if (numberPattern.test(raw)) {
            return { type: 'number', value: Number(raw.replace(/[₱$,%]|pts?/gi, '').replaceAll(',', '').trim()) };
        }

        const date = raw.match(datePattern) ?? raw.match(isoDatePattern);
        if (date) {
            const timestamp = Date.parse(date[0]);
            if (!Number.isNaN(timestamp)) return { type: 'number', value: timestamp };
        }

        const time = raw.match(timePattern);
        if (time) {
            let hour = Number(time[1]) % 12;
            if (time[4].toUpperCase() === 'PM') hour += 12;

            return {
                type: 'number',
                value: (hour * 3600) + (Number(time[2]) * 60) + Number(time[3] ?? 0),
            };
        }

        return { type: 'text', value: raw };
    };

    const compareValues = (left, right, direction) => {
        if (left.type === 'empty' && right.type !== 'empty') return 1;
        if (right.type === 'empty' && left.type !== 'empty') return -1;

        let comparison;
        if (left.type === 'number' && right.type === 'number') {
            comparison = left.value - right.value;
        } else {
            comparison = String(left.value).localeCompare(String(right.value), undefined, {
                numeric: true,
                sensitivity: 'base',
            });
        }

        return comparison * direction;
    };

    const enhanceTable = (table) => {
        const headerRow = table.tHead?.rows[0];
        const body = table.tBodies[0];

        if (!headerRow || !body || headerRow.cells.length === 0) return;

        const originalPositions = new WeakMap();
        Array.from(body.rows).forEach((row, index) => originalPositions.set(row, index));

        Array.from(headerRow.cells).forEach((header, columnIndex) => {
            const label = normalize(header.textContent ?? '');
            const hasCheckbox = header.querySelector('input[type="checkbox"]');

            if (!label || hasCheckbox || excludedHeaders.test(label) || header.dataset.sortDisabled !== undefined) return;

            const button = document.createElement('button');
            const labelNode = document.createElement('span');
            const indicator = document.createElement('span');

            button.type = 'button';
            button.className = 'table-sort-button';
            button.setAttribute('aria-label', `Sort by ${label}`);
            labelNode.textContent = label;
            indicator.className = 'table-sort-indicator';
            indicator.setAttribute('aria-hidden', 'true');
            indicator.textContent = '↕';
            button.append(labelNode, indicator);
            header.replaceChildren(button);
            header.classList.add('sortable-column');
            header.setAttribute('aria-sort', 'none');

            button.addEventListener('click', () => {
                const ascending = header.getAttribute('aria-sort') !== 'ascending';
                const direction = ascending ? 1 : -1;
                const rows = Array.from(body.rows);
                const sortableRows = rows.filter((row) => row.cells.length > columnIndex && !row.querySelector('.empty-state'));
                const fixedRows = rows.filter((row) => !sortableRows.includes(row));

                Array.from(headerRow.cells).forEach((item) => {
                    item.setAttribute('aria-sort', 'none');
                    const icon = item.querySelector('.table-sort-indicator');
                    if (icon) icon.textContent = '↕';
                });

                header.setAttribute('aria-sort', ascending ? 'ascending' : 'descending');
                indicator.textContent = ascending ? '↑' : '↓';

                sortableRows.sort((leftRow, rightRow) => {
                    const comparison = compareValues(
                        sortableValue(leftRow.cells[columnIndex]),
                        sortableValue(rightRow.cells[columnIndex]),
                        direction,
                    );

                    return comparison || originalPositions.get(leftRow) - originalPositions.get(rightRow);
                });

                body.append(...sortableRows, ...fixedRows);
            });
        });

        table.dataset.columnSorting = 'enabled';
    };

    document.querySelectorAll('table.data-table').forEach(enhanceTable);
})();
