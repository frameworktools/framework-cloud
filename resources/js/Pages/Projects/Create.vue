<script setup>
import { ChevronRightIcon } from '@heroicons/vue/20/solid';
import { computed, ref, watch } from 'vue';
import { applyContextSkills } from '@/context-skills.js';
import { router, useForm, usePage } from '@inertiajs/vue3';
import SectionLayout from '@/Layouts/SectionLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import UiPanel from '@/Components/UiPanel.vue';
import UiSeparator from '@/Components/UiSeparator.vue';
import UiButton from '@/Components/UiButton.vue';
import UiInput from '@/Components/UiInput.vue';
import UiLink from '@/Components/UiLink.vue';
import UiSelect from '@/Components/UiSelect.vue';
import UiSwitch from '@/Components/UiSwitch.vue';
import UiTextarea from '@/Components/UiTextarea.vue';
import UiCallout from '@/Components/UiCallout.vue';
import UiRadioCards from '@/Components/UiRadioCards.vue';
import ChoicesEditor, { errorSection, PAGE_SECTIONS, SECTIONS } from '@/Components/ChoicesEditor.vue';
import ProjectTreeView from '@/Components/ProjectTreeView.vue';
import UiModal from '@/Components/UiModal.vue';
import { agentNames, agentSummary, fromChoices, NAMED, toChoices } from '@/agents.js';
import { editorOptions, editorsOf } from '@/project-settings.js';
import { fitsLanguage, group, groups, languageOptions, typeFits } from '@/library.js';
import { connectionLanguages, copyConnections, variablesOnly } from '@/connections.js';

const props = defineProps({
    blueprints: Array, catalog: Object, defaults: Object, selected: Number, start: Object,
    stage: String, tab: String, github: Object, downloads: Boolean, githubUnavailable: Object,
    ggplotThemes: Array, quartoChoices: Object,
    // An existing project to edit: its id, slug, name, description, language, blueprint_id, and stored choices
    editing: { type: Object, default: null },
});
const editing = props.editing;

const page = usePage();
const githubReady = computed(() => props.github.enabled && props.github.login && props.github.owners?.length);

// The choice tabs, as the blueprint editor has them. Language is chosen at the start.
const sections = SECTIONS.filter((section) => !['name', 'basics', 'language'].includes(section.key));
// Sections this page draws itself; the shared editor draws the rest
const editorDraws = (key) => !PAGE_SECTIONS.includes(key);
// Scripts have no Quarto documents, so no Quarto tab
const tabs = computed(() => [{ key: 'basics', label: 'Basics' }, ...sections.filter((section) => section.key !== 'quarto' || form.workflow !== 'scripts')]);

// Blueprints and languages
const allLanguages = ['r', 'python', 'stata'];
const blueprintLanguages = (b) => b?.choices?.languages?.length ? b.choices.languages : allLanguages;
const fits = (b, language) => !!b && blueprintLanguages(b).includes(language);
const byHandle = (handle) => props.blueprints.find((b) => b.handle === handle);
// Your default blueprint for the language, the standard project, or the first that fits
const defaultFor = (language) => [byHandle(props.defaults?.blueprints?.[language]), byHandle(props.defaults?.blueprint), byHandle('framework/project'), ...props.blueprints]
    .find((b) => fits(b, language));
const languages = computed(() => languageOptions.filter((o) => props.blueprints.some((b) => fits(b, o.value))));
const linked = props.blueprints.find((b) => b.id === props.selected);
const initialLanguage = [editing?.language, props.start?.language, linked && blueprintLanguages(linked).length === 1 ? blueprintLanguages(linked)[0] : null, props.defaults?.language, 'r', ...allLanguages]
    .find((l) => l && props.blueprints.some((b) => fits(b, l)));
const initialBlueprint = fits(linked, initialLanguage) ? linked : defaultFor(initialLanguage);
// Changes the project kept from its blueprint, sent again when it is saved
const startCustomized = ref(!!editing?.choices?.customized);

const form = useForm({
    name: editing?.name ?? '',
    description: editing?.description ?? '',
    blueprint_id: initialBlueprint?.id,
    language: initialLanguage,
    workflow: '',
    notebook_format: null,
    agents: ['other'],
    structure: null,
    tools: {},
    package_sets: [],
    package_overrides: {},
    package_auto_attach: {},
    // Packages from no package set, from the blueprint, the Packages tab, or push_all()
    package_definitions: {},
    skill_sets: [],
    skills: [],
    excluded_skills: [],
    settings: { git: true, hooks: [], ai: true },
    scaffold: {},
    quarto: { html: {}, revealjs: {} },
    ide: 'vscode',
    publish_destination: '',
    connections: variablesOnly(null),
    // Notebook, script, and slide templates; empty uses Framework's
    templates: { notebook: '', script: '', slides: '' },
    extra_yaml: '',
    // A destination passed in the link, when it's available here; otherwise local setup
    destination: ({ download: props.downloads, github: props.github.enabled, local: true })[props.start?.destination]
        ? props.start.destination
        : 'local',
    github: { owner: props.github.owners?.[0]?.login ?? null, name: '', private: true, nameEdited: false },
    save_blueprint: false,
    blueprint_name: '',
    blueprintNameEdited: false,
});
const blueprint = computed(() => props.blueprints.find((b) => b.id === form.blueprint_id));
const stata = computed(() => form.language === 'stata');

