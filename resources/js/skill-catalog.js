import { fitsLanguage, group } from './library.js';

export const filterSkills = (skills, { language, source = '', search = '', tags = [], category = '' }) => {
    const terms = search.trim().toLocaleLowerCase().split(/\s+/).filter(Boolean);
    return skills.filter(skill => {
        const haystack = [skill.name, skill.summary, skill.descriptions?.[language] ?? skill.description,
            ...(skill.tags ?? []).map(tag => tag.name)].filter(Boolean).join(' ').toLocaleLowerCase();
        return fitsLanguage(skill.language, language)
            && (!source || group(skill) === source)
            && (!category || skill.tags?.some(tag => tag.key === category))
            && tags.every(key => skill.tags?.some(tag => tag.key === key))
            && terms.every(term => haystack.includes(term));
    });
};

export const categoryCounts = (items, categories, matches) => [
    { key: '', name: 'All', count: items.length },
    ...categories.map(category => ({ ...category, count: items.filter(item => matches(item, category.key)).length })),
];
