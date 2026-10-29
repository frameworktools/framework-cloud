// Shared bits for the blueprint, facet, and skill library pages.

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