// Filling the choices from a blueprint, as the blueprint editor opens it, for the chosen language
const defaultFormat = (workflow, language, rmarkdown) => (language === 'stata' || workflow === 'scripts' ? null : workflow === 'jupyter' ? 'jupyter' : rmarkdown && language === 'r' ? 'rmarkdown' : 'quarto');
const choiceKey = () => JSON.stringify([form.workflow, form.notebook_format, form.agents, form.structure, form.tools, form.package_sets, form.package_overrides, form.package_auto_attach, form.skill_sets, form.skills, form.excluded_skills, form.settings.git, form.settings.hooks, form.scaffold, form.quarto, form.ide, form.publish_destination, form.connections, form.templates, form.package_definitions, form.extra_yaml]);
const filled = ref(null);
const baseline = ref({});
const contextSkills = ref(false);
const choiceFields = ['workflow', 'notebook_format', 'agents', 'structure', 'tools', 'package_sets', 'package_overrides', 'package_auto_attach', 'package_definitions', 'skill_sets', 'skills', 'excluded_skills', 'settings', 'scaffold', 'quarto', 'ide', 'publish_destination', 'connections', 'templates', 'extra_yaml'];
const snapshot = () => Object.fromEntries(choiceFields.map(key => [key, form[key]]));
const fill = (b) => {
    const c = b?.choices ?? {};
    const language = form.language;
    // As creating it would choose: the link's workflow, yours for the language, the blueprint's, or the first it takes
    const allowed = c.workflows?.length ? c.workflows : ['quarto', 'jupyter', 'scripts'];
    const workflow = language === 'stata'
        ? 'scripts'
        : [props.start?.workflow, props.defaults?.workflows?.[language], c.default_workflow?.[language], ...allowed.filter((w) => w !== 'scripts')]
            .find((w) => w && allowed.includes(w)) ?? 'quarto';
    const sameLanguage = (c.language ?? b?.language) === language;
    const keep = (list, items) => (list ?? []).filter((key) => items.some((item) => item.key === key && fitsLanguage(item.language, language)));
    const structure = props.catalog.structures.find((s) => s.key === c.structure);

    form.workflow = workflow;
    form.notebook_format = defaultFormat(workflow, language, c.notebook_format ? c.notebook_format === 'rmarkdown' : props.defaults?.rmarkdown);
    form.agents = [...(c.agents ?? ['other'])];
    form.structure = structure && typeFits(structure.type, language) ? structure.key : 'framework/project';
    form.tools = language === 'stata' ? {} : Object.fromEntries(props.catalog.tools
        .filter((t) => t.key !== 'targets' && fitsLanguage(t.language, language))
        .map((t) => [t.key, !!c.tools?.[t.key]]));
    form.package_sets = keep(c.package_sets, props.catalog.package_sets);
    form.package_overrides = sameLanguage ? { ...(c.package_overrides ?? {}) } : {};
    form.package_auto_attach = sameLanguage && language === 'r' ? { ...(c.package_auto_attach ?? {}) } : {};
    form.package_definitions = sameLanguage && language !== 'stata' ? { ...(c.package_definitions ?? {}) } : {};
    form.skill_sets = keep(c.skill_sets, props.catalog.skill_sets);
    form.skills = keep(c.skills, props.catalog.skills);
    form.excluded_skills = keep(c.excluded_skills, props.catalog.skills);
    form.settings = { git: c.settings?.git ?? true, hooks: [...(c.settings?.hooks ?? [])], ai: !form.agents.includes('none') };
    form.scaffold = { ...(c.scaffold ?? {}) };
    form.quarto = { html: { ...(c.quarto?.html ?? {}) }, revealjs: { ...(c.quarto?.revealjs ?? {}) } };
    // RStudio's .Rproj is for R projects
    form.ide = language !== 'r' && ['rstudio', 'both'].includes(c.ide) ? 'vscode' : (c.ide ?? 'vscode');
    form.publish_destination = c.publish_destination ?? '';
    // Stata reads no connections, but its .env takes variables
    form.connections = connectionLanguages.includes(language) ? copyConnections(c.connections) : variablesOnly(c.connections);
    form.templates = { notebook: '', script: '', slides: '', ...(c.templates ?? {}) };
    form.extra_yaml = c.extra_yaml ?? '';
    const named = JSON.stringify(form.skills);
    applyContextSkills(form, props.catalog, { reset: true });
    // Framework's blueprints add these skills on the server. Others are sent as choices.
    contextSkills.value = !b?.is_system && JSON.stringify(form.skills) !== named;
    baseline.value = JSON.parse(JSON.stringify(snapshot()));
    form.save_blueprint = false;
    filled.value = choiceKey();
};
fill(blueprint.value);

// Editing: the project's own choices over its blueprint's, as it last saved them
const fillStored = (c) => {
    const language = form.language;
    if (c.workflow) form.workflow = language === 'stata' ? 'scripts' : c.workflow;
    if ('notebook_format' in c) form.notebook_format = c.notebook_format ?? defaultFormat(form.workflow, language, false);
    // Customized, the stored packages are the project's; otherwise pushed ones go over the blueprint's
    form.package_definitions = c.customized ? { ...(c.package_definitions ?? {}) } : { ...form.package_definitions, ...(c.package_definitions ?? {}) };
    if (!c.customized) {
        fillChanges(c);
        return;
    }
    if (c.structure) form.structure = c.structure;
    for (const [key, on] of Object.entries(c.tools ?? {})) form.tools[key] = !!on;
    for (const key of ['package_sets', 'skill_sets', 'skills', 'excluded_skills']) form[key] = [...(c[key] ?? [])];
    form.package_overrides = { ...(c.package_overrides ?? {}) };
    form.package_auto_attach = { ...(c.package_auto_attach ?? {}) };
    const ai = c.settings?.ai;
    if (ai && typeof ai === 'object') {
        form.agents = toChoices(ai.enabled !== false, Object.keys(ai.assistants ?? {}).filter((a) => ai.assistants[a]), false);
    } else if (ai === false) {
        form.agents = ['none'];
    }
    form.settings = { git: c.settings?.git ?? true, hooks: [...(c.settings?.hooks ?? [])], ai: !form.agents.includes('none') };
    if (c.scaffold) form.scaffold = { ...c.scaffold };
    if (c.quarto) form.quarto = { html: { ...(c.quarto.html ?? {}) }, revealjs: { ...(c.quarto.revealjs ?? {}) } };
    if (c.ide) form.ide = c.ide;
    form.publish_destination = c.publish_destination ?? '';
    if (c.connections) form.connections = copyConnections(c.connections);
    if (c.templates) form.templates = { notebook: '', script: '', slides: '', ...c.templates };
    form.extra_yaml = c.extra_yaml ?? '';
};
// A project made over the API keeps its changes to the blueprint: shown over the blueprint's
// choices, and saved from then on as the project's own
const blueprintChanges = ['structure', 'tools', 'package_sets', 'package_overrides', 'package_auto_attach', 'skill_sets', 'skills', 'excluded_skills', 'settings'];
function fillChanges(c) {
    if (!blueprintChanges.some((key) => key in c)) return;
    const union = (list, more) => [...new Set([...list, ...(more ?? [])])];
    if (c.structure) form.structure = c.structure;
    for (const [key, on] of Object.entries(c.tools ?? {})) form.tools[key] = !!on;
    form.package_sets = union(form.package_sets, c.package_sets);
    form.skill_sets = union(form.skill_sets, c.skill_sets);
    form.excluded_skills = union(form.excluded_skills, c.excluded_skills);
    form.skills = union(form.skills, c.skills).filter((key) => !form.excluded_skills.includes(key));
    form.package_overrides = { ...form.package_overrides, ...(c.package_overrides ?? {}) };
    form.package_auto_attach = { ...form.package_auto_attach, ...(c.package_auto_attach ?? {}) };
    const s = c.settings ?? {};
    if ('ai' in s) form.agents = s.ai === false || s.ai?.enabled === false ? ['none'] : typeof s.ai === 'object' ? toChoices(true, Object.keys(s.ai.assistants ?? {}).filter((a) => s.ai.assistants[a]), false) : form.agents;
    form.settings = { git: s.git ?? form.settings.git, hooks: [...(s.hooks ?? form.settings.hooks)], ai: !form.agents.includes('none') };
    startCustomized.value = true;
}
if (editing?.choices) {
    fillStored(editing.choices);
    baseline.value = JSON.parse(JSON.stringify(snapshot()));
    filled.value = choiceKey();
}
// Whether saving changes the setup, or only the name and description
const setupKey = () => JSON.stringify([form.blueprint_id, form.language, choiceKey()]);
const initialSetup = setupKey();
const setupChanged = computed(() => !!editing && setupKey() !== initialSetup);
watch(() => form.agents, (agents) => { form.settings.ai = !agents.includes('none'); });
// Changes made to the blueprint's choices, which another blueprint or language would replace
const edited = computed(() => filled.value !== null && choiceKey() !== filled.value);

// Choosing a blueprint or language fills the choices again. Edits are only replaced after a warning.
const pending = ref(null);
const confirming = computed({ get: () => pending.value !== null, set: (open) => { if (!open) pending.value = null; } });
const apply = ({ language, id }) => {
    startCustomized.value = false;
    form.language = language;
    form.blueprint_id = id;
    fill(props.blueprints.find((b) => b.id === id));
    pending.value = null;
};
const change = (next) => {
    if (next.language === form.language && next.id === form.blueprint_id) return;
    if (edited.value) pending.value = next;
    else apply(next);
};
const chooseBlueprint = (id) => change({ language: form.language, id });
const chooseLanguage = (language) => change({ language, id: fits(blueprint.value, language) ? form.blueprint_id : defaultFor(language)?.id });
const pendingBlueprint = computed(() => props.blueprints.find((b) => b.id === pending.value?.id));

