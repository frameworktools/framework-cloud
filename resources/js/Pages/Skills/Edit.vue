<script setup>
import { computed } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
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

const save = () => (props.skill ? form.put(`/skills/${props.skill.id}`, { preserveScroll: true }) : form.post('/skills'));
const fork = () => router.post(`/skills/${props.skill.id}/fork`);
const destroy = () => {
    if (confirm(`Delete ${props.skill.key}? Facets that already copied it keep their copy.`)) router.delete(`/skills/${props.skill.id}`);
};
</script>

<template>
    <Head :title="skill ? skill.key : 'New skill'" />

    <AppLayout>
        <form class="flex max-w-3xl flex-col gap-6" @submit.prevent="save">
            <div>
                <Link href="/skills" class="text-sm text-slate-500 hover:underline">Skills</Link>
                <h1 class="font-mono text-2xl font-semibold tracking-tight">{{ skill ? skill.key : 'New skill' }}</h1>
                <div v-if="skill" class="font-mono text-xs text-slate-500">{{ skill.handle }} · v{{ skill.version }}</div>
                <p class="mt-1 text-slate-600 dark:text-slate-400">
                    The frontmatter <code class="font-mono">name</code> is the skill's name, and the
                    <code class="font-mono">description</code> tells an assistant when to load it.
                    <template v-if="skill?.is_system"> Framework skills ship with the R package, so facets use the installed copy.</template>
                </p>
            </div>

            <UiCallout v-if="saved" variant="success" heading="Saved">Version {{ skill?.version }} is in the library.</UiCallout>

            <UiTextarea v-model="form.content" code :rows="24" label="SKILL.md" :error="form.errors.content" />

            <template v-if="!readOnly">
                <UiSelect v-model="form.visibility" label="Who can see it" :options="visibilityOptions" :error="form.errors.visibility" />
                <UiInput v-if="skill" v-model="form.notes" label="What changed (optional)" :error="form.errors.notes" />
                <div class="flex gap-2">
                    <UiButton type="submit" :disabled="form.processing">{{ skill ? 'Save new version' : 'Create skill' }}</UiButton>
                    <UiButton v-if="skill && !skill.is_system" variant="ghost" @click="destroy">Delete</UiButton>
                </div>
            </template>
            <div v-else>
                <UiButton @click="fork">Copy and edit</UiButton>
            </div>
            <p v-if="page.props.errors.skill" class="text-xs text-red-600 dark:text-red-400">{{ page.props.errors.skill }}</p>
            <p v-if="page.props.errors.github" class="text-sm text-red-600 dark:text-red-400">{{ page.props.errors.github }}</p>

            <p v-if="skill?.source_url" class="text-sm text-slate-500">
                Imported from <a :href="skill.source_url" class="underline">{{ skill.source_url }}</a>
            </p>
            <GitHubPublish v-if="skill" :action="`/skills/${skill.id}/publish`" :path="`skills/${skill.key}/SKILL.md`" />
        </form>
    </AppLayout>
</template>
