<script setup>
import { computed } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import SectionLayout from '@/Layouts/SectionLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import UiTable from '@/Components/UiTable.vue';
import UiPanel from '@/Components/UiPanel.vue';
import GitHubMark from '@/Components/GitHubMark.vue';
import UiButton from '@/Components/UiButton.vue';
import UiCallout from '@/Components/UiCallout.vue';
import GitHubPublish from '@/Components/GitHubPublish.vue';
import UiInput from '@/Components/UiInput.vue';
import UiSelect from '@/Components/UiSelect.vue';
import UiTextarea from '@/Components/UiTextarea.vue';
import { visibilityOptions } from '@/library.js';

const props = defineProps({ skill: Object, content: String, variant: String, language: String, tagOptions: Array });

const page = usePage();
const saved = computed(() => ['skill-saved', 'skill-forked'].includes(page.props.flash.status));
const readOnly = computed(() => props.skill && !props.skill.editable);

const form = useForm({
    content: props.content,
    visibility: props.skill?.visibility ?? 'private',
    // One language, or empty for any
    language: props.skill ? (props.skill.language?.length === 1 ? props.skill.language[0] : '') : props.language,
    tags: props.skill?.tags?.map(tag => tag.id) ?? [],
    notes: '',
    expected_version: props.skill?.version,
    variant: props.variant ?? 'r',
});
const languageOptions = [
    { value: '', label: 'Any language' },
    { value: 'r', label: 'R only' },
    { value: 'python', label: 'Python only' },
    { value: 'stata', label: 'Stata only' },
];

const canPublish = computed(() => !!props.skill && !!page.props.githubSignIn);
const items = computed(() => [
    { label: 'SKILL.md', anchor: 'content' },
    ...(!readOnly.value ? [{ label: 'Details', anchor: 'details' }] : []),
    ...(canPublish.value ? [{ label: 'GitHub publishing', anchor: 'publish' }] : []),
]);

const save = () => (props.skill ? form.put(`/skills/${props.skill.id}`, { preserveScroll: true, onSuccess: () => { form.expected_version = props.skill.version; } }) : form.post('/skills'));
const restore = (version) => {
    if (confirm(`Restore version ${version} as a new version?`)) {
        router.post(`/skills/${props.skill.id}/restore`, { version, expected_version: props.skill.version }, {
            preserveScroll: true,
            onSuccess: () => {
                form.content = props.content;
                form.expected_version = props.skill.version;
                form.notes = '';
            },
        });
    }
};
const fork = () => router.post(`/skills/${props.skill.id}/fork`);
const destroy = () => {
    if (confirm(`Delete ${props.skill.name}? Skill sets and blueprints that already copied it keep their copy.`)) router.delete(`/skills/${props.skill.id}`);
};
</script>