// Blueprints for the chosen language, in groups
const blueprintGroups = computed(() => groups
    .map((g) => ({ ...g, rows: props.blueprints.filter((b) => group(b) === g.key && fits(b, form.language)) }))
    .filter((g) => g.rows.length));
// Groups you've hidden, remembered in this browser. A hidden group still shows the chosen blueprint.
const hiddenKey = 'fw.newProject.hiddenGroups';
const hidden = ref(JSON.parse(localStorage.getItem(hiddenKey) ?? '[]'));
const toggleGroup = (key) => {
    hidden.value = hidden.value.includes(key) ? hidden.value.filter((k) => k !== key) : [...hidden.value, key];
    localStorage.setItem(hiddenKey, JSON.stringify(hidden.value));
};
const shownRows = (g) => (hidden.value.includes(g.key) ? g.rows.filter((b) => b.id === form.blueprint_id) : g.rows);

// Notebook format, environment, and Git, as the blueprint editor shows them
const notebookOptions = computed(() => [
    { value: 'quarto', label: 'Quarto', description: 'Documents in .qmd files.' },
    ...(form.language === 'r' ? [{ value: 'rmarkdown', label: 'R Markdown', description: 'Documents in .Rmd files, rendered with Quarto.' }] : []),
    { value: 'jupyter', label: 'Jupyter', description: `Notebooks in .ipynb files with ${form.language === 'r' ? 'IRkernel' : 'ipykernel'}.` },
]);
// Scripts is a workflow with no notebook format, and leaves out Quarto's manuscript and slides
const notebookFormat = computed({
    get: () => (form.workflow === 'scripts' ? 'scripts' : form.notebook_format ?? ''),
    set: (format) => {
        form.workflow = format === 'scripts' ? 'scripts' : format === 'jupyter' ? 'jupyter' : 'quarto';
        form.notebook_format = format === 'scripts' ? null : format;
        if (format === 'scripts') for (const key of ['quarto_manuscript', 'quarto_slides']) form.tools[key] = false;
    },
});
const notebookTools = computed(() => props.catalog.tools.filter((tool) => ['quarto_manuscript', 'quarto_slides'].includes(tool.key)));
// Without notebooks a project has plain scripts; turning them back on restores the last format
const lastFormat = ref(null);
watch(() => form.notebook_format, (format) => { if (format) lastFormat.value = format; }, { immediate: true });
const useNotebooks = computed({
    get: () => form.workflow !== 'scripts',
    set: (on) => { notebookFormat.value = on ? (lastFormat.value ?? (form.language === 'python' ? 'jupyter' : 'quarto')) : 'scripts'; },
});
const environmentTool = computed(() => props.catalog.tools.find((tool) => tool.key === ({ r: 'renv', python: 'uv' })[form.language]));
const hooks = [
    { key: 'data_security', label: 'Secret detection', description: 'Stops commits that contain passwords, keys, or other secrets.' },
    { key: 'check_sensitive_dirs', label: 'Private folder warnings', description: 'Warns when a private folder is missing from .gitignore.' },
    { key: 'check_notebook_outputs', label: 'Notebook output checks', description: 'Stops commits of Jupyter notebooks that still contain outputs.' },
];
const toggleHook = (key, on) => { form.settings.hooks = on ? [...new Set([...form.settings.hooks, key])] : form.settings.hooks.filter((hook) => hook !== key); };

// What the form would create, resolved by the server exactly as creating it would:
// the blueprint as it is, or, once its choices change, exactly the choices shown
const structurePiece = computed(() => props.catalog.structures.find((s) => s.key === form.structure));
const agentSettings = (choices) => {
    const { enabled, agents } = fromChoices(choices);
    return { enabled, assistants: Object.fromEntries(NAMED.map((a) => [a, agents.includes(a)])) };
};
const requiredTools = computed(() => Object.fromEntries(Object.entries(resolved.value?.tools ?? {}).filter(([, t]) => t.required).map(([key, t]) => [key, t.required])));
// Only tools for the project's language, and none turned off that the workflow needs.
// The workflows' own requirements (as seeded) apply before the server has answered for a new one.
const workflowRequires = { quarto: ['quarto'], jupyter: ['jupyter', 'quarto'] };
const toolChoices = (data) => (data.language === 'stata' ? {} : Object.fromEntries(Object.entries(data.tools).filter(([key, on]) => {
    const tool = props.catalog.tools.find((t) => t.key === key);
    return fitsLanguage(tool?.language, data.language) && (on || (!requiredTools.value[key] && !workflowRequires[data.workflow]?.includes(key)));
})));
// Scaffold, Quarto, editor files, and publishing, for the languages that read them
const settingChoices = (data) => ({
    ...(data.language === 'stata' ? { connections: variablesOnly(data.connections) } : { scaffold: data.scaffold, quarto: data.quarto, ide: data.ide, connections: data.connections, templates: data.templates }),
    publish_destination: data.publish_destination,
});
const choicePayload = (data) => ({
    blueprint_id: data.blueprint_id,
    language: data.language,
    workflow: data.workflow || undefined,
    name: data.name || undefined,
    // Another language's packages don't carry over
    ...(editing && data.language === editing.language && Object.keys(data.package_definitions ?? {}).length ? { package_definitions: data.package_definitions } : {}),
    ...(edited.value || contextSkills.value || startCustomized.value ? {
        customized: true,
        structure: data.structure,
        notebook_format: data.notebook_format || undefined,
        tools: toolChoices(data),
        package_sets: data.package_sets,
        package_overrides: data.package_overrides,
        package_auto_attach: data.package_auto_attach,
        package_definitions: data.language === 'stata' ? {} : data.package_definitions,
        skill_sets: data.skill_sets,
        skills: data.skills,
        excluded_skills: data.excluded_skills,
        settings: { sensitive: !!structurePiece.value?.sensitive, git: data.settings.git, hooks: data.settings.hooks, ai: agentSettings(data.agents) },
        extra_yaml: data.extra_yaml || undefined,
        ...settingChoices(data),
    } : {}),
});
const resolved = ref(null);
const resolvedFor = ref(null);
let request = 0;
let timer = null;
const payloadKey = computed(() => JSON.stringify(choicePayload(form.data())));
const resolve = async () => {
    const id = ++request;
    const key = payloadKey.value;
    const response = await fetch('/projects/preview', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': page.props.csrf, 'X-Requested-With': 'XMLHttpRequest' },
        body: key,
    }).catch(() => null);
    const data = await response?.json().catch(() => null);
    if (id !== request) return;
    resolved.value = response?.ok ? data : { problem: data?.problem ?? data?.message ?? "The preview couldn't be loaded." };
    resolvedFor.value = key;
};
watch(payloadKey, () => {
    clearTimeout(timer);
    timer = setTimeout(resolve, 250);
}, { immediate: true });
// A result for other choices than the form's is never shown as current, so nothing stale contradicts the form
const current = computed(() => (resolvedFor.value === payloadKey.value ? resolved.value : null));
const shown = computed(() => current.value ?? resolved.value);
const pieceName = (list, key) => props.catalog[list].find((p) => p.key === key)?.name ?? key;
const sensitive = computed(() => !!structurePiece.value?.sensitive || !!shown.value?.settings?.sensitive || (!edited.value && !!blueprint.value?.sensitive));

