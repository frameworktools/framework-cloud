<script setup>
import StructureLocationsEditor from '@/Components/StructureLocationsEditor.vue';
import StructureFoldersEditor from '@/Components/StructureFoldersEditor.vue';
import ProjectTreeView from '@/Components/ProjectTreeView.vue';
import { ask } from '@/confirm.js';
import { computed, ref } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import SectionLayout from '@/Layouts/SectionLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import UiPanel from '@/Components/UiPanel.vue';
import UiButton from '@/Components/UiButton.vue';
import UiCallout from '@/Components/UiCallout.vue';
import UiInput from '@/Components/UiInput.vue';
import UiSelect from '@/Components/UiSelect.vue';
import UiRadioCards from '@/Components/UiRadioCards.vue';
import UiSwitch from '@/Components/UiSwitch.vue';
import UiGitignoreEditor from '@/Components/UiGitignoreEditor.vue';
import EmptyState from '@/Components/EmptyState.vue';
import PackagesEditor from '@/Components/PackagesEditor.vue';
import UiTextarea from '@/Components/UiTextarea.vue';
import { fitsLanguage, languageOptions as languageCardOptions, visibilityOptions } from '@/library.js';
import { pieceCopy } from '@/pieces.js';

const props = defineProps({
    kind: String,
    variant: String,
    path: String,
    facet: Object, // null when creating
    values: Object,
    library: Array,
    language: String,
});

const page = usePage();
// A copy made by Copy and edit that hasn't been saved yet can be discarded
const freshCopy = computed(() => new URL(page.url, window.location.origin).searchParams.has('copy'));
const discardCopy = () => router.delete(`/${props.path}/${props.facet.id}`);
const copy = computed(() => pieceCopy[props.kind]);
const saved = computed(() => ['facet-saved', 'facet-forked', 'facet-imported'].includes(page.props.flash.status));
const isStructure = computed(() => props.kind === 'structure');
const isPackageSet = computed(() => props.kind === 'package_set');
const paged = computed(() => isStructure.value || isPackageSet.value);
const isSkillSet = computed(() => props.kind === 'skill_set');

const blankPackage = (language) => ({ name: '', source: language === 'stata' ? 'manual' : language === 'python' ? 'pypi' : 'cran', version: null, repo: null, auto_attach: false });
const form = useForm({
    variant: props.variant,
    name: props.facet?.name ?? '',
    description: props.facet?.description ?? '',
    visibility: props.facet?.visibility ?? 'private',
    language: props.variant ?? props.facet?.language?.[0] ?? props.language ?? 'r',
    requirements: props.facet?.requirements ?? '',
    settings_yaml: props.values.settings_yaml,
    folders: props.values.folders ?? [],
    framework_locations: props.values.framework_locations ?? {},
    gitignore_content: props.values.gitignore_content ?? '',
    packages: props.values.packages,
    package_rows: props.values.package_rows?.length ? props.values.package_rows : !props.facet && props.kind === 'package_set' ? [blankPackage(props.language ?? 'r')] : [],
    files: props.values.files.map((f) => ({ ...f })),
    skills: props.values.skills.map((s) => ({ ...s, skill_id: null })),
    notes: '',
});

const changeLanguage = async (language) => {
    if (isPackageSet.value && language !== form.language) {
        if (form.package_rows.some(row => row.name || row.repo) && !await ask('Replace the package list for the new language? The current list will be cleared.', { action: 'Change language' })) return;
        form.package_rows = [blankPackage(language)];
    }
    form.language = language;
};
const languageOptions = [
    { value: 'r', label: 'R' },
    { value: 'python', label: 'Python' },
    ...(['skill_set', 'package_set'].includes(props.kind) ? [{ value: 'stata', label: 'Stata' }] : []),
];

const addFile = () => form.files.push({ path: '', content: '', template: false });
const removeFile = (index) => form.files.splice(index, 1);

const pick = ref('');
const skillOptions = computed(() => [
    { value: '', label: 'Add a skill…' },
    ...props.library
        .filter((s) => fitsLanguage(s.language, form.language) && !form.skills.some((chosen) => chosen.name === s.key))
        .map((s) => ({ value: s.id, label: `${s.name}${s.is_system ? '' : ` (${s.handle})`}` })),
]);
const addSkill = () => {
    const skill = props.library.find((s) => s.id === Number(pick.value));
    if (skill) form.skills.push({ name: skill.key, skill_id: skill.id, entry: null });
    pick.value = '';
};
const removeSkill = (index) => form.skills.splice(index, 1);
const skillTitle = (skill) => props.library.find((s) => s.key === skill.name)?.name ?? skill.name;

