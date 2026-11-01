<script setup>
import { computed, ref, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import SectionLayout from '@/Layouts/SectionLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import UiPanel from '@/Components/UiPanel.vue';
import UiButton from '@/Components/UiButton.vue';
import UiInput from '@/Components/UiInput.vue';
import UiSelect from '@/Components/UiSelect.vue';
import UiTextarea from '@/Components/UiTextarea.vue';
import UiCheckboxCards from '@/Components/UiCheckboxCards.vue';
import ChoicesEditor from '@/Components/ChoicesEditor.vue';
import ProjectTreeView from '@/Components/ProjectTreeView.vue';
import { languageNames, visibilityOptions } from '@/library.js';

const props = defineProps({
    blueprint: Object, // null when creating
    start: Object, // choices to begin from: the version being edited, or ?from=
    catalog: Object,
});

const editing = computed(() => !!props.blueprint);
const quarto = props.catalog.tools.some((t) => t.key === 'quarto');

const form = useForm({
    name: props.blueprint?.name ?? '',
    description: props.blueprint?.description ?? '',
    visibility: props.blueprint?.visibility ?? 'private',
    language: props.start?.language ?? props.blueprint?.language ?? 'r',
    structure: props.start?.structure ?? 'framework/project',
    tools: { ...(props.start?.tools ?? (quarto ? { quarto: true } : {})) },
    package_sets: [...(props.start?.package_sets ?? [])],
    skill_sets: [...(props.start?.skill_sets ?? ['framework/framework'])],
    skills: [...(props.start?.skills ?? [])],
    settings: { sensitive: false, ai: true, git: true, ...(props.start?.settings ?? {}) },
    extra_yaml: props.start?.extra_yaml ?? '',
    languages: [...(props.start?.languages ?? ['r', 'python', 'stata'])],
    workflows: [...(props.start?.workflows ?? ['quarto', 'jupyter', 'scripts'])],
    notes: '',
});
const showAdvanced = ref(!!props.start?.extra_yaml);

const save = () => (editing.value ? form.put(`/blueprints/${props.blueprint.id}`) : form.post('/blueprints'));

// What the blueprint supports. Stata has only Scripts only.
const workflowNames = { quarto: 'Quarto', jupyter: 'Jupyter', scripts: 'Scripts only' };
const fits = (workflow, language) => language !== 'stata' || workflow === 'scripts';
const languageOptions = computed(() => Object.keys(languageNames).map((value) => ({
    value, label: languageNames[value],
    description: value === form.language ? 'The default language, so it stays on.' : null,
    disabled: value === form.language,
})));
const workflowOptions = Object.keys(workflowNames).map((value) => ({
    value, label: workflowNames[value],
    description: value === 'scripts' ? 'The only workflow for Stata.' : null,
}));
watch(() => form.language, (language) => {
    if (!form.languages.includes(language)) form.languages = [...form.languages, language];
});

// The files and folders a project would get, for one language and workflow it supports
const page = usePage();
const previewWorkflow = ref(null);
const workflowChoices = computed(() => form.workflows.filter((w) => fits(w, form.language)));
watch(workflowChoices, (choices) => {
    if (!choices.includes(previewWorkflow.value)) {
        const usual = props.start?.default_workflow?.[form.language] ?? { r: 'quarto', python: 'jupyter', stata: 'scripts' }[form.language];
        previewWorkflow.value = choices.includes(usual) ? usual : (choices[0] ?? null);
    }
}, { immediate: true });
const previewKey = computed(() => (previewWorkflow.value ? JSON.stringify({
    language: form.language, workflow: previewWorkflow.value, structure: form.structure, tools: form.tools,
    package_sets: form.package_sets, skill_sets: form.skill_sets, skills: form.skills, settings: form.settings, extra_yaml: form.extra_yaml,
}) : null));
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
    { label: 'Project structure', anchor: 'structure' },
    { label: 'Languages and workflows', anchor: 'supports' },
    { label: 'Tools', anchor: 'tools' },
    { label: 'Packages', anchor: 'packages' },
    { label: 'Agents', anchor: 'ai' },
    { label: 'Settings', anchor: 'settings' },
    { label: 'Name and share', anchor: 'name' },
];
</script>

