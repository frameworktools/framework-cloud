<script setup>
import { computed, ref, watch } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import SectionLayout from '@/Layouts/SectionLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import UiPanel from '@/Components/UiPanel.vue';
import UiButton from '@/Components/UiButton.vue';
import UiInput from '@/Components/UiInput.vue';
import UiSelect from '@/Components/UiSelect.vue';
import UiTextarea from '@/Components/UiTextarea.vue';
import UiRadioCards from '@/Components/UiRadioCards.vue';
import UiSwitch from '@/Components/UiSwitch.vue';
import ChoicesEditor from '@/Components/ChoicesEditor.vue';
import ProjectTreeView from '@/Components/ProjectTreeView.vue';
import { languageOptions, fitsLanguage, typeFits, visibilityOptions } from '@/library.js';

const props = defineProps({
    blueprint: Object, // null when creating
    start: Object, // choices to begin from: the version being edited, or ?from=
    catalog: Object,
    section: String,
    agents: Array,
    ggplotThemes: Array,
    quartoChoices: Object,
});

const editing = computed(() => !!props.blueprint);
const initialLanguage = props.start?.language ?? props.blueprint?.language ?? 'r';
const defaultFormat = language => ({ r: 'quarto', python: 'jupyter', stata: 'script' })[language];
const initialWorkflow = props.start?.default_workflow?.[initialLanguage] ?? props.start?.workflows?.[0];

const form = useForm({
    name: props.blueprint?.name ?? '',
    description: props.blueprint?.description ?? '',
    visibility: props.blueprint?.visibility ?? 'private',
    language: initialLanguage,
    notebook_format: props.start?.notebook_format ?? (initialWorkflow === 'jupyter' ? 'jupyter' : initialWorkflow === 'scripts' && initialLanguage !== 'stata' ? 'scripts' : defaultFormat(initialLanguage)),
    agents: [...(props.start?.agents ?? props.agents ?? ['other'])],
    structure: props.start?.structure ?? 'framework/project',
    tools: { ...(props.start?.tools ?? {}), targets: false },
    package_sets: [...(props.start?.package_sets ?? [])],
    package_overrides: { ...(props.start?.package_overrides ?? {}) },
    package_auto_attach: { ...(props.start?.package_auto_attach ?? {}) },
    skill_sets: [...(props.start?.skill_sets ?? ['framework/framework'])],
    skills: [...(props.start?.skills ?? [])],
    excluded_skills: [...(props.start?.excluded_skills ?? [])],
    settings: { git: true, hooks: [], ...(props.start?.settings ?? {}), ai: !(props.start?.agents ?? props.agents ?? ['other']).includes('none'), sensitive: !!props.catalog.structures.find(structure => structure.key === (props.start?.structure ?? 'framework/project'))?.sensitive },
    // Scaffold, Quarto, editor files, and publishing: the settings a project's defaults held
    scaffold: { ...(props.start?.scaffold ?? {}) },
    quarto: { html: { ...(props.start?.quarto?.html ?? {}) }, revealjs: { ...(props.start?.quarto?.revealjs ?? {}) } },
    ide: props.start?.ide ?? 'vscode',
    publish_destination: props.start?.publish_destination ?? '',
    extra_yaml: props.start?.extra_yaml ?? '',
    notes: '',
});
const showAdvanced = ref(!!props.start?.extra_yaml);

const errorSection = errors => {
    const field = Object.keys(errors)[0]?.split('.')[0];
    return ({ agents: 'ai', language: 'language', structure: 'structure', languages: 'language', workflow: 'notebooks', workflows: 'notebooks', notebook_format: 'notebooks', tools: 'environment', package_sets: 'packages', package_overrides: 'packages', package_auto_attach: 'packages', skills: 'skills', excluded_skills: 'skills', skill_sets: 'skills', settings: 'git', scaffold: 'scaffold', quarto: 'quarto', ide: 'editor', publish_destination: 'publishing' })[field] ?? 'name';
};
const save = () => {
    const options = { preserveScroll: true, preserveState: true, onError: errors => {
        const target = errorSection(errors);
        if (target !== activeSection.value) router.get(sectionUrl(target), {}, { preserveState: true });
    } };
    form.transform(data => ({ ...data, workflow: workflow.value, notebook_format: sentFormat(data) }));
    return editing.value ? form.put(`/blueprints/${props.blueprint.id}`, options) : form.post('/blueprints', options);
};
const saved = computed(() => page.props.flash.status === 'blueprint-saved');

