<script setup>
import { computed, ref } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { ClockIcon, Cog6ToothIcon, DocumentTextIcon, InformationCircleIcon, SparklesIcon } from '@heroicons/vue/24/outline';
import SectionLayout from '@/Layouts/SectionLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import UiPanel from '@/Components/UiPanel.vue';
import GitHubMark from '@/Components/GitHubMark.vue';
import UiButton from '@/Components/UiButton.vue';
import UiCallout from '@/Components/UiCallout.vue';
import GitHubPublish from '@/Components/GitHubPublish.vue';
import UiInput from '@/Components/UiInput.vue';
import UiSelect from '@/Components/UiSelect.vue';
import UiSwitch from '@/Components/UiSwitch.vue';
import UiTextarea from '@/Components/UiTextarea.vue';
import { visibilityOptions } from '@/library.js';

const props = defineProps({
    facet: Object, // null when creating
    kind: String, // base | facet
    values: Object,
    categories: Object,
    library: Array,
});

const page = usePage();
const saved = computed(() => ['facet-saved', 'facet-forked'].includes(page.props.flash.status));
const isBase = computed(() => props.kind === 'base');
const noun = computed(() => (isBase.value ? 'project type' : 'facet'));

const form = useForm({
    kind: props.kind,
    name: props.facet?.name ?? '',
    description: props.facet?.description ?? '',
    category: props.facet?.category ?? 'data',
    question: props.facet?.question ?? '',
    visibility: props.facet?.visibility ?? 'private',
    settings_yaml: props.values.settings_yaml,
    files: props.values.files.map((f) => ({ ...f })),
    skills: props.values.skills.map((s) => ({ ...s, skill_id: null })),
    notes: '',
});

const categoryOptions = computed(() => Object.entries(props.categories).map(([value, label]) => ({ value, label })));

const addFile = () => form.files.push({ path: '', content: '', template: false });
const removeFile = (index) => form.files.splice(index, 1);

const pick = ref('');
const libraryOptions = computed(() => [
    { value: '', label: 'Add a skill…' },
    ...props.library
        .filter((s) => !form.skills.some((chosen) => chosen.name === s.key))
        .map((s) => ({ value: s.id, label: `${s.key}${s.is_system ? '' : ` (${s.handle})`}` })),
]);
const addSkill = () => {
    const skill = props.library.find((s) => s.id === Number(pick.value));
    if (skill) form.skills.push({ name: skill.key, skill_id: skill.id, entry: null });
    pick.value = '';
};
const removeSkill = (index) => form.skills.splice(index, 1);
const skillSource = (skill) => {
    if (skill.skill_id) return 'from Skills';
    if (skill.entry?.package) return 'the copy installed with Framework';
    if (skill.entry === null) return 'removes this skill';
    return 'custom copy';
};

const save = () => (props.facet ? form.put(`/facets/${props.facet.id}`, { preserveScroll: true }) : form.post('/facets'));
const destroy = () => {
    if (confirm(`Delete ${props.facet.name}? This can't be undone.`)) router.delete(`/facets/${props.facet.id}`);
};
const canPublish = computed(() => !!props.facet && !!page.props.githubSignIn);
const items = computed(() => [
    { label: 'Details', anchor: 'details', icon: InformationCircleIcon },
    { label: 'Settings', anchor: 'settings', icon: Cog6ToothIcon },
    { label: 'Files', anchor: 'files', icon: DocumentTextIcon },
    { label: 'Skills', anchor: 'skills', icon: SparklesIcon },
    ...(props.facet ? [{ label: 'Versions', anchor: 'versions', icon: ClockIcon }] : []),
    ...(canPublish.value ? [{ label: 'Publish to GitHub', anchor: 'publish', icon: GitHubMark }] : []),
]);
const errorFor = (prefix) => Object.entries(form.errors).filter(([k]) => k.startsWith(prefix)).map(([, v]) => v).join(' ');
</script>

