import { test } from 'node:test';
import assert from 'node:assert/strict';
import { compareFolders, moveFolder } from '../../resources/js/structure-layout.js';

test('moving a subtree retains identity and rewrites mappings and starter files', () => {
    const result = moveFolder([{ path: 'data', original_path: 'data' }, { path: 'data/raw', original_path: 'data/raw' }, { path: 'database' }], { cache: 'data/raw' }, [{ path: 'data/raw/readme.md', content: 'kept' }, { path: 'database/schema.sql' }], 'data', 'research');
    assert.deepEqual(result.folders.map((f) => f.path), ['research', 'research/raw', 'database']);
    assert.equal(result.folders[1].original_path, 'data/raw');
    assert.equal(result.locations.cache, 'research/raw');
    assert.equal(result.files[0].path, 'research/raw/readme.md');
    assert.equal(result.files[1].path, 'database/schema.sql');
});

test('rejects collisions, traversal, and moving a folder into itself', () => {
    const folders = [{ path: 'data' }, { path: 'reports' }];
    for (const target of ['reports', '../data', 'data/raw', 'a//b', '/tmp']) assert.throws(() => moveFolder(folders, {}, [], 'data', target));
});

test('tree order keeps descendants next to their parents', () => {
    const paths = ['data-backup', 'data/raw', 'data', 'data/raw/2026'];
    assert.deepEqual(paths.map((path) => ({ path })).sort(compareFolders).map((f) => f.path), ['data', 'data/raw', 'data/raw/2026', 'data-backup']);
});