const workflow = computed(() => form.language === 'stata' || form.notebook_format === 'scripts' ? 'scripts' : form.notebook_format === 'jupyter' ? 'jupyter' : 'quarto');
// Scripts is a workflow, not a notebook format, so the request sends no format for it
const sentFormat = data => (data.language === 'stata' || data.notebook_format === 'scripts' ? null : data.notebook_format);
const notebookOptions = computed(() => [
    { value: 'quarto', label: 'Quarto', description: 'Documents in .qmd files.' },
    ...(form.language === 'r' ? [{ value: 'rmarkdown', label: 'R Markdown', description: 'Documents in .Rmd files, rendered with Quarto.' }] : []),
    { value: 'jupyter', label: 'Jupyter', description: `Notebooks in .ipynb files with ${form.language === 'r' ? 'IRkernel' : 'ipykernel'}.` },
    { value: 'scripts', label: 'Scripts', description: `Plain ${form.language === 'r' ? '.R' : '.py'} files. No notebooks.` },
]);
// Scripts leave out Quarto's manuscript and slides
watch(() => form.notebook_format, format => {
    if (format === 'scripts') for (const key of ['quarto_manuscript', 'quarto_slides']) form.tools[key] = false;
});
// The Jupyter notebook format brings Jupyter, so it can't be turned off
const requiredTools = computed(() => (form.language !== 'stata' && form.notebook_format === 'jupyter' ? { jupyter: 'The Jupyter notebook format includes it' } : {}));
const notebookTools = computed(() => props.catalog.tools.filter(tool => ['quarto_manuscript', 'quarto_slides'].includes(tool.key)));
const environmentTool = computed(() => props.catalog.tools.find(tool => tool.key === ({ r: 'renv', python: 'uv' })[form.language]));
const hooks = [
    { key: 'data_security', label: 'Secret detection', description: 'Stops commits that contain passwords, keys, or other secrets.' },
    { key: 'check_sensitive_dirs', label: 'Private folder warnings', description: 'Warns when a private folder is missing from .gitignore.' },
    { key: 'check_notebook_outputs', label: 'Notebook output checks', description: 'Stops commits of Jupyter notebooks that still contain outputs.' },
];
const toggleHook = (key, on) => { form.settings.hooks = on ? [...new Set([...form.settings.hooks, key])] : form.settings.hooks.filter(hook => hook !== key); };
watch(() => form.agents, agents => { form.settings.ai = !agents.includes('none'); });
watch(() => form.structure, key => {
    form.settings.sensitive = !!props.catalog.structures.find(structure => structure.key === key)?.sensitive;
});
watch(() => form.language, (language) => {
    form.notebook_format = defaultFormat(language);
    const structure = props.catalog.structures.find(item => item.key === form.structure);
    if (!structure || !typeFits(structure.type, language)) form.structure = 'framework/project';
    form.tools = Object.fromEntries(props.catalog.tools.filter(tool => tool.key !== 'targets' && fitsLanguage(tool.language, language)).map(tool => [tool.key, !!form.tools[tool.key]]));
    for (const [field, items] of [['package_sets', props.catalog.package_sets], ['skill_sets', props.catalog.skill_sets], ['skills', props.catalog.skills], ['excluded_skills', props.catalog.skills]]) {
        form[field] = form[field].filter(key => items.some(item => item.key === key && fitsLanguage(item.language, language)));
    }
    form.package_overrides = {};
    form.package_auto_attach = {};
});