// Destination
const slugify = (value) => value.trim().toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
const slug = computed(() => slugify(form.name));
const repoName = computed(() => (form.github.nameEdited ? form.github.name : slug.value));
const editRepoName = (value) => { form.github.name = value; form.github.nameEdited = true; };
const ownerOptions = computed(() => (props.github.owners ?? []).map((o) => ({ value: o.login, label: o.login })));
watch(sensitive, (value) => { if (value) form.github.private = true; });
const onGitHub = computed(() => form.destination === 'github');
// Why GitHub can't set up a project in this language here, if it can't
const githubBlocked = computed(() => props.githubUnavailable?.[form.language] ?? null);
watch(githubBlocked, (reason) => { if (reason && onGitHub.value) form.destination = 'local'; });
// Local setup first, always. Then GitHub before the ZIP when GitHub is ready to use, and after it when not.
const destinations = computed(() => {
    const local = {
        key: 'local',
        title: 'Local setup',
        text: form.language === 'stata'
            ? 'In a terminal, run fw login, then fw new with the project name. Framework sets up Git.'
            : form.language === 'python'
                ? 'Framework issues a project token. Pass it to framework.new(key=...) in Python, or to fw new in a terminal. Framework sets up Git.'
                : 'Framework issues a project token. Pass it to framework::setup() in R, or to fw new in a terminal. Framework sets up Git.',
    };
    const download = props.downloads ? [{
        key: 'download',
        title: 'ZIP download',
        text: 'Framework builds the project and your browser downloads it. Unzip it and open the folder. You need no Git and no Framework packages.',
    }] : [];
    const github = props.github.enabled ? [{
        key: 'github',
        title: 'GitHub repository',
        text: githubBlocked.value ?? 'Framework creates a repository on your GitHub account and sets the project up there. Open it in VS Code, Positron, or RStudio by cloning it.',
        disabled: !!githubBlocked.value,
    }] : [];

    return githubReady.value ? [local, ...github, ...download] : [local, ...download, ...github];
});
const submitLabel = computed(() => (editing ? 'Save changes' : ({ download: 'Create and download', github: 'Create on GitHub' })[form.destination] ?? 'Create project'));
const destinationName = computed(() => destinations.value.find((d) => d.key === form.destination)?.title ?? '');
const githubError = computed(() => page.props.errors.github ?? form.errors['github.enabled']);

// Save as blueprint: a private blueprint with these choices, named after the project until renamed
const blueprintName = computed({
    get: () => (form.blueprintNameEdited ? form.blueprint_name : form.name),
    set: (value) => { form.blueprint_name = value; form.blueprintNameEdited = true; },
});
const newBlueprint = (data) => ({
    name: blueprintName.value,
    description: data.description,
    visibility: 'private',
    language: data.language,
    agents: data.agents,
    workflow: data.language === 'stata' ? 'scripts' : data.workflow,
    notebook_format: data.language === 'stata' ? null : data.notebook_format,
    structure: data.structure,
    tools: { ...toolChoices(data), targets: false },
    package_sets: data.package_sets,
    package_overrides: data.package_overrides,
    package_auto_attach: data.package_auto_attach,
    package_definitions: data.language === 'stata' ? {} : data.package_definitions,
    skill_sets: data.skill_sets,
    skills: data.skills,
    excluded_skills: data.excluded_skills,
    settings: { git: data.settings.git, hooks: data.settings.hooks, ai: !data.agents.includes('none') },
    extra_yaml: data.extra_yaml,
    ...settingChoices(data),
});

// Steps and tabs, each with its own URL
const stage = computed(() => (['start', 'project', 'review'].includes(props.stage) ? props.stage : 'start'));
const tab = computed(() => (tabs.value.some((t) => t.key === props.tab) ? props.tab : 'basics'));
const urlFor = (to, toTab = null) => {
    const url = new URL(page.url, window.location.origin);
    url.searchParams.set('stage', to);
    if (toTab) url.searchParams.set('tab', toTab);
    else url.searchParams.delete('tab');
    // A reload starts from the chosen blueprint and language
    if (form.blueprint_id) url.searchParams.set('blueprint', form.blueprint_id);
    url.searchParams.set('language', form.language);
    url.hash = '';
    return url.pathname + url.search;
};
const go = (to, toTab = null) => router.get(urlFor(to, toTab), {}, { preserveState: true, preserveScroll: false });
const navigation = computed(() => [
    { label: 'Start', href: urlFor('start'), preserveState: true, preserveScroll: false, current: stage.value === 'start' },
    { label: 'Project', href: urlFor('project'), preserveState: true, preserveScroll: false, current: false, quiet: true },
    ...tabs.value.map((t) => ({ label: t.label, href: urlFor('project', t.key), preserveState: true, preserveScroll: false, indent: true, current: stage.value === 'project' && tab.value === t.key })),
    { label: 'Review', href: urlFor('review'), preserveState: true, preserveScroll: false, current: stage.value === 'review' },
]);
// Your own blueprint already holds your choices, so Continue goes straight to Review
const ownBlueprint = computed(() => !!blueprint.value && group(blueprint.value) === 'mine');
// Review asks for the name when Basics was skipped or left it empty
const nameOnReview = ref(false);
watch(stage, (value) => { if (value === 'review' && (ownBlueprint.value || !form.name.trim())) nameOnReview.value = true; }, { immediate: true });
const next = () => {
    if (stage.value === 'start') return ownBlueprint.value ? go('review') : go('project', 'basics');
    if (stage.value === 'project') {
        const at = tabs.value.findIndex((t) => t.key === tab.value);
        return at < tabs.value.length - 1 ? go('project', tabs.value[at + 1].key) : go('review');
    }
    return editing ? save() : create();
};
const back = () => {
    if (stage.value === 'review') return go('project', tabs.value.at(-1).key);
    const at = tabs.value.findIndex((t) => t.key === tab.value);
    return at > 0 ? go('project', tabs.value[at - 1].key) : go('start');
};
const nextLabel = computed(() => (stage.value === 'review' ? submitLabel.value : stage.value === 'project' && tab.value === tabs.value.at(-1).key ? 'Review' : 'Continue'));

// A validation error opens the step and tab with the field
const errorPlace = (field) => {
    const top = field.split('.')[0];
    if (['blueprint_id', 'language'].includes(top)) return ['start'];
    if (['destination', 'github', 'new_blueprint'].includes(top)) return ['review'];
    if (['name', 'description'].includes(top)) return nameOnReview.value ? ['review'] : ['project', 'basics'];
    if (top === 'extra_yaml') return ['project', 'basics'];
    const target = errorSection(field);
    return target && tabs.value.some((t) => t.key === target) ? ['project', target] : ['review'];
};
const create = () => form
    .transform((data) => ({
        name: data.name,
        description: data.description,
        ...choicePayload(data),
        destination: data.destination,
        github: onGitHub.value
            ? { enabled: true, owner: data.github.owner, name: repoName.value, private: data.github.private }
            : { enabled: false },
        ...(data.save_blueprint ? { new_blueprint: newBlueprint(data) } : {}),
    }))
    .post('/projects', {
        preserveScroll: true,
        preserveState: true,
        onError: (errors) => {
            const [to, toTab] = errorPlace(Object.keys(errors)[0] ?? '');
            if (to !== stage.value || (toTab ?? null) !== (to === 'project' ? tab.value : null)) go(to, toTab);
        },
    });
const save = () => form
    .transform((data) => ({
        name: data.name,
        description: data.description,
        ...choicePayload(data),
        update_setup: setupChanged.value,
    }))
    .put(`/projects/${editing.id}`, {
        preserveScroll: true,
        preserveState: true,
        onError: (errors) => {
            const [to, toTab] = errorPlace(Object.keys(errors)[0] ?? '');
            if (to !== stage.value || (toTab ?? null) !== (to === 'project' ? tab.value : null)) go(to, toTab);
        },
    });