<template>
    <Head :title="facet ? `Edit ${facet.name}` : `New ${noun}`" />

    <SectionLayout :heading="isBase ? 'Project type' : 'Facet'" :items="items">
        <Link href="/facets" class="text-sm text-slate-500 hover:underline">Facets</Link>
        <PageHeader :title="facet ? `Edit ${facet.name}` : `New ${noun}`">
            <template #description>
                <template v-if="isBase">A project type is the starting layer of a blueprint. It sets the type and the core folders.</template>
                <template v-else>A facet holds only what it adds. Blueprints that use it apply it after the project type, in order.</template>
                Saving makes a new version; blueprints keep the version they chose until their owner updates.
            </template>
        </PageHeader>
        <div v-if="facet" class="mt-2 font-mono text-xs text-slate-500">{{ facet.handle }} · v{{ facet.version }}</div>

        <UiCallout v-if="saved" variant="success" heading="Saved" class="mt-8">Version {{ facet?.version }} is ready to use in blueprints.</UiCallout>

        <form id="facet-form" class="mt-8 flex flex-col gap-8" @submit.prevent="save">
            <UiPanel id="details" title="Details" class="scroll-mt-6">
                <UiInput v-model="form.name" label="Name" required :error="form.errors.name" />
                <UiTextarea v-model="form.description" label="Description" :rows="2" :error="form.errors.description" />
                <template v-if="!isBase">
                    <UiSelect v-model="form.category" label="Section in the builder" :options="categoryOptions" :error="form.errors.category" />
                    <UiInput
                        v-model="form.question" label="Builder question"
                        placeholder="e.g. Will this project produce a paper?"
                        description="The yes-or-no question the builder shows for this facet."
                        :error="form.errors.question"
                    />
                </template>
                <UiSelect v-model="form.visibility" label="Who can see it" :options="visibilityOptions" :error="form.errors.visibility" />
            </UiPanel>

            <UiPanel id="settings" title="Settings" class="scroll-mt-6">
                <UiTextarea
                    v-model="form.settings_yaml" code :rows="14"
                    label="Settings (YAML)"
                    description="Same keys as settings.yml: directories, extra_directories, render_dirs, packages, git, ai, scaffold, quarto, connections, env. Set a key to null to remove it. Credentials must be env(&quot;VARIABLE&quot;) references."
                    :error="form.errors.settings_yaml"
                />
            </UiPanel>

            <UiPanel id="files" title="Files" class="scroll-mt-6">
                <template #header>
                    <UiButton size="sm" variant="outline" @click="addFile">Add file</UiButton>
                </template>
                <p class="-mt-4 text-sm text-slate-600 dark:text-slate-400">Text files Framework writes into the project, such as a manuscript template. Templates fill in placeholders like <code class="font-mono">&#123;&#123;author&#125;&#125;</code>.</p>
                <div
                    v-for="(file, index) in form.files" :key="index"
                    class="flex flex-col gap-3 rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900"
                >
                    <div class="flex items-end gap-3">
                        <div class="flex-1"><UiInput v-model="file.path" label="Path" placeholder="manuscript/manuscript.qmd" /></div>
                        <UiButton size="sm" variant="ghost" @click="removeFile(index)">Remove</UiButton>
                    </div>
                    <UiSwitch v-model="file.template" label="Fill in placeholders" />
                    <UiTextarea v-model="file.content" code :rows="6" />
                </div>
                <p v-if="errorFor('files')" class="text-xs text-red-600 dark:text-red-400">{{ errorFor('files') }}</p>
            </UiPanel>

            <UiPanel
                id="skills" title="Skills" class="scroll-mt-6"
                description="Instructions an AI assistant loads when a task matches. Framework installs them where the project's assistants read them."
            >
                <div class="flex flex-col divide-y divide-slate-200 rounded-lg border border-slate-200 bg-white empty:hidden dark:divide-slate-700 dark:border-slate-700 dark:bg-slate-900">
                    <div v-for="(skill, index) in form.skills" :key="skill.name" class="flex items-center justify-between gap-3 px-3 py-2">
                        <div>
                            <span class="font-mono text-sm">{{ skill.name }}</span>
                            <span class="text-xs text-slate-500"> · {{ skillSource(skill) }}</span>
                        </div>
                        <UiButton size="sm" variant="ghost" @click="removeSkill(index)">Remove</UiButton>
                    </div>
                </div>
                <div class="flex items-end gap-2">
                    <div class="flex-1"><UiSelect v-model="pick" :options="libraryOptions" /></div>
                    <UiButton variant="outline" :disabled="!pick" @click="addSkill">Add</UiButton>
                </div>
                <p v-if="errorFor('skills')" class="text-xs text-red-600 dark:text-red-400">{{ errorFor('skills') }}</p>
            </UiPanel>

            <UiPanel v-if="facet" id="versions" title="Versions" class="scroll-mt-6">
                <UiInput v-model="form.notes" label="What changed (optional)" placeholder="Shown in the version history" :error="form.errors.notes" />
                <div v-if="facet.versions?.length" class="flex flex-col gap-1">
                    <div v-for="v in [...facet.versions].reverse()" :key="v.id" class="text-sm">
                        <span class="font-mono">v{{ v.version }}</span>
                        <span class="text-slate-500"> · {{ new Date(v.created_at).toLocaleDateString() }}</span>
                        <span v-if="v.notes" class="text-slate-500"> · {{ v.notes }}</span>
                    </div>
                </div>
                <p v-if="facet.source_url" class="text-sm text-slate-500">
                    Imported from <a :href="facet.source_url" class="underline">{{ facet.source_url }}</a>
                </p>
            </UiPanel>

            <UiPanel v-if="canPublish || page.props.errors.github" id="publish" class="scroll-mt-6">
                <p v-if="page.props.errors.github" class="text-sm text-red-600 dark:text-red-400">{{ page.props.errors.github }}</p>
                <GitHubPublish v-if="facet" :action="`/facets/${facet.id}/publish`" :path="`facets/${facet.key}.facet.yml`" />
            </UiPanel>
        </form>

        <template #actions>
            <UiButton save-shortcut type="submit" form="facet-form" class="w-full" :disabled="form.processing">{{ facet ? 'Save new version' : `Create ${noun}` }}</UiButton>
            <UiButton v-if="facet && !facet.is_system" variant="ghost" class="w-full" @click="destroy">Delete</UiButton>
            <p v-if="page.props.errors.facet" class="text-xs text-red-600 dark:text-red-400">{{ page.props.errors.facet }}</p>
        </template>
    </SectionLayout>
</template>
