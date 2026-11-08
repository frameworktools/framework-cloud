// Shared bits for the blueprint, structure, package set, and skill pages.

export const visibilityOptions = [
    { value: 'private', label: 'Private, only you' },
    { value: 'unlisted', label: 'Anyone with the link' },
    { value: 'public', label: 'Public, listed for everyone' },
];

// "mine", "framework", or "community", for grouping library listings
export const group = (row) => (row.is_system ? 'framework' : row.editable ? 'mine' : 'community');

export const groups = [
    { key: 'mine', label: 'Yours' },
    { key: 'framework', label: 'From Framework' },
    { key: 'community', label: 'Shared by others' },
];

// A project's language decides which tool scaffolds it: the R package, the
// Python package, or fw for Stata. Stata projects use the framework Stata package.
export const languageOptions = [
    { value: 'r', label: 'R' },
    { value: 'python', label: 'Python' },
    { value: 'stata', label: 'Stata' },
];

// fw scaffolds only these project types for Stata
export const stataTypes = ['project', 'project_sensitive', 'bare'];
// App structures that only one language's package can build
export const languageTypes = { shiny: 'r', plumber: 'r', shiny_python: 'python', fastapi: 'python' };
export const typeFits = (type, language) => (language !== 'stata' || stataTypes.includes(type)) && (!languageTypes[type] || languageTypes[type] === language);

export const languageNames = { r: 'R', python: 'Python', stata: 'Stata' };

// What languages a skill or set is for, in words; none listed means any
export const languageScope = (languages) => {
    if (!languages?.length) return 'Any language';
    if (languages.length === 1) return `${languageNames[languages[0]]} only`;
    return languages.map((l) => languageNames[l]).join(' and ');
};

// Whether something for these languages fits a project in one; none listed fits all
export const fitsLanguage = (languages, language) => !languages?.length || languages.includes(language);

// Each catalog visit starts at the saved preference, including Stata.
export const languageTab = (preferred, choices = ['r', 'python', 'stata']) => choices.includes(preferred) ? preferred : choices[0];
export const rememberLanguage = (language) => {
    const url = new URL(window.location.href);
    url.searchParams.set('language', language);
    window.history.replaceState(window.history.state, '', url);
};