// What differs from the blueprint the project started from, one line per setting
const settingChanges = computed(() => {
    const before = baseline.value;
    const after = JSON.parse(JSON.stringify(snapshot()));
    const name = (list, key) => props.catalog[list]?.find((item) => item.key === key)?.name ?? key;
    const added = (a, b) => (b ?? []).filter((key) => !(a ?? []).includes(key));
    const delta = (a, b, label = (key) => key) => [...added(a, b).map((key) => `+${label(key)}`), ...added(b, a).map((key) => `−${label(key)}`)].join(', ');
    const on = (map) => Object.keys(map ?? {}).filter((key) => map[key]);
    const format = (c) => (c.workflow === 'scripts' || !c.notebook_format ? 'Scripts' : ({ quarto: 'Quarto', rmarkdown: 'R Markdown', jupyter: 'Jupyter' })[c.notebook_format] ?? c.notebook_format);
    // Packages: an override on adds a package and an override off removes one
    const packageDelta = () => {
        const keys = [...new Set([...Object.keys(before.package_overrides ?? {}), ...Object.keys(after.package_overrides ?? {})])];
        return keys.filter((key) => before.package_overrides?.[key] !== after.package_overrides?.[key])
            .map((key) => ((after.package_overrides?.[key] ?? !before.package_overrides?.[key]) ? `+${key}` : `−${key}`)).join(', ');
    };
    // Skills as the picker shows them: single skills and set members, less exclusions
    const skills = (c) => props.catalog.skills.filter((skill) => fitsLanguage(skill.language, form.language)
        && !(c.excluded_skills ?? []).includes(skill.key)
        && ((c.skills ?? []).includes(skill.key) || props.catalog.skill_sets.some((set) => (c.skill_sets ?? []).includes(set.key) && set.members?.[form.language]?.includes(skill.skill))))
        .map((skill) => skill.key);
    const hookName = (key) => hooks.find((hook) => hook.key === key)?.label ?? key;
    const editor = (ide) => editorsOf(ide).map((key) => editorOptions.find((o) => o.value === key)?.label ?? key).join(' and ') || 'None';
    const publishing = (value) => ({ '': 'Automatic', cloud: 'Framework Cloud', github: 'GitHub Pages' })[value ?? ''] ?? value;
    const blank = (value) => value === undefined || value === null || value === '';
    const paths = (a, b, prefix = '') => [...new Set([...Object.keys(a ?? {}), ...Object.keys(b ?? {})])].flatMap((key) => {
        const [x, y] = [a?.[key], b?.[key]];
        if ((x && typeof x === 'object' && !Array.isArray(x)) || (y && typeof y === 'object' && !Array.isArray(y))) return paths(x, y, `${prefix}${key} `);
        if ((blank(x) && blank(y)) || JSON.stringify(x) === JSON.stringify(y)) return [];
        return [`${prefix}${key.replaceAll('_', ' ')}: ${blank(x) ? 'default' : x} → ${blank(y) ? 'default' : y}`];
    });
    const nested = (a, b) => paths(a, b).join('; ');
    const swap = (a, b) => (a === b ? '' : `${a} → ${b}`);
    // Connections added, removed, or changed, by name
    const connectionDelta = (a, b) => {
        const rows = (c) => Object.fromEntries([...(c?.databases ?? []), ...(c?.storage_buckets ?? [])].map((row) => [row.name, JSON.stringify(row)]));
        const [x, y] = [rows(a), rows(b)];
        const changed = Object.keys(y).filter((key) => key in x && x[key] !== y[key]).map((key) => `${key} changed`);
        const defaults = ['default_database', 'default_storage_bucket'].filter((key) => (a?.[key] ?? '') !== (b?.[key] ?? '')).map((key) => `${key.replaceAll('_', ' ')}: ${b?.[key] || 'not set'}`);
        return [delta(Object.keys(x), Object.keys(y)), ...changed, ...defaults].filter(Boolean).join(', ');
    };
    return [
        ['Notebook format', swap(format(before), format(after))],
        ['Structure', swap(name('structures', before.structure), name('structures', after.structure))],
        ['Tools', delta(on(before.tools), on(after.tools), (key) => name('tools', key))],
        ['Package sets', delta(before.package_sets, after.package_sets, (key) => name('package_sets', key))],
        ['Packages', packageDelta()],
        ['Auto-attach', [...new Set([...Object.keys(before.package_auto_attach ?? {}), ...Object.keys(after.package_auto_attach ?? {})])]
            .filter((key) => before.package_auto_attach?.[key] !== after.package_auto_attach?.[key])
            .map((key) => `${key} ${({ true: 'on', false: 'off' })[after.package_auto_attach?.[key]] ?? 'as the package set has it'}`).join(', ')],
        ['Agents', delta(before.agents, after.agents, (key) => agentNames[key] ?? key)],
        ['Skill sets', delta(before.skill_sets, after.skill_sets, (key) => name('skill_sets', key))],
        ['Skills', delta(skills(before), skills(after), (key) => name('skills', key))],
        ['Git', swap(before.settings.git ? 'On' : 'Off', after.settings.git ? 'On' : 'Off')],
        ['Git hooks', delta(before.settings.hooks, after.settings.hooks, hookName)],
        ['Scaffold', nested(before.scaffold, after.scaffold)],
        ['Quarto', nested(before.quarto, after.quarto)],
        ['Editor', swap(editor(before.ide), editor(after.ide))],
        ['Publishing', swap(publishing(before.publish_destination), publishing(after.publish_destination))],
        ['Other packages', delta(Object.keys(before.package_definitions ?? {}), Object.keys(after.package_definitions ?? {}))],
        ['Connections', connectionDelta(before.connections, after.connections)],
        ['Environment variables', JSON.stringify(before.connections?.variables ?? []) === JSON.stringify(after.connections?.variables ?? []) ? '' : 'Changed'],
        ['Templates', ['notebook', 'script', 'slides'].filter((key) => (before.templates?.[key] ?? '') !== (after.templates?.[key] ?? '')).join(', ')],
        ['Extra settings', (before.extra_yaml ?? '').trim() === (after.extra_yaml ?? '').trim() ? '' : 'Changed'],
    ].filter(([, detail]) => detail).map(([label, detail]) => ({ label, detail }));
});
// Nothing changed leaves nothing new to save
watch(() => settingChanges.value.length, (count) => { if (!count) form.save_blueprint = false; });
const blueprintError = computed(() => Object.entries(form.errors).find(([key]) => key.startsWith('new_blueprint.'))?.[1]);
const submitDisabled = computed(() => form.processing || (stage.value === 'review' && !editing && onGitHub.value && (!githubReady.value || (sensitive.value && !form.github.private))));
</script>

