const collator = new Intl.Collator(undefined, { numeric: false, sensitivity: 'base' });

// decimal: the value's column holds fractions, so whole numbers there get the same decimal places
export function displayValue(value, precision = 3, decimal = false) {
    if (value === null || value === undefined) return 'NA';
    if (typeof value === 'boolean') return value ? 'TRUE' : 'FALSE';
    if (typeof value === 'number' && Number.isFinite(value) && (decimal || !Number.isInteger(value)) && precision !== 'full') {
        const rounded = value.toFixed(precision);
        return Number(rounded) === 0 ? (0).toFixed(precision) : rounded;
    }
    return String(value);
}

// A p-value that rounds to zero reads as "< 0.001" at three decimal places
const pValueColumns = new Set(['p_value', 'p.value', 'p-value', 'pr(>|t|)', 'pr(>|z|)', 'pvalue']);
export function isPValueColumn(name) {
    return pValueColumns.has(String(name ?? '').toLowerCase());
}

export function displayPValue(value, precision = 3) {
    if (typeof value === 'number' && Number.isFinite(value) && precision !== 'full' && value > 0 && value < 10 ** -precision) {
        return `< ${(10 ** -precision).toFixed(precision)}`;
    }
    return displayValue(value, precision, true);
}

export function compareValues(left, right, type = 'string', direction = 'asc') {
    // Missing values stay last in either direction.
    const leftMissing = left === null || left === undefined || (type === 'number' && left === '');
    const rightMissing = right === null || right === undefined || (type === 'number' && right === '');
    if (leftMissing || rightMissing) return Number(leftMissing) - Number(rightMissing);
    let result;
    if (type === 'number' && /^[-+]?\d+$/.test(String(left)) && /^[-+]?\d+$/.test(String(right))) {
        const a = BigInt(left);
        const b = BigInt(right);
        result = a < b ? -1 : a > b ? 1 : 0;
    } else if (type === 'number' && Number.isFinite(Number(left)) && Number.isFinite(Number(right))) {
        result = Number(left) - Number(right);
    } else if (type === 'boolean') {
        result = Number(left) - Number(right);
    } else if (type === 'datetime' && Number.isFinite(Date.parse(left)) && Number.isFinite(Date.parse(right))) {
        result = Date.parse(left) - Date.parse(right);
    } else {
        result = collator.compare(String(left), String(right));
    }
    return direction === 'desc' ? -result : result;
}

export function tableCsv(table, rows = table.rows) {
    const quote = (value) => `"${(value === null || value === undefined ? '' : String(value)).replaceAll('"', '""')}"`;
    return [table.columns.map((column) => column.name), ...rows].map((row) => row.map(quote).join(',')).join('\r\n');
}

export function tablePage(table, { query = '', sortColumn = null, direction = 'asc', page = 0, pageSize = 25 } = {}) {
    const search = query.trim().toLocaleLowerCase();
    const rows = table.rows.map((row, index) => ({ row, index }))
        .filter(({ row }) => !search || row.some((cell) => (displayValue(cell).toLocaleLowerCase().includes(search) || String(cell ?? '').toLocaleLowerCase().includes(search))));
    if (sortColumn !== null && table.columns[sortColumn]) {
        rows.sort((a, b) => compareValues(a.row[sortColumn], b.row[sortColumn], table.columns[sortColumn].type, direction) || a.index - b.index);
    }
    const pages = Math.max(1, Math.ceil(rows.length / pageSize));
    const current = Math.min(Math.max(0, page), pages - 1);
    const start = current * pageSize;
    return { rows: rows.slice(start, start + pageSize).map(({ row }) => row), total: rows.length, pages, page: current, start };
}

