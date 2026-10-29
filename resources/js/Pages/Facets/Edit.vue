<script setup>
import { computed, ref } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
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
    { value: '', label: 'Add a skill from the library…' },
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
    if (skill.skill_id) return 'from the library';
    if (skill.entry?.package) return 'the copy installed with Framework';
    if (skill.entry === null) return 'removes this skill';
    return 'custom copy';
};

const save = () => (props.facet ? form.put(`/facets/${props.facet.id}`, { preserveScroll: true }) : form.post('/facets'));
const destroy = () => {
    if (confirm(`Delete ${props.facet.name}? This can't be undone.`)) router.delete(`/facets/${props.facet.id}`);
};
const errorFor = (prefix) => Object.entries(form.errors).filter(([k]) => k.startsWith(prefix)).map(([, v]) => v).join(' ');
</script>

<template>
    <Head :title="facet ? `Edit ${facet.name}` : `New ${noun}`" />

    <AppLayout>
        <form class="flex max-w-3xl flex-col gap-8" @submit.prevent="save">
            <div>
                <Link href="/facets" class="text-sm text-neutral-500 hover:underline">Facets</Link>
                <h1 class="text-2xl font-semibold tracking-tight">{{ facet ? `Edit ${facet.name}` : `New ${noun}` }}</h1>
                <div v-if="facet" class="font-mono text-xs text-neutral-500">{{ facet.handle }} · v{{ facet.version }}</div>
                <p class="mt-1 text-neutral-600 dark:text-neutral-400">
                    <template v-if="isBase">A project type is the starting layer of a blueprint. It sets the type and the core folders.</template>
                    <template v-else>A facet holds only what it adds. Blueprints that use it apply it after the project type, in order.</template>
                    Saving makes a new version; blueprints keep the version they chose until their owner updates.
                </p>
            </div>

            <UiCallout v-if="saved" variant="success" heading="Saved">Version {{ facet?.version }} is ready to use in blueprints.</UiCallout>

            <section class="flex flex-col gap-4">
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
            </section>

            <section class="flex flex-col gap-2">
                <UiTextarea
                    v-model="form.settings_yaml" code :rows="14"
                    label="Settings (YAML)"
                    description="Same keys as settings.yml: directories, extra_directories, render_dirs, packages, git, ai, scaffold, quarto, connections, env. Set a key to null to remove it. Credentials must be env(&quot;VARIABLE&quot;) references."
                    :error="form.errors.settings_yaml"
                />
            </section>

            <section class="flex flex-col gap-3">
                <div class="flex items-baseline justify-between">
                    <h2 class="font-medium">Files</h2>
                    <UiButton size="sm" variant="outline" @click="addFile">Add file</UiButton>
                </div>
                <p class="text-sm text-neutral-500">Text files Framework writes into the project, such as a manuscript template. Templates fill in placeholders like <code class="font-mono">&#123;&#123;author&#125;&#125;</code>.</p>
                <div v-for="(file, index) in form.files" :key="index" class="flex flex-col gap-2 rounded-lg border border-neutral-200 p-3 dark:border-neutral-800">
                    <div class="flex items-end gap-3">
                        <div class="flex-1"><UiInput v-model="file.path" label="Path" placeholder="manuscript/manuscript.qmd" /></div>
                        <UiButton size="sm" variant="ghost" @click="removeFile(index)">Remove</UiButton>
                    </div>
                    <UiSwitch v-model="file.template" label="Fill in placeholders" />
                    <UiTextarea v-model="file.content" code :rows="6" />
                </div>
                <p v-if="errorFor('files')" class="text-xs text-red-600 dark:text-red-400">{{ errorFor('files') }}</p>
            </section>

            <section class="flex flex-col gap-3">
                <h2 class="font-medium">Skills</h2>
                <p class="text-sm text-neutral-500">
                    Instructions an AI assistant loads when a task matches. Framework installs them where the project's assistants read them.
                </p>
                <div class="flex flex-col divide-y divide-neutral-200 rounded-lg border border-neutral-200 empty:hidden dark:divide-neutral-800 dark:border-neutral-800">
                    <div v-for="(skill, index) in form.skills" :key="skill.name" class="flex items-center justify-between gap-3 px-3 py-2">
                        <div>
                            <span class="font-mono text-sm">{{ skill.name }}</span>
                            <span class="text-xs text-neutral-500"> · {{ skillSource(skill) }}</span>
                        </div>
                        <UiButton size="sm" variant="ghost" @click="removeSkill(index)">Remove</UiButton>
                    </div>
                </div>
                <div class="flex items-end gap-2">
                    <div class="flex-1"><UiSelect v-model="pick" :options="libraryOptions" /></div>
                    <UiButton variant="outline" :disabled="!pick" @click="addSkill">Add</UiButton>
                </div>
                <p v-if="errorFor('skills')" class="text-xs text-red-600 dark:text-red-400">{{ errorFor('skills') }}</p>
            </section>

            <section class="flex flex-col gap-4">
                <UiInput v-if="facet" v-model="form.notes" label="What changed (optional)" placeholder="Shown in the version history" :error="form.errors.notes" />
                <div class="flex gap-2">
                    <UiButton type="submit" :disabled="form.processing">{{ facet ? 'Save new version' : `Create ${noun}` }}</UiButton>
                    <UiButton v-if="facet && !facet.is_system" variant="ghost" @click="destroy">Delete</UiButton>
                </div>
                <p v-if="page.props.errors.facet" class="text-xs text-red-600 dark:text-red-400">{{ page.props.errors.facet }}</p>
            </section>

            <p v-if="page.props.errors.github" class="text-sm text-red-600 dark:text-red-400">{{ page.props.errors.github }}</p>
            <p v-if="facet?.source_url" class="text-sm text-neutral-500">
                Imported from <a :href="facet.source_url" class="underline">{{ facet.source_url }}</a>
            </p>
            <GitHubPublish v-if="facet" :action="`/facets/${facet.id}/publish`" :path="`facets/${facet.key}.facet.yml`" />

            <section v-if="facet?.versions?.length" class="flex flex-col gap-1">
                <h2 class="text-sm font-medium">Versions</h2>
                <div v-for="v in [...facet.versions].reverse()" :key="v.id" class="text-sm">
                    <span class="font-mono">v{{ v.version }}</span>
                    <span class="text-neutral-500"> · {{ new Date(v.created_at).toLocaleDateString() }}</span>
                    <span v-if="v.notes" class="text-neutral-500"> · {{ v.notes }}</span>
                </div>
            </section>
        </form>
    </AppLayout>
</template>