<template>
    <Head :title="skill ? skill.name : 'New skill'" />

    <SectionLayout heading="Skill" :items="items">
        <Link href="/skills" class="text-sm text-slate-500 hover:underline">Skills</Link>
        <PageHeader :title="skill ? skill.name : 'New skill'">
            <template #description>
                <code class="font-mono">name</code> identifies the skill to assistants.
                <code class="font-mono">description</code> tells assistants when to load the skill.
                <code class="font-mono">metadata.title</code> sets its display name.
                <template v-if="skill?.is_system"> Framework skills use the canonical copy for each environment.</template>
            </template>
        </PageHeader>
        <p v-if="skill?.parent" class="mt-3 text-sm text-slate-500">Forked from <Link :href="`/skills/${skill.parent.id}`" class="underline">{{ skill.parent.name }}</Link>, version {{ skill.parent.version }}.</p>
        <div v-if="skill" class="mt-2 font-mono text-xs text-slate-500">{{ skill.handle }} · v{{ skill.version }}</div>

        <UiCallout v-if="saved" variant="success" heading="Saved" class="mt-8">Version {{ skill?.version }} is saved.</UiCallout>

        <form id="skill-form" class="mt-8 flex flex-col gap-8" @submit.prevent="save">
            <UiPanel id="content" class="scroll-mt-6">
                <UiSelect v-if="skill?.language?.length > 1" v-model="form.variant" label="Content language" :options="skill.language.map(value => ({ value, label: value === 'r' ? 'R' : value === 'python' ? 'Python' : 'Stata' }))" @update:model-value="value => router.get(`/skills/${skill.id}/edit`, { language: value })" />
                <UiTextarea v-model="form.content" code :rows="24" label="SKILL.md" :error="form.errors.content" />
            </UiPanel>

            <UiPanel v-if="!readOnly" id="details" title="Details" class="scroll-mt-6">
                <UiSelect v-if="!skill?.is_system && !(skill?.language?.length > 1)" v-model="form.language" label="Language" :options="languageOptions" :error="form.errors.language" />
                <UiSelect v-model="form.visibility" label="Visibility" :options="visibilityOptions" :error="form.errors.visibility" />
                <fieldset class="flex flex-col gap-3">
                    <legend class="mb-2 text-sm font-medium text-slate-800 dark:text-white">Tags</legend>
                    <div class="flex flex-wrap gap-x-6 gap-y-3">
                        <label v-for="tag in tagOptions" :key="tag.id" class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                            <input v-model="form.tags" type="checkbox" :value="tag.id" class="size-4 accent-sky-700" />{{ tag.name }}
                        </label>
                    </div>
                    <p class="text-xs text-slate-500">Curated tags. Select all that apply.</p>
                    <p v-if="form.errors.tags || Object.keys(form.errors).some(key => key.startsWith('tags.'))" class="text-sm text-red-600">Select tags from the curated list.</p>
                </fieldset>
                <UiInput v-if="skill" v-model="form.notes" label="Version notes (optional)" :error="form.errors.notes" />
                <p v-if="skill?.source_url" class="text-sm text-slate-500">
                    Imported from <a :href="skill.source_url" class="underline">{{ skill.source_url }}</a>
                </p>
            </UiPanel>
            <p v-else-if="skill?.source_url" class="text-sm text-slate-500">
                Imported from <a :href="skill.source_url" class="underline">{{ skill.source_url }}</a>
            </p>

            <UiPanel v-if="canPublish || page.props.errors.github" id="publish" class="scroll-mt-6">
                <p v-if="page.props.errors.github" class="text-sm text-red-600 dark:text-red-400">{{ page.props.errors.github }}</p>
                <GitHubPublish v-if="skill" :action="`/skills/${skill.id}/publish`" :path="`skills/${skill.key}/SKILL.md`" />
            </UiPanel>
        </form>

        <UiPanel v-if="skill?.versions?.length" title="Version history" class="mt-8">
            <UiTable label="Skill versions">
                <thead><tr><th>Version</th><th>Notes</th><th class="actions"><span class="sr-only">Actions</span></th></tr></thead>
                <tbody><tr v-for="version in [...skill.versions].reverse()" :key="version.id">
                    <td class="font-mono">{{ version.version }}</td><td class="max-w-64 truncate">{{ version.notes || '—' }}</td>
                    <td class="actions"><UiButton v-if="version.version !== skill.version && !readOnly" variant="ghost" size="sm" @click="restore(version.version)">Restore</UiButton><span v-else-if="version.version === skill.version" class="text-xs">Current</span></td>
                </tr></tbody>
            </UiTable>
        </UiPanel>

        <template #actions>
            <template v-if="!readOnly">
                <UiButton save-shortcut type="submit" form="skill-form" class="w-full" :disabled="form.processing">{{ skill ? 'Save new version' : 'Create skill' }}</UiButton>
                <UiButton v-if="skill && !skill.is_system" variant="ghost" class="w-full" @click="destroy">Delete</UiButton>
            </template>
            <UiButton v-else class="w-full" @click="fork">Copy and edit</UiButton>
            <p v-if="page.props.errors.skill" class="text-xs text-red-600 dark:text-red-400">{{ page.props.errors.skill }}</p>
        </template>
    </SectionLayout>
</template>
