import { fitsLanguage, group } from './library.js';

export const filterSkills = (skills, { language, source = '', search = '', tags = [] }) => {
    const terms = search.trim().toLocaleLowerCase().split(/\s+/).filter(Boolean);
    return skills.filter(skill => {
        const haystack = [skill.name, skill.summary, skill.descriptions?.[language] ?? skill.description,
            ...(skill.tags ?? []).map(tag => tag.name)].filter(Boolean).join(' ').toLocaleLowerCase();
        return fitsLanguage(skill.language, language)
            && (!source || group(skill) === source)
            && tags.every(key => skill.tags?.some(tag => tag.key === key))
            && terms.every(term => haystack.includes(term));
    });
};