<template>
    <Head :title="editing ? `Edit ${editing.name}` : 'New project'" />

    <SectionLayout
        :heading="editing ? editing.name : 'New project'"
        :back="editing ? { label: 'Back to the project', href: `/projects/${editing.slug}` } : { label: 'Back to all projects', href: '/projects' }"
        :items="navigation" :dirty="editing ? setupChanged : edited" wide
    >
        <PageHeader :title="editing ? 'Edit project' : 'New project'">
            <template #description>
                <template v-if="editing && stage === 'start'">Change the project's language or blueprint. A new blueprint replaces the project's choices.</template>
                <template v-else-if="editing && stage === 'project'">Change the project's name and choices.</template>
                <template v-else-if="editing">Check the project, then save it. New tokens, setup commands, and downloads use the saved setup.</template>
                <template v-else-if="stage === 'start'">Choose a language and the blueprint to start from. The blueprint fills in the project's choices.</template>
                <template v-else-if="stage === 'project'">Name the project and adjust its choices. Changes apply to this project only.</template>
                <template v-else>Check the project, then choose where to set it up.</template>
            </template>
        </PageHeader>

        <UiCallout v-if="editing?.unknown_choices" variant="warning" heading="Earlier changes" class="mt-6 max-w-3xl">
            This project changed its blueprint's choices before Framework kept them, so the form starts from the blueprint.
            Saving keeps those changes unless you change the setup here.
        </UiCallout>

        <form id="create-form" class="mt-8 flex flex-col gap-8" @submit.prevent="next">
            <!-- Start: a language, then a blueprint for it -->
            <template v-if="stage === 'start'">
                <UiPanel flush title="Language" class="max-w-2xl" description="Each project uses one language.">
                    <UiRadioCards :model-value="form.language" name="project-language" legend="Language" hide-legend :options="languages" columns="sm:grid-cols-3" @update:model-value="chooseLanguage" />
                    <p v-if="stata" class="text-sm text-slate-500 dark:text-slate-400">Stata projects use do-files and the framework Stata package for paths, data, and publishing.</p>
                    <p v-if="form.errors.language" class="text-sm text-red-600 dark:text-red-400">{{ form.errors.language }}</p>
                </UiPanel>

                <UiSeparator />

                <UiPanel id="blueprint" flush title="Blueprint" class="max-w-3xl" description="Your default blueprint for the language is selected first.">
                    <template #header>
                        <UiButton href="/blueprints/create" variant="ghost" size="sm">Build a blueprint</UiButton>
                    </template>
                    <p v-if="form.errors.blueprint_id" class="text-sm text-red-600 dark:text-red-400">{{ form.errors.blueprint_id }}</p>
                    <p v-if="!blueprintGroups.length" class="text-sm text-slate-500 dark:text-slate-400">No blueprints for this language.</p>

                    <div v-for="g in blueprintGroups" :key="g.key" class="flex flex-col gap-1">
                        <div v-if="blueprintGroups.length > 1" class="flex items-center justify-between gap-3">
                            <h3 class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ g.label }}</h3>
                            <UiButton variant="subtle" size="xs" :aria-expanded="!hidden.includes(g.key)" @click="toggleGroup(g.key)">
                                {{ hidden.includes(g.key) ? `Show ${g.rows.length}` : 'Hide' }}
                            </UiButton>
                        </div>
                        <div class="-mx-4 flex flex-col divide-y divide-slate-800/10 dark:divide-white/10" role="radiogroup" :aria-label="g.label">
                            <button
                                v-for="b in shownRows(g)" :key="b.id" type="button" role="radio" :aria-checked="b.id === form.blueprint_id"
                                class="flex w-full items-start gap-3 p-4 text-left transition-colors"
                                :class="b.id === form.blueprint_id ? 'bg-sky-50/60 dark:bg-sky-950/30' : 'hover:bg-slate-50 dark:hover:bg-slate-900'"
                                @click="chooseBlueprint(b.id)"
                            >
                                <span
                                    class="mt-1 flex size-4 shrink-0 items-center justify-center rounded-full border"
                                    :class="b.id === form.blueprint_id ? 'border-sky-500' : 'border-slate-300 dark:border-slate-600'"
                                ><span v-if="b.id === form.blueprint_id" class="size-2 rounded-full bg-sky-600" /></span>
                                <span class="flex min-w-0 flex-1 flex-col gap-1">
                                    <span class="flex flex-wrap items-center gap-2">
                                        <span class="font-medium text-slate-900 dark:text-white">{{ b.name }}</span>
                                        <span v-if="b.sensitive" class="rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-800 dark:bg-amber-900/40 dark:text-amber-200">Sensitive data</span>
                                        <span v-if="defaultFor(form.language)?.id === b.id" class="rounded-full bg-slate-800/5 px-2 py-0.5 text-xs text-slate-600 dark:bg-white/10 dark:text-slate-300">Default</span>
                                    </span>
                                    <span v-if="b.description" class="text-sm text-slate-600 dark:text-slate-400">{{ b.description }}</span>
                                    <span class="font-mono text-xs text-slate-500 dark:text-slate-400">{{ b.handle }} v{{ b.version }}</span>
                                </span>
                            </button>
                        </div>
                    </div>
                </UiPanel>
            </template>

            <!-- Project: a name, then the blueprint's choices, a tab at a time -->
            <template v-else-if="stage === 'project'">
                <p class="max-w-2xl text-sm text-slate-600 dark:text-slate-400">
                    From <UiLink :href="`/blueprints/${blueprint?.id}`">{{ blueprint?.name }}</UiLink>
                    for {{ languages.find((l) => l.value === form.language)?.label }}.
                    <template v-if="edited || startCustomized">Changed for this project.</template>
                    <UiButton variant="subtle" size="xs" class="ml-1" @click="go('start')">Change</UiButton>
                </p>

                <UiPanel v-if="tab === 'basics'" flush id="name" title="Basics" class="max-w-2xl scroll-mt-6">
                    <div>
                        <UiInput v-model="form.name" label="Project name" placeholder="NHANES study" required :error="form.errors.name" />
                        <p v-if="slug" class="mt-1.5 text-xs text-slate-500 dark:text-slate-400">Folder name: <span class="font-mono">{{ slug }}</span></p>
                    </div>
                    <UiTextarea v-model="form.description" label="Description (optional)" placeholder="Study purpose, data, and planned analysis" :rows="3" :error="form.errors.description" />
                    <details class="group" :open="!!form.errors.extra_yaml">
                        <summary class="flex cursor-pointer list-none items-center gap-1 text-sm font-medium text-slate-800 dark:text-slate-200 [&::-webkit-details-marker]:hidden">
                            <ChevronRightIcon class="-ml-1 size-4 shrink-0 text-slate-400 transition-transform group-open:rotate-90" />Extra settings (YAML)
                        </summary>
                        <UiTextarea class="mt-4" v-model="form.extra_yaml" code :rows="6"
                            aria-label="Extra settings (YAML)" :placeholder="'scaffold:\n  seed: 123'" description="Settings from the blueprint beyond its choices, applied on top of them."
                            :error="form.errors.extra_yaml"
                        />
                    </details>
                </UiPanel>

                <template v-else>
                    <UiPanel v-if="tab === 'notebooks' && !editorDraws('notebooks')" flush title="Notebook format" class="max-w-2xl">
                        <p v-if="stata" class="text-sm text-slate-500 dark:text-slate-400">Stata projects use .do scripts. No notebook setup is needed.</p>
                        <template v-else>
                            <UiSwitch v-model="useNotebooks" label="Use notebooks" :description="`Off: plain ${form.language === 'r' ? '.R' : '.py'} scripts, with no notebooks or Quarto.`" />
                            <UiRadioCards v-if="useNotebooks" v-model="notebookFormat" name="project-notebook" legend="Notebook format" hide-legend :options="notebookOptions" />
                            <UiSwitch v-for="t in notebookTools" v-show="useNotebooks" :key="t.key" v-model="form.tools[t.key]" :label="t.name" :description="t.description" />
                        </template>
                        <p v-if="form.errors.workflow || form.errors.notebook_format" class="text-xs text-red-600 dark:text-red-400">{{ form.errors.workflow || form.errors.notebook_format }}</p>
                    </UiPanel>
                    <UiPanel v-if="tab === 'environment' && !editorDraws('environment')" flush title="Reproducibility" class="max-w-2xl">
                        <UiSwitch
                            v-if="environmentTool" v-model="form.tools[environmentTool.key]" :label="environmentTool.name"
                            :disabled="!!requiredTools[environmentTool.key]"
                            :description="requiredTools[environmentTool.key] ? `${requiredTools[environmentTool.key]}, so it stays on.` : environmentTool.description"
                        />
                        <p v-else class="text-sm text-slate-500 dark:text-slate-400">Stata projects have no managed package environment.</p>
                        <p v-if="form.errors.tools" class="text-xs text-red-600 dark:text-red-400">{{ form.errors.tools }}</p>
                    </UiPanel>
                    <UiPanel v-if="tab === 'git' && !editorDraws('git')" flush title="Git" class="max-w-2xl">
                        <UiSwitch v-model="form.settings.git" label="Use git" />
                        <fieldset v-if="form.settings.git && !stata" class="flex flex-col gap-5">
                            <legend class="mb-4 text-sm font-medium text-slate-900 dark:text-white">Commit hooks</legend>
                            <UiSwitch v-for="hook in hooks" :key="hook.key" :model-value="form.settings.hooks.includes(hook.key)" :label="hook.label" :description="hook.description" @update:model-value="toggleHook(hook.key, $event)" />
                        </fieldset>
                        <p v-else-if="form.settings.git" class="text-sm text-slate-500 dark:text-slate-400">Commit hooks are available for R and Python projects.</p>
                        <p v-if="form.errors.settings" class="text-xs text-red-600 dark:text-red-400">{{ form.errors.settings }}</p>
                    </UiPanel>

                    <ChoicesEditor :form="form" :catalog="catalog" :apply-on-load="!editing" :section="tab" :languages="[form.language]" :required="requiredTools" :ggplot-themes="ggplotThemes" :quarto-choices="quartoChoices" agent-picker flush>
                        <!-- The files and folders this project gets, as the choices stand -->
                        <template #structure-preview>
                            <div class="flex flex-col gap-3 border-t border-slate-800/10 pt-6 dark:border-white/15" :class="current ? '' : 'opacity-60'">
                                <p v-if="shown?.problem" class="text-xs text-red-600 dark:text-red-400">{{ shown.problem }}</p>
                                <ProjectTreeView v-else-if="shown?.tree" :entries="shown.tree" />
                                <div v-if="shown?.render_dirs?.length" class="text-xs text-slate-600 dark:text-slate-400">
                                    <div class="font-medium text-slate-900 dark:text-white">Render destinations</div>
                                    <ul class="mt-1 font-mono">
                                        <li v-for="d in shown.render_dirs" :key="d.from">{{ d.from }}/ → {{ d.to }}/</li>
                                    </ul>
                                </div>
                                <p v-if="shown?.settings?.sensitive" class="text-xs text-amber-800 dark:text-amber-300">Sensitive data: private folders stay out of Git, and Framework warns before anything private is uploaded or published.</p>
                            </div>
                        </template>
                    </ChoicesEditor>
                </template>
            </template>

            <!-- Review: what the project gets, where it goes, and creating it -->
            <template v-else>
                <template v-if="nameOnReview">
                    <UiPanel id="name" flush title="Project details" class="max-w-2xl scroll-mt-6">
                        <div>
                            <UiInput v-model="form.name" label="Project name" placeholder="NHANES study" required :error="form.errors.name" />
                            <p v-if="slug" class="mt-1.5 text-xs text-slate-500 dark:text-slate-400">Folder name: <span class="font-mono">{{ slug }}</span></p>
                        </div>
                        <UiTextarea v-model="form.description" label="Description (optional)" placeholder="Study purpose, data, and planned analysis" :rows="2" :error="form.errors.description" />
                        <p class="text-sm text-slate-500 dark:text-slate-400">
                            From {{ blueprint?.name }}.
                            <UiButton variant="subtle" size="xs" class="ml-1" @click="go('project', tabs[1]?.key ?? 'basics')">Adjust choices</UiButton>
                        </p>
                    </UiPanel>
                    <UiSeparator />
                </template>
                <UiCallout v-if="current?.missing_render_directory" variant="warning" heading="Quarto render directory" class="max-w-3xl">
                    <p>The render directory <code class="font-mono">{{ current.missing_render_directory }}/</code> will not be created during project setup. Add it to the project structure if you need it before rendering.</p>
                    <div><UiButton size="sm" variant="ghost" @click="go('project', 'structure')">Review project structure</UiButton></div>
                </UiCallout>
                <UiPanel id="summary" title="Summary" flush class="max-w-3xl scroll-mt-6">
                    <dl class="grid grid-cols-[auto_minmax(0,1fr)] gap-x-6 gap-y-2 text-sm">
                        <dt class="text-slate-500 dark:text-slate-400">Name</dt>
                        <dd class="break-words font-medium text-slate-900 dark:text-white">{{ form.name || 'Not named yet' }}</dd>
                        <template v-if="form.description"><dt class="text-slate-500 dark:text-slate-400">Description</dt><dd class="break-words">{{ form.description }}</dd></template>
                        <dt class="text-slate-500 dark:text-slate-400">Blueprint</dt>
                        <dd>
                            {{ blueprint?.name }}
                            <span class="ml-1 font-mono text-xs text-slate-500 dark:text-slate-400">{{ blueprint?.handle }} v{{ blueprint?.version }}</span>
                        </dd>
                        <dt class="text-slate-500 dark:text-slate-400">Language</dt><dd>{{ languages.find((l) => l.value === form.language)?.label ?? form.language }}</dd>
                        <dt class="text-slate-500 dark:text-slate-400">Choices</dt><dd>{{ edited || startCustomized ? 'Changed for this project' : 'As the blueprint has them' }}</dd>
                        <template v-if="!shown"><dt class="sr-only">Status</dt><dd class="col-span-2 text-slate-500 dark:text-slate-400">Resolving…</dd></template>
                        <template v-else-if="shown.problem"><dt class="sr-only">Problem</dt><dd class="col-span-2 text-amber-800 dark:text-amber-300">{{ shown.problem }}</dd></template>
                        <template v-else>
                            <dt class="text-slate-500 dark:text-slate-400">Structure</dt><dd>{{ pieceName('structures', shown.structure) }}</dd>
                            <template v-if="shown.workflow"><dt class="text-slate-500 dark:text-slate-400">Workflow</dt><dd>{{ shown.workflow.name }}</dd></template>
                            <dt class="text-slate-500 dark:text-slate-400">Tools</dt>
                            <dd>{{ Object.entries(shown.tools).filter(([, t]) => t.on).map(([k]) => pieceName('tools', k)).join(', ') || 'None' }}</dd>
                            <dt class="text-slate-500 dark:text-slate-400">Package sets</dt>
                            <dd><template v-for="(s, i) in shown.package_sets" :key="s.key">{{ i ? ', ' : '' }}{{ s.name }}<span v-if="s.from === 'yours'" class="text-xs text-slate-500 dark:text-slate-400"> (your default)</span></template><template v-if="!shown.package_sets.length">None</template></dd>
                            <dt class="text-slate-500 dark:text-slate-400">Agents</dt><dd>{{ agentSummary(shown.agents.enabled, shown.agents.named, shown.agents.other) }}</dd>
                            <template v-if="shown.agents.enabled">
                                <dt class="text-slate-500 dark:text-slate-400">Skill sets</dt>
                                <dd><template v-for="(s, i) in shown.skill_sets" :key="s.key">{{ i ? ', ' : '' }}{{ s.name }}<span v-if="s.from === 'yours'" class="text-xs text-slate-500 dark:text-slate-400"> (your default)</span></template><template v-if="!shown.skill_sets.length">None</template></dd>
                            </template>
                            <dt class="text-slate-500 dark:text-slate-400">Git</dt><dd>{{ shown.settings.git ? 'On' : 'Off' }}</dd>
                            <template v-if="shown.settings.sensitive"><dt class="text-slate-500 dark:text-slate-400">Sensitive data</dt><dd>Private and public folders are kept apart</dd></template>
                            <template v-for="n in shown.notices" :key="n"><dt class="sr-only">Note</dt><dd class="col-span-2 text-xs text-amber-800 dark:text-amber-300">{{ n }}</dd></template>
                        </template>
                    </dl>
                    <div v-if="shown?.tree" :class="current ? '' : 'opacity-60'" class="border-t border-slate-800/10 pt-6 dark:border-white/15">
                        <ProjectTreeView :entries="shown.tree" />
                    </div>
                </UiPanel>

                <UiSeparator />

                <UiPanel v-if="editing" id="saving" title="Setup update" flush class="max-w-3xl scroll-mt-6">
                    <p v-if="!setupChanged" class="text-sm text-slate-600 dark:text-slate-400">The setup is unchanged. Saving updates the name and description.</p>
                    <template v-else>
                        <dl v-if="settingChanges.length" class="grid grid-cols-[auto_minmax(0,1fr)] gap-x-6 gap-y-2 text-sm">
                            <template v-for="change in settingChanges" :key="change.label">
                                <dt class="text-slate-500 dark:text-slate-400">{{ change.label }}</dt>
                                <dd class="break-words">{{ change.detail }}</dd>
                            </template>
                        </dl>
                        <p class="text-sm text-slate-600 dark:text-slate-400">New tokens, setup commands, and downloads use the saved setup. Folders already set up keep their files.</p>
                        <p v-if="editing.github_repo" class="text-sm text-slate-600 dark:text-slate-400">{{ editing.github_repo }} keeps its current files. After saving, Update on GitHub on the project page opens a pull request with Framework's regenerated settings files.</p>
                    </template>
                </UiPanel>

                <UiPanel v-if="!editing" id="where" title="Destination" flush class="max-w-3xl scroll-mt-6">
                    <div class="grid gap-3" :class="destinations.length === 3 ? 'lg:grid-cols-3' : 'sm:grid-cols-2'">
                        <button
                            v-for="d in destinations" :key="d.key"
                            type="button"
                            class="flex flex-col gap-1.5 rounded-lg border p-4 text-left transition-colors disabled:cursor-not-allowed disabled:opacity-60"
                            :class="form.destination === d.key
                                ? 'border-sky-500 bg-sky-50 ring-1 ring-sky-500 dark:bg-sky-950/30'
                                : 'border-slate-200 bg-white hover:border-slate-300 dark:border-slate-700 dark:bg-slate-900 dark:hover:border-slate-600'"
                            :aria-pressed="form.destination === d.key"
                            :disabled="d.disabled"
                            @click="form.destination = d.key"
                        >
                            <span class="font-medium">{{ d.title }}</span>
                            <span class="text-sm text-slate-600 dark:text-slate-400">{{ d.text }}</span>
                        </button>
                    </div>
                    <p v-if="form.errors.destination" class="text-sm text-red-600 dark:text-red-400">{{ form.errors.destination }}</p>
                    <p v-if="form.destination === 'download'" class="text-sm text-slate-600 dark:text-slate-400">
                        The project stays here too. You can download it again from its page, and connect the folder to it later with a project token.
                    </p>

                    <div v-if="onGitHub" class="flex flex-col gap-4 rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
                        <p v-if="!github.login" class="text-sm text-slate-600 dark:text-slate-400">
                            <UiLink href="/account/github">Connect GitHub</UiLink> first.
                        </p>
                        <p v-else-if="github.owners === null" class="text-sm text-slate-600 dark:text-slate-400">
                            GitHub could not be reached. Reload to try again.
                        </p>
                        <p v-else-if="!ownerOptions.length" class="text-sm text-slate-600 dark:text-slate-400">
                            <UiLink :href="github.install_url" external>Install the Framework app</UiLink>
                            on your account or an organization, then reload this page.
                        </p>
                        <template v-else>
                            <div class="flex flex-wrap items-end gap-2">
                                <div class="w-44"><UiSelect v-model="form.github.owner" label="Owner" :options="ownerOptions" :error="form.errors['github.owner']" /></div>
                                <span class="pb-2 text-slate-400">/</span>
                                <div class="min-w-48 flex-1">
                                    <UiInput :model-value="repoName" label="Repository" placeholder="nhanes-study" :error="form.errors['github.name']" @update:model-value="editRepoName" />
                                </div>
                            </div>
                            <UiSwitch
                                v-model="form.github.private" label="Private repository" :disabled="sensitive"
                                :description="sensitive ? 'Required: this project handles restricted data.' : 'Only you and people you add can see it.'"
                            />
                            <p v-if="form.errors['github.private']" class="text-sm text-red-600 dark:text-red-400">{{ form.errors['github.private'] }}</p>
                        </template>
                    </div>
                </UiPanel>

                <UiSeparator v-if="!editing" />

                <UiPanel v-if="!editing" id="save-blueprint" title="Blueprint copy" flush class="max-w-2xl scroll-mt-6">
                    <div v-if="settingChanges.length" class="flex flex-col gap-2">
                        <h3 class="text-sm font-medium text-slate-800 dark:text-slate-200">Changes from {{ blueprint?.name }}</h3>
                        <dl class="grid grid-cols-[auto_minmax(0,1fr)] gap-x-6 gap-y-2 text-sm">
                            <template v-for="change in settingChanges" :key="change.label">
                                <dt class="text-slate-500 dark:text-slate-400">{{ change.label }}</dt>
                                <dd class="break-words">{{ change.detail }}</dd>
                            </template>
                        </dl>
                    </div>
                    <p v-else class="text-sm text-slate-500 dark:text-slate-400">No changes from {{ blueprint?.name }}.</p>
                    <UiSwitch
                        v-model="form.save_blueprint" :disabled="!settingChanges.length" label="Save as blueprint"
                        :description="settingChanges.length ? 'Saves these choices as a private blueprint for later projects.' : 'Change a choice to save a new blueprint.'"
                    />
                    <UiInput v-if="form.save_blueprint" v-model="blueprintName" label="Blueprint name" placeholder="Survey analysis" :error="form.errors['new_blueprint.name']" />
                    <p v-if="blueprintError && !form.errors['new_blueprint.name']" class="text-sm text-red-600 dark:text-red-400">{{ blueprintError }}</p>
                </UiPanel>

                <UiCallout v-if="githubError" variant="danger" heading="GitHub">{{ githubError }}</UiCallout>
            </template>

            <div class="flex items-center justify-between gap-3 border-t border-slate-800/10 pt-6 dark:border-white/15">
                <UiButton v-if="stage !== 'start'" variant="ghost" @click="back">Back</UiButton>
                <UiButton v-else :href="editing ? `/projects/${editing.slug}` : '/projects'" variant="ghost">Cancel</UiButton>
                <UiButton type="submit" class="max-lg:hidden" :disabled="submitDisabled">{{ nextLabel }}</UiButton>
            </div>
        </form>

        <template #actions>
            <UiButton type="submit" form="create-form" class="w-full" :disabled="submitDisabled">{{ nextLabel }}</UiButton>
        </template>

        <UiModal v-model="confirming" title="Blueprint change">
            <p class="text-sm text-slate-600 dark:text-slate-400">
                You changed this project's choices. Switching to {{ pendingBlueprint?.name }}<template v-if="pending?.language !== form.language"> for {{ languages.find((l) => l.value === pending?.language)?.label }}</template>
                resets them to that blueprint's choices. The name, description, and destination stay.
            </p>
            <div class="flex justify-end gap-3">
                <UiButton variant="ghost" @click="confirming = false">Keep editing</UiButton>
                <UiButton @click="apply(pending)">Switch blueprint</UiButton>
            </div>
        </UiModal>
    </SectionLayout>
</template>