const save = () => (props.facet ? form.put(`/${props.path}/${props.facet.id}`, { preserveScroll: true }) : form.post(`/${props.path}`));
const destroy = async () => {
    if (await ask(`Delete ${props.facet.name}? This can't be undone.`)) router.delete(`/${props.path}/${props.facet.id}`);
};

const structureTabs = [
    ['details', 'Basics'], ['folders', 'Folders'], ['locations', 'Framework locations'],
    ['files', 'Starter files'], ['gitignore', 'Git ignore'], ['preview', 'Preview'],
    ...(props.facet ? [['versions', 'Versions']] : []),
];
const tabs = computed(() => isStructure.value ? structureTabs : [['details', 'Basics'], ['packages', 'Packages'], ...(props.facet ? [['versions', 'Versions']] : [])]);
const activeTab = computed(() => {
    const tab = new URL(page.url, window.location.origin).searchParams.get('tab');
    return tabs.value.some(([key]) => key === tab) ? tab : 'details';
});
const visible = (tab) => !paged.value || activeTab.value === tab;
const previewEntries = computed(() => [
    ...form.folders.map((folder) => ({ path: folder.path, type: 'dir' })),
    ...form.files.filter((file) => file.path).map((file) => ({ path: file.path, type: 'file' })),
]);
const items = computed(() => paged.value ? tabs.value.map(([key, label]) => ({
    label, href: `${page.url.split('?')[0]}?tab=${key}`, current: activeTab.value === key, preserveState: true, preserveScroll: false,
})) : [
    { label: 'Details', anchor: 'details' },
    ...(isStructure.value ? [
        { label: 'Folders and settings', anchor: 'settings' },
        { label: '.gitignore', anchor: 'gitignore' },
        { label: 'Files', anchor: 'files' },
    ] : []),
    ...(isSkillSet.value ? [{ label: 'Skills', anchor: 'skills' }] : []),
    ...(!isStructure.value ? [{ label: 'Packages', anchor: 'packages' }] : []),
    ...(props.facet ? [{ label: 'Versions', anchor: 'versions' }] : []),
]);
const errorFor = (prefix) => Object.entries(form.errors).filter(([k]) => k.startsWith(prefix)).map(([, v]) => v).join(' ');
</script>

