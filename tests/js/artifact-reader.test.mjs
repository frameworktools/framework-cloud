import test from 'node:test';
import assert from 'node:assert/strict';
import { compareValues, displayValue, tablePage, tableCsv } from '../../resources/js/artifact-reader.js';

const table = {
    columns: [{ name: 'term', type: 'string' }, { name: 'estimate', type: 'number' }],
    rows: [['ten', 10], ['two', 2], ['missing', null], ['negative', -3], ['same estimate', 2]],
};

test('numeric sorting is numeric, stable, and leaves missing values last in both directions', () => {
    assert.deepEqual(tablePage(table, { sortColumn: 1 }).rows.map(row => row[0]), ['negative', 'two', 'same estimate', 'ten', 'missing']);
    assert.deepEqual(tablePage(table, { sortColumn: 1, direction: 'desc' }).rows.map(row => row[0]), ['ten', 'two', 'same estimate', 'negative', 'missing']);
    assert.equal(table.rows[0][0], 'ten');
});

test('search applies across cells before sorting and pagination', () => {
    const result = tablePage(table, { query: 'T', sortColumn: 1, pageSize: 2, page: 1 });
    assert.equal(result.total, 4);
    assert.equal(result.pages, 2);
    assert.deepEqual(result.rows, [['same estimate', 2], ['ten', 10]]);
    assert.equal(tablePage(table, { query: 'does not exist', page: 200 }).page, 0);
});

test('missing values and booleans are readable and searchable', () => {
    assert.equal(displayValue(null), 'NA');
    assert.equal(displayValue(false), 'FALSE');
    assert.equal(displayValue(35, 3, true), '35.000');
    assert.equal(displayValue(35, 3), '35');
    assert.equal(tablePage(table, { query: 'missing' }).rows[0][1], null);
    assert.ok(compareValues(false, true, 'boolean') < 0);
});

test('datetime sorting respects timezone offsets and strings retain their identity', () => {
    assert.ok(compareValues('2026-01-01T01:00:00+02:00', '2026-01-01T00:00:00Z', 'datetime') < 0);
    assert.equal(displayValue('0012'), '0012');
    assert.equal(displayValue('<script>alert(1)</script>'), '<script>alert(1)</script>');
});

test('large integer CSV values sort without losing precision', () => {
    assert.ok(compareValues('9007199254740993', '9007199254740992', 'number') > 0);
    assert.ok(compareValues('-9007199254740993', '-9007199254740992', 'number') < 0);
});

test('preview CSV preserves exact values and escapes quotes, newlines, and commas', () => {
    const values = { columns: [{ name: 'identifier' }, { name: 'note' }], rows: [['9007199254740993', 'a,"quoted"\nvalue'], [null, false]] };
    assert.equal(tableCsv(values), '"identifier","note"\r\n"9007199254740993","a,""quoted""\nvalue"\r\n"","false"');
});


test('display rounds results while preserving exact strings, integers, and CSV values', () => {
    assert.equal(displayValue(37.2272701164472), '37.227');
    assert.equal(displayValue(-0.031772946982161), '-0.032');
    assert.equal(displayValue(0.0000001234567), '0.000');
    assert.equal(displayValue(32), '32');
    assert.equal(displayValue(1.6), '1.600');
    assert.equal(displayValue(37.2272701164472, 5), '37.22727');
    assert.equal(displayValue(37.2272701164472, 0), '37');
    assert.equal(displayValue(-0.0000001), '0.000');
    assert.equal(displayValue('9223372036854775807'), '9223372036854775807');
    assert.equal(displayValue(37.2272701164472, 'full'), '37.2272701164472');
    assert.ok(tableCsv({columns:[{name:'estimate'}],rows:[[37.2272701164472]]}).includes('37.2272701164472'));
});