// The files and folders a project would get, for one language and workflow it supports
const page = usePage();
const previewKey = computed(() => JSON.stringify({
    language: form.language, agents: form.agents, workflow: workflow.value, notebook_format: sentFormat(form), structure: form.structure, tools: form.tools,
    package_sets: form.package_sets, package_overrides: form.package_overrides, package_auto_attach: form.package_auto_attach, skill_sets: form.skill_sets, skills: form.skills, excluded_skills: form.excluded_skills, settings: form.settings, extra_yaml: form.extra_yaml, ide: form.ide,
}));
const preview = ref(null);
const previewFor = ref(null);
let request = 0;
let timer = null;
const loadPreview = async () => {
    const id = ++request;
    const key = previewKey.value;
    if (!key) return;
    const response = await fetch('/blueprints/preview', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': page.props.csrf, 'X-Requested-With': 'XMLHttpRequest' },
        body: key,
    }).catch(() => null);
    const data = await response?.json().catch(() => null);
    if (id !== request) return;
    preview.value = response?.ok ? data : { problem: data?.problem ?? data?.message ?? "The preview couldn't be loaded." };
    previewFor.value = key;
};
watch(previewKey, () => {
    clearTimeout(timer);
    timer = setTimeout(loadPreview, 250);
}, { immediate: true });
const current = computed(() => (previewFor.value === previewKey.value ? preview.value : null));
const shown = computed(() => current.value ?? preview.value);

const items = [
    { label: 'Basics', anchor: 'name' },
    { label: 'Language', anchor: 'language' },
    { label: 'Notebook format', anchor: 'notebooks' },
    { label: 'Quarto', anchor: 'quarto' },
    { label: 'Jupyter', anchor: 'jupyter' },
    { label: 'Project structure', anchor: 'structure' },
    { label: 'Environment reproducibility', anchor: 'environment' },
    { label: 'Packages', anchor: 'packages' },
    { label: 'Scaffold', anchor: 'scaffold' },
    { label: 'Editor support', anchor: 'editor' },
    { label: 'AI Agents', anchor: 'ai' },
    { label: 'AI Skills', anchor: 'skills' },
    { label: 'Git', anchor: 'git' },
    { label: 'Publishing', anchor: 'publishing' },
];
const activeSection = computed(() => props.section ?? 'name');
const sectionUrl = section => {
    const url = new URL(page.url, window.location.origin);
    url.searchParams.set('tab', section);
    url.hash = '';
    return url.pathname + url.search;
};
// Scripts have no Quarto documents, so no Quarto settings
const navigation = computed(() => items.filter(item => item.anchor !== 'quarto' || workflow.value !== 'scripts').map(item => ({
    label: item.label, href: sectionUrl(item.anchor), preserveState: true, preserveScroll: false, exact: true,
    current: activeSection.value === item.anchor,
})));

</script>

