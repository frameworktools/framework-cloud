// Shared bits for the blueprint, structure, package set, and skill pages.

export const visibilityOptions = [
    { value: 'private', label: 'Private: only you' },
    { value: 'unlisted', label: 'Unlisted: anyone with the link' },
    { value: 'public', label: 'Public: listed for everyone' },
];

// "mine", "framework", or "community", for grouping library listings
export const group = (row) => (row.is_system ? 'framework' : row.editable ? 'mine' : 'community');

export const groups = [
    { key: 'framework', label: 'From Framework' },
    { key: 'mine', label: 'Yours' },
    { key: 'community', label: 'Shared by others' },
];

// A project's language decides which tool scaffolds it. Stata support covers
// scaffolding only. Python waits for the Python package.
export const languageOptions = [
    { value: 'r', label: 'R' },
    { value: 'python', label: 'Python' },
    { value: 'stata', label: 'Stata (scaffolding only)' },
];

// fw scaffolds only these project types for Stata
export const stataTypes = ['project', 'project_sensitive', 'bare'];

export const languageNames = { r: 'R', python: 'Python', stata: 'Stata' };
