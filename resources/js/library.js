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

// What languages a skill or set is for, in words; none listed means any
export const languageScope = (languages) => {
    if (!languages?.length) return 'Any language';
    if (languages.length === 1) return `${languageNames[languages[0]]} only`;
    return languages.map((l) => languageNames[l]).join(' and ');
};

// Whether something for these languages fits a project in one; none listed fits all
export const fitsLanguage = (languages, language) => !languages?.length || languages.includes(language);

// The R or Python tab a catalog shows: from the address, else the given default, kept in the address as it changes
export const languageTab = (fallback, choices = ['r', 'python']) => {
    const asked = typeof window === 'undefined' ? null : new URLSearchParams(window.location.search).get('language');
    return choices.includes(asked) ? asked : fallback;
};
export const rememberLanguage = (language) => {
    const url = new URL(window.location.href);
    url.searchParams.set('language', language);
    window.history.replaceState(window.history.state, '', url);
};