<template>
    <Head :title="editing ? `Edit ${blueprint.name}` : 'New Blueprint'" />

    <SectionLayout heading="Blueprint" :items="navigation" :dirty="form.isDirty" wide>
        <PageHeader :title="editing ? `Edit ${blueprint.name}` : 'New Blueprint'">
            <template #description>
                A blueprint defines a reusable project setup.
                Each save creates a version. Existing projects keep their original version.
            </template>
        </PageHeader>

        <form id="blueprint-form" class="mt-8 flex flex-col gap-8" @submit.prevent="save">
            <UiPanel v-if="activeSection === 'language'" flush title="Language" class="max-w-2xl" description="Each blueprint uses one language.">
                <UiRadioCards v-model="form.language" name="blueprint-language" legend="Language" hide-legend :options="languageOptions" columns="sm:grid-cols-3" />
                <p v-if="form.errors.language" class="text-xs text-red-600 dark:text-red-400">{{ form.errors.language }}</p>
            </UiPanel>
            <UiPanel v-if="activeSection === 'notebooks'" flush title="Notebook format" class="max-w-2xl">
                <p v-if="form.language === 'stata'" class="text-sm text-slate-500 dark:text-slate-400">Stata projects use .do scripts. No notebook setup is needed.</p>
                <template v-else>
                    <UiRadioCards v-model="form.notebook_format" name="blueprint-notebook" legend="Notebook format" hide-legend :options="notebookOptions" />
                    <UiSwitch v-for="tool in notebookTools" v-show="form.notebook_format !== 'scripts'" :key="tool.key" v-model="form.tools[tool.key]" :label="tool.name" :description="tool.description" />
                </template>
                <p v-if="form.errors.workflow || form.errors.workflows || form.errors.notebook_format" class="text-xs text-red-600 dark:text-red-400">{{ form.errors.workflow || form.errors.workflows || form.errors.notebook_format }}</p>
            </UiPanel>
            <UiPanel v-if="activeSection === 'environment'" flush title="Environment reproducibility" class="max-w-2xl">
                <UiSwitch v-if="environmentTool" v-model="form.tools[environmentTool.key]" :label="environmentTool.name" :description="environmentTool.description" />
                <p v-else class="text-sm text-slate-500 dark:text-slate-400">Stata projects have no managed package environment.</p>
                <p v-if="form.errors.tools" class="text-xs text-red-600 dark:text-red-400">{{ form.errors.tools }}</p>
            </UiPanel>
            <UiPanel v-if="activeSection === 'git'" flush title="Git" class="max-w-2xl">
                <UiSwitch v-model="form.settings.git" label="Use git" />
                <fieldset v-if="form.settings.git && form.language !== 'stata'" class="flex flex-col gap-5">
                    <legend class="mb-4 text-sm font-medium text-slate-900 dark:text-white">Commit hooks</legend>
                    <UiSwitch v-for="hook in hooks" :key="hook.key" :model-value="form.settings.hooks.includes(hook.key)" :label="hook.label" :description="hook.description" @update:model-value="toggleHook(hook.key, $event)" />
                </fieldset>
                <p v-else-if="form.settings.git" class="text-sm text-slate-500 dark:text-slate-400">Commit hooks are available for R and Python projects.</p>
                <p v-if="form.errors.settings" class="text-xs text-red-600 dark:text-red-400">{{ form.errors.settings }}</p>
            </UiPanel>
            <ChoicesEditor :form="form" :catalog="catalog" :section="activeSection" :required="requiredTools" :ggplot-themes="ggplotThemes" :quarto-choices="quartoChoices" agent-picker flush>
                <!-- The files and folders a project gets from these choices, with nobody's defaults -->
                <template #structure-preview>
                    <div class="flex flex-col gap-3 border-t border-slate-800/10 pt-6 dark:border-white/15">
                        <div :class="current ? '' : 'opacity-60'" class="flex flex-col gap-3">
                            <p v-if="shown?.problem" class="text-xs text-red-600 dark:text-red-400">{{ shown.problem }}</p>
                            <ProjectTreeView v-else-if="shown?.tree" :entries="shown.tree" />
                            <div v-if="shown?.render_dirs?.length" class="text-xs text-slate-600 dark:text-slate-400">
                                <div class="font-medium text-slate-900 dark:text-white">Render destinations</div>
                                <ul class="mt-1 font-mono">
                                    <li v-for="d in shown.render_dirs" :key="d.from">{{ d.from }}/ → {{ d.to }}/</li>
                                </ul>
                            </div>
                            <p v-if="shown?.sensitive" class="text-xs text-amber-800 dark:text-amber-300">Sensitive data: private folders stay out of Git, and Framework warns before anything private is uploaded or published.</p>
                        </div>
                    </div>
                </template>
            </ChoicesEditor>

            <UiPanel v-if="activeSection === 'name'" flush id="name" title="Basics" class="max-w-2xl scroll-mt-6">
                <UiInput v-model="form.name" label="Name" placeholder="e.g. R01 secondary analysis" required :error="form.errors.name" />
                <UiTextarea v-model="form.description" label="Description" :rows="2" :error="form.errors.description" />
                <UiSelect v-model="form.visibility" label="Visibility" :options="visibilityOptions" :error="form.errors.visibility" />
                <UiInput v-if="editing" v-model="form.notes" label="Version notes (optional)" placeholder="Shown in the version history" :error="form.errors.notes" />

                <div>
                    <button type="button" class="text-sm text-slate-600 hover:underline dark:text-slate-400" @click="showAdvanced = !showAdvanced">
                        {{ showAdvanced ? 'Hide' : 'Show' }} extra settings
                    </button>
                </div>
                <UiTextarea
                    v-if="showAdvanced" v-model="form.extra_yaml" code :rows="8"
                    label="Extra settings (YAML)"
                    description="Anything else for settings.yml, such as scaffold options or connections. Applied on top of the pieces above."
                    :error="form.errors.extra_yaml"
                />
            </UiPanel>
        </form>

        <template #actions>
            <UiButton save-shortcut type="submit" form="blueprint-form" class="w-full" :disabled="form.processing || !form.structure">
                {{ editing ? 'Save new version' : 'Create Blueprint' }}
            </UiButton>
            <p v-if="saved && !form.isDirty" role="status" class="text-center text-sm text-emerald-700 dark:text-emerald-400">Saved.</p>
        </template>
    </SectionLayout>
</template>