<template>
    <Head :title="editing ? `Edit ${blueprint.name}` : 'New blueprint'" />

    <SectionLayout heading="Blueprint" :items="items">
        <PageHeader :title="editing ? `Edit ${blueprint.name}` : 'New blueprint'">
            <template #description>
                A blueprint is a saved set of choices for new projects: a project structure, tools, packages, skills, and settings.
                Saving makes a new version; projects keep the version they were made from.
            </template>
        </PageHeader>

        <form id="blueprint-form" class="mt-8 flex flex-col gap-8" @submit.prevent="save">
            <ChoicesEditor :form="form" :catalog="catalog">
                <!-- The files and folders a project gets from these choices, with nobody's defaults -->
                <template #structure-preview>
                    <div class="flex flex-col gap-3 rounded-md bg-white p-3 dark:bg-slate-900">
                        <UiSelect
                            v-if="workflowChoices.length > 1" v-model="previewWorkflow" label="Preview with"
                            :options="workflowChoices.map((w) => ({ value: w, label: `${languageNames[form.language]}, ${workflowNames[w]}` }))"
                        />
                        <p v-else-if="previewWorkflow" class="text-xs text-slate-500">{{ languageNames[form.language] }}, {{ workflowNames[previewWorkflow] }}</p>
                        <div :class="current ? '' : 'opacity-60'" class="flex flex-col gap-3">
                            <p v-if="shown?.problem" class="text-xs text-red-600 dark:text-red-400">{{ shown.problem }}</p>
                            <ProjectTreeView v-else-if="shown?.tree" :entries="shown.tree" />
                            <div v-if="shown?.render_dirs?.length" class="text-xs text-slate-600 dark:text-slate-400">
                                <div class="font-medium text-slate-900 dark:text-white">Rendered documents go to</div>
                                <ul class="mt-1 font-mono">
                                    <li v-for="d in shown.render_dirs" :key="d.from">{{ d.from }}/ → {{ d.to }}/</li>
                                </ul>
                            </div>
                            <p v-if="shown?.sensitive" class="text-xs text-amber-800 dark:text-amber-300">Sensitive data: private folders stay out of Git, and Framework warns before anything private is uploaded or published.</p>
                        </div>
                        <p class="text-xs text-slate-500">Each person's own defaults, such as their editor and agents, are added when they make a project.</p>
                    </div>
                </template>
                <template #after-structure>
                    <UiPanel id="supports" title="Languages and workflows" description="What projects made from this blueprint can use." class="scroll-mt-6">
                        <UiCheckboxCards v-model="form.languages" legend="Languages" :options="languageOptions" />
                        <p v-if="form.errors.languages" class="text-xs text-red-600 dark:text-red-400">{{ form.errors.languages }}</p>
                        <UiCheckboxCards v-model="form.workflows" legend="Notebook workflows" :options="workflowOptions" />
                        <p v-if="form.errors.workflows" class="text-xs text-red-600 dark:text-red-400">{{ form.errors.workflows }}</p>
                    </UiPanel>
                </template>
            </ChoicesEditor>

            <UiPanel id="name" title="Name and share it" class="scroll-mt-6">
                <UiInput v-model="form.name" label="Name" placeholder="e.g. R01 secondary analysis" required :error="form.errors.name" />
                <UiTextarea v-model="form.description" label="Description" :rows="2" :error="form.errors.description" />
                <UiSelect v-model="form.visibility" label="Who can see it" :options="visibilityOptions" :error="form.errors.visibility" />
                <UiInput v-if="editing" v-model="form.notes" label="What changed (optional)" placeholder="Shown in the version history" :error="form.errors.notes" />

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
                {{ editing ? 'Save new version' : 'Create blueprint' }}
            </UiButton>
            <Link :href="editing ? `/blueprints/${blueprint.id}` : '/blueprints'" class="block"><UiButton variant="ghost" class="w-full">Cancel</UiButton></Link>
        </template>
    </SectionLayout>
</template>
