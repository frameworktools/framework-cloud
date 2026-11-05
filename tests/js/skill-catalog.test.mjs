import test from 'node:test';
import assert from 'node:assert/strict';
import { filterSkills } from '../../resources/js/skill-catalog.js';
import { languageTab } from '../../resources/js/library.js';
const skills = [
    { id: 1, name: 'Managing packages', language: ['r', 'python'], is_system: true, tags: [{ key: 'framework', name: 'Framework' }, { key: 'packages', name: 'Packages' }] },
    { id: 2, name: 'Data import', summary: 'Read survey files', language: ['stata'], editable: true, tags: [{ key: 'framework', name: 'Framework' }] },
    { id: 3, name: 'Writing', language: null, tags: [{ key: 'prose', name: 'AI Prose' }] },
];
test('catalog preference takes priority over a stale URL, including Stata', () => {
    globalThis.window = { location: { search: '?language=r' } };
    assert.equal(languageTab('python'), 'python');
    assert.equal(languageTab('stata'), 'stata');
    assert.equal(languageTab('unknown'), 'r');
    delete globalThis.window;
});
test('filters combine language, ownership, search, and every selected tag', () => {
    assert.deepEqual(filterSkills(skills, { language: 'r', tags: ['framework', 'packages'], search: 'managing PACKAGES' }).map(s => s.id), [1]);
    assert.deepEqual(filterSkills(skills, { language: 'stata', source: 'mine', search: 'survey' }).map(s => s.id), [2]);
    assert.deepEqual(filterSkills(skills, { language: 'stata', tags: ['framework', 'packages'] }), []);
    assert.deepEqual(filterSkills(skills, { language: 'stata' }).map(s => s.id), [2, 3]);
});
