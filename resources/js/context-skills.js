import { toRaw } from 'vue';

// Skills a project's own choices call for. They start selected, stay
// removable, and an exclusion keeps a removed one off. They match the skill
// sets the resolver adds to Framework's blueprints (Resolver::derived): Git
// for any structure, the notebook format's skill for project structures.
const applied = new WeakMap();
const PROJECT_TYPES = ['project', 'project_sensitive', 'course', 'presentation'];

export function contextSkillKeys(form, catalog) {
    if (form.agents?.includes('none') || form.settings?.ai === false) return [];
    const format = form.language === 'stata' ? 'scripts' : (form.notebook_format ?? form.workflow);
    const structure = catalog.structures.find(item => item.key === form.structure);
    const project = PROJECT_TYPES.includes(structure?.type);
    return [
        ...(project && ['quarto', 'rmarkdown'].includes(format) ? ['quarto-documents'] : []),
        ...(project && format === 'jupyter' ? ['jupyter-notebooks'] : []),
        ...(form.settings?.git !== false ? ['git-workflow'] : []),
        ...(structure?.sensitive ? ['framework-sensitive-data'] : []),
    ];
}

// Selects the context skills for the form's choices. A skill the previous
// choices brought, which the current ones don't, is taken off again.
// `reset` starts over, as when a blueprint fills the form.
export function applyContextSkills(form, catalog, { reset = false } = {}) {
    const raw = toRaw(form);
    const wanted = contextSkillKeys(form, catalog);
    const handles = catalog.skills.filter(skill => skill.system && wanted.includes(skill.skill)
        && (!skill.language?.length || skill.language.includes(form.language))).map(skill => skill.key);
    const previous = reset ? [] : (applied.get(raw) ?? []);
    applied.set(raw, handles);
    const dropped = previous.filter(key => !handles.includes(key));
    const excluded = form.excluded_skills ?? [];
    const next = [...new Set([
        ...(form.skills ?? []).filter(key => !dropped.includes(key)),
        ...handles.filter(key => !excluded.includes(key)),
    ])];
    if (JSON.stringify(next) !== JSON.stringify(form.skills ?? [])) form.skills = next;
}
