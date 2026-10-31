<script setup>
import { computed } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { DocumentTextIcon, InformationCircleIcon } from '@heroicons/vue/24/outline';
import SectionLayout from '@/Layouts/SectionLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import UiPanel from '@/Components/UiPanel.vue';
import GitHubMark from '@/Components/GitHubMark.vue';
import UiButton from '@/Components/UiButton.vue';
import UiCallout from '@/Components/UiCallout.vue';
import GitHubPublish from '@/Components/GitHubPublish.vue';
import UiInput from '@/Components/UiInput.vue';
import UiSelect from '@/Components/UiSelect.vue';
import UiTextarea from '@/Components/UiTextarea.vue';
import { visibilityOptions } from '@/library.js';

const props = defineProps({ skill: Object, content: String });

const page = usePage();
const saved = computed(() => ['skill-saved', 'skill-forked'].includes(page.props.flash.status));
const readOnly = computed(() => props.skill && !props.skill.editable);

const form = useForm({
    content: props.content,
    visibility: props.skill?.visibility ?? 'private',
    notes: '',
});

const canPublish = computed(() => !!props.skill && !!page.props.githubSignIn);
const items = computed(() => [
    { label: 'SKILL.md', anchor: 'content', icon: DocumentTextIcon },
    ...(!readOnly.value ? [{ label: 'Details', anchor: 'details', icon: InformationCircleIcon }] : []),
    ...(canPublish.value ? [{ label: 'Publish to GitHub', anchor: 'publish', icon: GitHubMark }] : []),
]);

const save = () => (props.skill ? form.put(`/skills/${props.skill.id}`, { preserveScroll: true }) : form.post('/skills'));
const fork = () => router.post(`/skills/${props.skill.id}/fork`);
const destroy = () => {
    if (confirm(`Delete ${props.skill.name}? Facets that already copied it keep their copy.`)) router.delete(`/skills/${props.skill.id}`);
};
</script>

<template>
    <Head :title="skill ? skill.name : 'New skill'" />

    <SectionLayout heading="Skill" :items="items">
        <Link href="/skills" class="text-sm text-slate-500 hover:underline">Skills</Link>
        <PageHeader :title="skill ? skill.name : 'New skill'">
            <template #description>
                The frontmatter <code class="font-mono">name</code> is the skill's official name, which assistants use. The
                <code class="font-mono">description</code> tells an assistant when to load it, and
                <code class="font-mono">metadata.title</code> is the readable name shown here.
                <template v-if="skill?.is_system"> Framework skills ship with the R package, so facets use the installed copy.</template>
            </template>
        </PageHeader>
        <div v-if="skill" class="mt-2 font-mono text-xs text-slate-500">{{ skill.handle }} · v{{ skill.version }}</div>

        <UiCallout v-if="saved" variant="success" heading="Saved" class="mt-8">Version {{ skill?.version }} is saved.</UiCallout>

        <form id="skill-form" class="mt-8 flex flex-col gap-8" @submit.prevent="save">
            <UiPanel id="content" class="scroll-mt-6">
                <UiTextarea v-model="form.content" code :rows="24" label="SKILL.md" :error="form.errors.content" />
            </UiPanel>

            <UiPanel v-if="!readOnly" id="details" title="Details" class="scroll-mt-6">
                <UiSelect v-model="form.visibility" label="Who can see it" :options="visibilityOptions" :error="form.errors.visibility" />
                <UiInput v-if="skill" v-model="form.notes" label="What changed (optional)" :error="form.errors.notes" />
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