export function mountTable(container) {
    const table = JSON.parse(container.querySelector('[data-table-values]').textContent);
    const body = container.querySelector('tbody');
    const search = container.querySelector('[data-table-search]');
    const size = container.querySelector('[data-table-size]');
    const previous = container.querySelector('[data-table-previous]');
    const next = container.querySelector('[data-table-next]');
    const status = container.querySelector('[data-table-status]');
    const exportButton = container.querySelector('[data-table-export]');
    const precision = container.querySelector('[data-table-precision]');
    const state = { precision: 3, query: '', sortColumn: null, direction: 'asc', page: 0, pageSize: 25 };
    const modelTable = container.dataset.modelTable === 'true';
    const decimal = table.columns.map((column, index) => column.type === 'number'
        && table.rows.some((row) => typeof row[index] === 'number' && !Number.isInteger(row[index])));

    function render() {
        const result = tablePage(table, state);
        state.page = result.page;
        const fragment = document.createDocumentFragment();
        for (const row of result.rows) {
            const tr = document.createElement('tr');
            tr.className = 'border-b border-slate-200 dark:border-slate-700';
            table.columns.forEach((column, index) => {
                const cell = document.createElement('td');
                cell.className = 'px-4 py-2 whitespace-nowrap text-slate-700 dark:text-slate-200';
                if (['number', 'date', 'datetime'].includes(column.type) || (typeof row[index] === 'string' && /^[+-]?(?:\d+\.?\d*|\.\d+)(?:e[+-]?\d+)?$/i.test(row[index]))) cell.classList.add('font-mono', 'tabular-nums');
                if (column.type === 'number') cell.classList.add('text-right', 'tabular-nums');
                if (row[index] === null) cell.classList.add('text-slate-400');
                const reported = row[index] !== null && row[index] !== undefined;
                cell.textContent = !reported && modelTable ? '—' : isPValueColumn(column.name) ? displayPValue(row[index], state.precision) : displayValue(row[index], state.precision, decimal[index]);
                cell.title = reported ? String(row[index]) : modelTable ? 'Not reported' : 'Missing value';
                tr.append(cell);
            });
            fragment.append(tr);
        }
        if (result.rows.length === 0) {
            const tr = document.createElement('tr');
            const cell = document.createElement('td');
            cell.colSpan = Math.max(1, table.columns.length);
            cell.className = 'px-4 py-8 text-center text-slate-500';
            cell.textContent = state.query ? 'No matching rows.' : 'This table has no rows.';
            tr.append(cell);
            fragment.append(tr);
        }
        body.replaceChildren(fragment);
        const range = result.total ? `${result.start + 1}–${result.start + result.rows.length}` : '0';
        status.textContent = `${range} of ${result.total.toLocaleString()} ${state.query ? 'matching ' : ''}rows · page ${result.page + 1} of ${result.pages}`;
        const increase = container.querySelector('[data-precision-increase]');
        const decrease = container.querySelector('[data-precision-decrease]');
        if (increase) increase.disabled = state.precision >= 15;
        if (decrease) decrease.disabled = state.precision <= 0;
        previous.disabled = result.page === 0;
        next.disabled = result.page >= result.pages - 1;
        container.querySelectorAll('[data-table-sort]').forEach((button) => {
            const selected = Number(button.dataset.tableSort) === state.sortColumn;
            button.closest('th').setAttribute('aria-sort', selected ? (state.direction === 'asc' ? 'ascending' : 'descending') : 'none');
            const indicator = button.querySelector('[data-sort-indicator]');
            indicator?.querySelector('[data-sort-up]')?.setAttribute('opacity', selected && state.direction === 'desc' ? '0.2' : '1');
            indicator?.querySelector('[data-sort-down]')?.setAttribute('opacity', selected && state.direction === 'asc' ? '0.2' : '1');
        });
    }

    precision?.addEventListener('input', () => {
        if (!precision.validity.valid || !Number.isInteger(precision.valueAsNumber)) return;
        state.precision = precision.valueAsNumber;
        render();
        // Summaries elsewhere on the page follow the same decimal places
        container.closest('main')?.querySelectorAll('[data-model-summary-number]').forEach((cell) => {
            cell.textContent = displayValue(Number(cell.dataset.modelSummaryNumber), state.precision) + (cell.dataset.numberSuffix || '');
        });
    });
    container.querySelector('[data-precision-increase]')?.addEventListener('click', () => { precision.stepUp(); precision.dispatchEvent(new Event('input', { bubbles: true })); });
    container.querySelector('[data-precision-decrease]')?.addEventListener('click', () => { precision.stepDown(); precision.dispatchEvent(new Event('input', { bubbles: true })); });
    search.addEventListener('input', () => { state.query = search.value; state.page = 0; render(); });
    size.addEventListener('change', () => { state.pageSize = Number(size.value); state.page = 0; render(); });
    previous.addEventListener('click', () => { state.page--; render(); });
    next.addEventListener('click', () => { state.page++; render(); });
    exportButton?.addEventListener('click', () => {
        const rows = tablePage(table, { ...state, page: 0, pageSize: Math.max(1, table.rows.length) }).rows;
        const url = URL.createObjectURL(new Blob([tableCsv(table, rows)], { type: 'text/csv;charset=utf-8' }));
        const link = document.createElement('a');
        link.href = url;
        link.download = 'table-preview.csv';
        link.click();
        setTimeout(() => URL.revokeObjectURL(url), 1000);
    });
    container.querySelectorAll('[data-table-sort]').forEach((button) => button.addEventListener('click', () => {
        const index = Number(button.dataset.tableSort);
        state.direction = state.sortColumn === index && state.direction === 'asc' ? 'desc' : 'asc';
        state.sortColumn = index;
        state.page = 0;
        render();
    }));
    container.querySelectorAll('[data-table-control]').forEach((element) => { element.hidden = false; });
    render();
}

// Switches a forest plot between ratios and the model's own scale. phiz redraws when data-phiz changes.
function mountCoefficientPlot(section) {
    const specs = JSON.parse(section.querySelector('[data-coefficient-specs]').textContent);
    const chart = section.querySelector('[data-coefficient-chart]');
    section.querySelectorAll('[data-scale]').forEach((button) => button.addEventListener('click', () => {
        const scale = button.dataset.scale;
        chart.setAttribute('data-phiz', JSON.stringify(specs[scale]));
        section.querySelectorAll('[data-scale]').forEach((other) => other.setAttribute('aria-pressed', String(other === button)));
        section.querySelectorAll('[data-scale-note]').forEach((note) => { note.hidden = note.dataset.scaleNote !== scale; });
    }));
}

if (typeof document !== 'undefined') {
    document.querySelectorAll('[data-artifact-table]').forEach(mountTable);
    document.querySelectorAll('[data-coefficient-plot]').forEach((section) => {
        if (section.querySelector('[data-coefficient-specs]')) mountCoefficientPlot(section);
    });
}
