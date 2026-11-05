import { displayValue, mountTable } from './artifact-reader.js';

const labels = { term: 'Term', estimate: 'Estimate', std_error: 'Std. error', statistic: 'Test statistic', p_value: 'p-value', conf_low: 'CI lower', conf_high: 'CI upper' };
const el = (tag, text, cls) => { const node = document.createElement(tag); if (text !== undefined && text !== null) node.textContent = String(text); if (cls) node.className = cls; return node; };
export function safeArtifactUrl(value) {
    if (typeof value !== 'string' || !value || /[\u0000-\u001f\\]/.test(value) || value.startsWith('//') || value !== value.trim() || value.split(/[?#]/)[0].split('/').includes('..')) return null;
    if (/^[a-z][a-z\d+.-]*:/i.test(value) && !/^https?:/i.test(value)) return null;
    return value;
}
const numberCell = (value, precision = 3) => { const node = el('td', displayValue(value, precision, typeof value === 'number' && !Number.isInteger(value)), 'fw-number'); if (typeof value === 'number') node.dataset.modelSummaryNumber = String(value); node.title = value === null ? 'Not reported' : String(value); return node; };
function tableView(table, index, model) {
    const section = el('section', null, 'fw-table'); section.dataset.artifactTable = ''; section.dataset.modelTable = String(model);
    const header = el('header'); const intro = el('div'); const id = `fw-table-${index}`;
    const title = el('h2', table.title || 'Table'); title.id = id; section.setAttribute('aria-labelledby', id);
    intro.append(title, el('p', `${table.total_rows ?? table.row_count ?? table.rows.length} rows × ${table.columns.length} columns`, 'fw-number fw-muted')); header.append(intro);
    const searchLabel = el('label'); searchLabel.append(el('span', 'Search table', 'fw-sr-only'));
    const search = el('input'); search.type = 'search'; search.placeholder = 'Search these rows'; search.dataset.tableSearch = ''; searchLabel.append(search); header.append(searchLabel);
    const scroll = el('div', null, 'fw-scroll'); const grid = el('table'); const thead = el('thead'); const head = el('tr');
    table.columns.forEach((column, i) => { const th = el('th'); th.scope = 'col'; if (column.type === 'number') th.className = 'fw-number';
        if (model) th.append(el('span', labels[column.name] ?? column.label ?? column.name));
        else { const button = el('button', column.label ?? column.name); button.type = 'button'; button.dataset.tableSort = String(i); button.setAttribute('aria-label', `Sort by ${column.name}`); th.setAttribute('aria-sort', 'none'); th.append(button, el('small', column.type, 'fw-muted')); }
        head.append(th);
    }); thead.append(head); grid.append(thead, el('tbody')); scroll.append(grid);
    const footer = el('footer'); const status = el('span', '', 'fw-number fw-muted'); status.dataset.tableStatus = ''; status.setAttribute('aria-live', 'polite'); footer.append(status);
    const controls = el('div', null, 'fw-controls'); const precisionLabel = el('label', 'Decimal places '); const precision = el('input'); precision.type = 'number'; precision.min = '0'; precision.max = '15'; precision.value = '3'; precision.dataset.tablePrecision = ''; precisionLabel.append(precision); controls.append(precisionLabel);
    for (const [caption, marker] of [['−', 'precisionDecrease'], ['+', 'precisionIncrease']]) { const button = el('button', caption); button.type = 'button'; button.dataset[marker] = ''; button.setAttribute('aria-label', caption === '+' ? 'Increase decimal places' : 'Decrease decimal places'); controls.append(button); }
    const csv = el('button', 'Export preview CSV'); csv.type = 'button'; csv.dataset.tableExport = ''; controls.append(csv);
    const sizeLabel = el('label', 'Rows per page '); const size = el('select'); size.dataset.tableSize = ''; for (const value of [25, 50, 100]) { const option = el('option', value); option.value = String(value); size.append(option); } sizeLabel.append(size); controls.append(sizeLabel);
    for (const [caption, marker] of [['Previous', 'tablePrevious'], ['Next', 'tableNext']]) { const button = el('button', caption); button.type = 'button'; button.dataset[marker] = ''; controls.append(button); }
    footer.append(controls); const data = el('script'); data.type = 'application/json'; data.dataset.tableValues = ''; data.textContent = JSON.stringify(table);
    section.append(header); if (table.truncated || table.total_rows > table.rows.length) section.append(el('p', 'Limited preview. Search, sorting, and CSV export use the displayed rows. Download the original for all data.', 'fw-notice'));
    section.append(scroll, footer, data);
    const summaries = table.columns.filter(column => column.summary && typeof column.summary === 'object');
    if (!model && summaries.length) {
        const details = el('details', null, 'fw-profiles'); details.append(el('summary', 'Column summaries'));
        for (const column of summaries) {
            const block = el('section'); block.append(el('h3', column.label ?? column.name));
            const stats = el('table'); for (const [name, value] of Object.entries(column.summary)) {
                if (value !== null && typeof value === 'object') continue;
                const row = el('tr'); row.append(el('th', name.replaceAll('_', ' ')), numberCell(value)); stats.append(row);
            } block.append(stats); details.append(block);
        } section.append(details);
    }
    mountTable(section);
    return section;
}
function metricView(metrics) {
    const section = el('section', null, 'fw-metrics'); section.append(el('h2', 'Model summary'));
    const table = el('table'); const header = el('tr'); header.append(el('th', 'Statistic'), el('th', 'Value')); const head = el('thead'); head.append(header); table.append(head);
    const metricLabels = { nobs:'Observations', df_resid:'Residual degrees of freedom', df_model:'Model degrees of freedom', aic:'AIC', bic:'BIC', rsquared:'R²', 'r.squared':'R²', rsquared_adj:'Adjusted R²', 'adj.r.squared':'Adjusted R²', sigma:'Residual standard deviation', llf:'Log likelihood', log_likelihood:'Log likelihood', confidence_level:'Confidence level', interval_method:'Interval method', vcov_type:'Standard errors', n_estimators:'Trees', max_depth:'Maximum depth', learning_rate:'Learning rate' };
    const group = name => ['nobs','df_resid','df_model','n_events'].includes(name) || name.startsWith('n_groups') ? 'Sample and degrees of freedom' : ['confidence_level','interval_method','vcov_type'].includes(name) ? 'Inference' : ['aic','bic','rsquared','r.squared','rsquared_adj','adj.r.squared','sigma','llf','log_likelihood','deviance','null.deviance','deviance_explained','converged'].includes(name) ? 'Model fit' : 'Additional statistics';
    const body = el('tbody');
    const groups = new Map(); for (const metric of metrics) { const key = group(metric.name); if (!groups.has(key)) groups.set(key, []); groups.get(key).push(metric); }
    for (const [name, values] of groups) {
        const heading = el('tr'); const cell = el('th', name, 'fw-metric-group'); cell.colSpan = 2; heading.append(cell); body.append(heading);
        for (const metric of values) {
            const row = el('tr'); const label = metric.label ?? metricLabels[metric.name] ?? metric.name.replaceAll('_',' ').replaceAll('.',' ');
            const value = numberCell(metric.value);
            if (metric.name === 'confidence_level' && typeof metric.value === 'number') { value.textContent = `${metric.value * 100}%`; delete value.dataset.modelSummaryNumber; }
            if (metric.name === 'interval_method') value.textContent = {'wald-t':'Wald t','wald-normal':'Wald normal'}[metric.value] ?? metric.value;
            row.append(el('th', label), value); body.append(row);
        }
    }
    table.append(body); section.append(table); return section;
}
function forestPlot(table) {
    const columns = table.columns.map(c => c.name); const ei = columns.indexOf('estimate'), li = columns.indexOf('conf_low'), hi = columns.indexOf('conf_high'), ti = columns.indexOf('term');
    if (ei < 0 || li < 0 || hi < 0) return null;
    const rows = table.rows.filter(row => !/^(\(intercept\)|intercept|const)$/i.test(String(row[ti] ?? '')) && [row[ei], row[li], row[hi]].every(x => typeof x === 'number' && Number.isFinite(x))).slice(0, 50);
    if (!rows.length) return null;
    const min = Math.min(0, ...rows.map(row => row[li])), max = Math.max(0, ...rows.map(row => row[hi])); const width = max - min || 1;
    const x = value => 165 + (value - min) / width * 470; const height = rows.length * 36 + 65;
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg'); svg.setAttribute('viewBox', `0 0 680 ${height}`); svg.setAttribute('role', 'img'); svg.setAttribute('aria-label', 'Coefficient estimates and supplied confidence intervals');
    const draw = (tag, attrs, text) => { const node = document.createElementNS(svg.namespaceURI, tag); for (const [key, value] of Object.entries(attrs)) node.setAttribute(key, value); if (text !== undefined) node.textContent = text; svg.append(node); return node; };
    draw('line', { x1:x(0), x2:x(0), y1:10, y2:height-40, stroke:'currentColor', 'stroke-dasharray':'3 3', opacity:'.4' });
    rows.forEach((row, i) => { const y = i * 36 + 25; draw('text', { x:150, y:y+4, 'text-anchor':'end', fill:'currentColor', 'font-size':12 }, String(row[ti] ?? i + 1).slice(0, 30)); draw('line', { x1:x(row[li]), x2:x(row[hi]), y1:y, y2:y, stroke:'#0284c7', 'stroke-width':2 }); const point = draw('circle', { cx:x(row[ei]), cy:y, r:4, fill:'#0284c7' }); const title = document.createElementNS(svg.namespaceURI, 'title'); title.textContent = `${row[ti]}: ${row[ei]} [${row[li]}, ${row[hi]}]`; point.append(title); });
    for (const value of [...new Set([min, 0, max])]) draw('text', { x:x(value), y:height-15, 'text-anchor':'middle', fill:'currentColor', 'font-size':11, 'font-family':'monospace' }, displayValue(value));
    const section = el('section', null, 'fw-plot'); section.append(el('h2', 'Coefficient estimates'), el('p', 'Points are estimates. Lines show the supplied confidence intervals. The intercept is omitted.', 'fw-muted'), svg); return section;
}
function testView(test) {
    const section = el('section', null, 'fw-metrics'); section.append(el('h2', test.method || 'Statistical test'));
    if (test.alternative) section.append(el('p', {'two.sided':'Two-sided alternative',less:'One-sided alternative, less than',greater:'One-sided alternative, greater than'}[test.alternative] ?? test.alternative, 'fw-muted'));
    const table = el('table'); const body = el('tbody');
    const add = (name, value) => { const row = el('tr'); row.append(el('th', name), numberCell(value)); body.append(row); };
    if (test.statistic) add(test.statistic.name || 'Test statistic', test.statistic.value);
    if (test.p_value !== undefined && test.p_value !== null) add('p-value', test.p_value);
    for (const [key, label] of [['estimates','Estimate'],['parameters','Parameter'],['null_value','Null value']]) for (const entry of test[key] ?? []) add(`${label}: ${entry.name}`, entry.value);
    if (test.conf_int) { add('Confidence level', `${test.conf_int.level * 100}%`); add('CI lower', test.conf_int.low ?? '−∞'); add('CI upper', test.conf_int.high ?? '∞'); }
    table.append(body); section.append(table); return section;
}
export function mountFrameworkReader(container, payload) {
    container.classList.add('fw-reader'); container.replaceChildren();
    const manifest = payload.manifest ?? {}; const data = payload.representation;
    if (data?.schema === 'framework.artifact/v1' || data?.tables) {
        if (data.source) { const info = el('details', null, 'fw-source'); info.append(el('summary', 'Source details'), el('p', [data.source.language, ...(data.source.class ?? [])].join(' · '))); container.append(info); }
        if (data.kind === 'test' && data.test) container.append(testView(data.test));
        if (data.formula) { const section = el('section'); section.append(el('h2', 'Model formula'), el('pre', data.formula)); container.append(section); }
        const tables = Array.isArray(data.tables) ? data.tables : [];
        tables.forEach((table, i) => { if (!Array.isArray(table.columns) || !Array.isArray(table.rows)) return; const model = table.role === 'coefficients' || (data.kind === 'model' && /coefficient/i.test(table.title)); container.append(tableView(table, i, model)); if (model) { const plot = forestPlot(table); if (plot) container.append(plot); } });
        if (Array.isArray(data.metrics) && data.metrics.length) { const metrics = metricView(data.metrics); metrics.querySelector('h2').textContent = data.kind === 'model' ? 'Model summary' : 'Summary'; container.append(metrics); }
        if (data.truncated) container.append(el('p', 'Limited preview. Download the original representation for complete values.', 'fw-notice'));
        return;
    }
    const original = safeArtifactUrl(manifest.original); if (!original) { container.append(el('p', 'The original file is unavailable.')); return; }
    if (['image', 'figure'].includes(manifest.kind)) { const figure = el('figure'); const img = el('img'); img.src = original; img.alt = manifest.title || 'Published figure'; figure.append(img); container.append(figure); }
    else if (['html', 'pdf', 'notebook', 'report', 'book'].includes(manifest.kind)) { const frame = el('iframe'); frame.src = original; frame.title = manifest.title || 'Published document'; frame.setAttribute('sandbox', 'allow-scripts allow-downloads'); frame.referrerPolicy = 'no-referrer'; container.append(frame); }
    else { const link = el('a', 'Download original'); link.href = original; link.download = ''; container.append(link); }
}
if (typeof document !== 'undefined') {
    document.querySelectorAll('[data-framework-reader]').forEach(container => {
        const data = container.querySelector('[data-framework-payload]');
        if (data) { try { mountFrameworkReader(container, JSON.parse(data.textContent)); } catch { container.replaceChildren(el('p', 'This artifact cannot be displayed. Download the original file.')); } }
    });
}