<template>
    <Head :title="facet ? `Edit ${facet.name}` : `New ${copy.noun}`" />

    <SectionLayout :heading="copy.title" :items="items" :wide="paged" :dirty="form.isDirty">
        <Link :href="`/${path}`" class="text-sm text-slate-500 dark:text-slate-400 hover:underline">{{ copy.title }}</Link>
        <PageHeader :title="facet ? `Edit ${facet.name}` : `New ${copy.noun}`">
            <template #description>
                {{ isPackageSet ? 'Packages included in this set.' : copy.description }}
                Saving makes a new version. Blueprints and projects keep the version they chose.
            </template>
        </PageHeader>
        <div v-if="facet" class="mt-2 font-mono text-xs text-slate-500 dark:text-slate-400">{{ facet.handle }} · v{{ facet.version }}</div>

        <p v-if="saved" role="status" class="mt-4 text-sm text-slate-500 dark:text-slate-400">Saved · Version {{ facet?.version }}</p>
        <UiCallout v-else-if="facet && freshCopy" heading="New copy" class="mt-8">
            <span class="flex flex-wrap items-center gap-3">Save a version to keep it. <UiButton variant="ghost" size="sm" @click="discardCopy">Discard copy</UiButton></span>
        </UiCallout>

        <p v-if="isStructure && Object.keys(form.errors).length" role="alert" class="mt-6 text-sm text-red-600">{{ Object.values(form.errors).join(' ') }}</p>
        <form id="piece-form" class="mt-8 flex flex-col gap-8" @submit.prevent="save">
            <UiPanel v-if="visible('details')" :flush="paged" id="details" :title="paged ? 'Basics' : 'Details'" class="scroll-mt-6">
                <UiInput v-model="form.name" label="Name" :placeholder="isPackageSet ? 'Survey analysis' : isStructure ? 'Research project' : 'Data cleaning'" required :error="form.errors.name" />
                <UiTextarea v-model="form.description" label="Description" :placeholder="isPackageSet ? 'Packages for survey analysis and reporting' : isStructure ? 'Folder layout for research projects' : 'Tasks these skills cover'" :rows="2" :error="form.errors.description" />
                <UiRadioCards v-if="isPackageSet" :model-value="form.language" name="package-set-language" legend="Language" :options="languageCardOptions" columns="sm:grid-cols-3" @update:model-value="changeLanguage" />
                <p v-if="isPackageSet && form.errors.language" role="alert" class="text-sm text-red-600 dark:text-red-400">{{ form.errors.language }}</p>
                <UiSelect v-if="!isStructure && !isPackageSet" :model-value="form.language" @update:model-value="changeLanguage" :disabled="!!variant" label="Language" :options="languageOptions" :error="form.errors.language" />
                <div>
                    <UiRadioCards v-model="form.visibility" name="visibility" legend="Visibility" :options="visibilityOptions" stacked />
                    <p v-if="form.errors.visibility" role="alert" class="mt-2 text-sm text-red-600 dark:text-red-400">{{ form.errors.visibility }}</p>
                </div>
            </UiPanel>

            <template v-if="isStructure">
                <UiPanel v-if="visible('folders')" flush title="Folders" description="Name and arrange folders for your project. Data and report folders can use any convention.">
                    <StructureFoldersEditor v-model:folders="form.folders" v-model:locations="form.framework_locations" v-model:files="form.files" />
                    <p v-if="errorFor('folders')" role="alert" class="text-sm text-red-600">{{ errorFor('folders') }}</p>
                </UiPanel>
                <UiPanel v-if="visible('locations')" flush title="Framework locations" description="Choose the folders Framework uses for shared functions and cached results.">
                    <StructureLocationsEditor v-model="form.framework_locations" :folders="form.folders" :errors="form.errors" />
                    <div class="max-w-2xl">
                        <details><summary class="cursor-pointer text-sm font-medium">Advanced settings</summary>
                            <UiTextarea v-model="form.settings_yaml" code :rows="14" class="mt-4" label="Settings (YAML)" :placeholder="'type: project\nscaffold:\n  seed: 123'" description="Preserves existing settings and legacy folder roles. Folder controls take precedence over directory paths." :error="form.errors.settings_yaml" />
                        </details>
                    </div>
                </UiPanel>
                <UiPanel v-if="visible('preview')" flush title="Structure preview" description="Folders and starter files. Blueprints add language, notebook, environment, and agent files.">
                    <ProjectTreeView :entries="previewEntries" />
                </UiPanel>

                <UiPanel v-if="visible('gitignore')" flush id="gitignore" title=".gitignore" class="scroll-mt-6">
                    <UiGitignoreEditor
                        v-model="form.gitignore_content" label="Ignore rules"
                        description="Keep private data, credentials, and local files out of Git. These rules follow the package defaults. Add one pattern per line; use ! to override a default. Existing tracked files stay tracked."
                        :error="form.errors.gitignore_content"
                    />
                </UiPanel>

                <UiPanel v-if="visible('files')" flush id="files" title="Starter files" description="Files included in each new project, such as a README or analysis script." class="scroll-mt-6">
                    <template v-if="form.files.length" #header>
                        <UiButton size="sm" variant="outline" @click="addFile">Add file</UiButton>
                    </template>
                    <EmptyState v-if="!form.files.length" compact title="No starter files" description="Add a file and its contents to this structure.">
                        <template #actions><UiButton size="sm" variant="outline" @click="addFile">Add file</UiButton></template>
                    </EmptyState>
                    <div
                        v-for="(file, index) in form.files" :key="index"
                        class="flex flex-col gap-3 rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900"
                    >
                        <div class="flex items-end gap-3">
                            <div class="flex-1"><UiInput v-model="file.path" label="Path" placeholder="README.md" /></div>
                            <UiButton size="sm" variant="ghost" @click="removeFile(index)">Remove</UiButton>
                        </div>
                        <UiSwitch v-model="file.template" label="Fill in placeholders" description="Replace placeholders such as {{author}} with project values." />
                        <UiTextarea v-model="file.content" code :rows="6" aria-label="File contents" placeholder="Enter file contents" />
                    </div>
                    <p v-if="form.files.length" class="text-xs text-slate-500 dark:text-slate-400">Framework generates AGENTS.md from project settings and skills. An AGENTS.md starter file replaces it.</p>
                    <p v-if="errorFor('files')" class="text-xs text-red-600 dark:text-red-400">{{ errorFor('files') }}</p>
                </UiPanel>
            </template>

            <UiPanel
                v-if="isSkillSet" id="skills" title="Skills" class="scroll-mt-6"
                description="Instructions an AI assistant loads when a task matches."
            >
                <div class="flex flex-col divide-y divide-slate-200 rounded-lg border border-slate-200 bg-white empty:hidden dark:divide-slate-700 dark:border-slate-700 dark:bg-slate-900">
                    <div v-for="(skill, index) in form.skills" :key="skill.name" class="flex items-center justify-between gap-3 px-3 py-2">
                        <div class="min-w-0">
                            <div class="text-sm font-medium text-slate-900 dark:text-white">{{ skillTitle(skill) }}</div>
                            <div class="font-mono text-xs text-slate-500 dark:text-slate-400">{{ skill.name }}</div>
                        </div>
                        <UiButton size="sm" variant="ghost" @click="removeSkill(index)">Remove</UiButton>
                    </div>
                </div>
                <div class="flex items-end gap-2">
                    <div class="flex-1"><UiSelect v-model="pick" :options="skillOptions" /></div>
                    <UiButton variant="outline" :disabled="!pick" @click="addSkill">Add</UiButton>
                </div>
                <p v-if="errorFor('skills')" class="text-xs text-red-600 dark:text-red-400">{{ errorFor('skills') }}</p>
            </UiPanel>

            <UiPanel
                v-if="!isStructure && visible('packages')" :flush="isPackageSet" id="packages" title="Packages" class="scroll-mt-6"
                :description="isSkillSet ? 'Packages these skills need. Projects that use the skill set get them.' : form.language === 'stata' ? 'Package names for reference. Projects record this list in .framework/packages/. Install these packages manually.' : 'Search for packages and choose their installation source.'"
            >
                <PackagesEditor v-if="isPackageSet" v-model="form.package_rows" :language="form.language" :attachment="false" />
                <p v-if="isPackageSet && errorFor('package_rows')" role="alert" class="text-sm text-red-600">{{ errorFor('package_rows') }}</p>
                <UiTextarea v-if="!isPackageSet" v-model="form.packages" code :rows="8" aria-label="Packages" :placeholder="form.language === 'python' ? 'pandas\nmatplotlib' : 'dplyr\nggplot2'" :error="form.errors.packages" />
                <UiTextarea
                    v-if="kind === 'package_set'" v-model="form.requirements" :rows="2" label="Requirements (optional)" placeholder="Java or a C++ compiler"
                    description="Anything the packages need that Framework doesn't install, such as Java or a C++ compiler."
                    :error="form.errors.requirements"
                />
            </UiPanel>

            <UiPanel v-if="facet && visible('versions')" :flush="paged" id="versions" title="Versions" class="scroll-mt-6">
                <UiInput v-model="form.notes" label="Version notes (optional)" placeholder="Summarize changes in this version" :error="form.errors.notes" />
                <div v-if="facet.versions?.length" class="flex flex-col gap-1">
                    <div v-for="v in [...facet.versions].reverse()" :key="v.id" class="text-sm">
                        <span class="font-mono">v{{ v.version }}</span>
                        <span class="text-slate-500 dark:text-slate-400"> · {{ new Date(v.created_at).toLocaleDateString() }}</span>
                        <span v-if="v.notes" class="text-slate-500 dark:text-slate-400"> · {{ v.notes }}</span>
                    </div>
                </div>
            </UiPanel>
        </form>

        <template #actions>
            <UiButton save-shortcut type="submit" form="piece-form" class="w-full" :disabled="form.processing">{{ facet ? 'Save new version' : `Create ${copy.noun}` }}</UiButton>
            <UiButton v-if="facet && !facet.is_system" variant="ghost" class="w-full" @click="destroy">Delete</UiButton>
            <p v-if="page.props.errors.facet" class="text-xs text-red-600 dark:text-red-400">{{ page.props.errors.facet }}</p>
        </template>
    </SectionLayout>
</template>
