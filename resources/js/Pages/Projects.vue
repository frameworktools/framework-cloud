<script setup>
import { computed } from 'vue';
import { useForm, usePage, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import UiButton from '@/Components/UiButton.vue';
import UiInput from '@/Components/UiInput.vue';
import UiSelect from '@/Components/UiSelect.vue';
import UiCallout from '@/Components/UiCallout.vue';

const props = defineProps({ projects: Array, blueprints: Array });

const page = usePage();
const pullToken = computed(() =>
    page.props.flash.status === 'project-token' ? page.props.flash.token : null,
);

const options = computed(() => props.blueprints.map((b) => ({ value: b.key, label: b.name })));

const form = useForm({ name: '', project_type: 'bare', description: '' });
const create = () => form.post('/projects', { onSuccess: () => form.reset('name', 'description') });

const issueToken = (id) => router.post(`/projects/${id}/token`);
const destroy = (id) => {
    if (confirm('Delete this project definition? Existing local copies are unaffected, but its tokens stop working.')) {
        router.delete(`/projects/${id}`);
    }
};

const headline = (key) => key.split('_').map((w) => w[0].toUpperCase() + w.slice(1)).join(' ');
</script>

<template>
    <Head title="Projects" />

    <AppLayout>
        <div class="flex flex-col gap-6">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Projects</h1>
                <p class="mt-1 text-neutral-600 dark:text-neutral-400">
                    Define a project here, generate its token, and set it up on any machine with
                    <code class="font-mono text-sm">framework::setup("&lt;project-token&gt;")</code>.
                </p>
            </div>

            <form class="flex flex-wrap items-end gap-3" @submit.prevent="create">
                <div class="w-56"><UiInput v-model="form.name" label="Name" placeholder="e.g. NHANES Study" :error="form.errors.name" /></div>
                <div class="w-44"><UiSelect v-model="form.project_type" label="Type" :options="options" :error="form.errors.project_type" /></div>
                <div class="w-64"><UiInput v-model="form.description" label="Description (optional)" :error="form.errors.description" /></div>
                <UiButton type="submit" :disabled="form.processing">Create project</UiButton>
            </form>

            <UiCallout v-if="pullToken" variant="success" heading="Project token — copy it now, it won't be shown again">
                <code class="break-all font-mono text-sm">{{ pullToken }}</code>
                <code class="break-all font-mono text-xs">framework::setup("{{ pullToken }}")</code>
            </UiCallout>

            <div class="flex flex-col divide-y divide-neutral-200 dark:divide-neutral-700">
                <div v-for="project in projects" :key="project.id" class="flex items-center justify-between gap-4 py-3">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="font-medium">{{ project.name }}</span>
                            <span class="rounded-full bg-neutral-100 px-2 py-0.5 text-xs text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300">
                                {{ headline(project.project_type) }}
                            </span>
                        </div>
                        <p class="text-sm text-neutral-500 dark:text-neutral-400">
                            {{ project.slug }}<span v-if="project.description"> · {{ project.description }}</span>
                        </p>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <UiButton variant="outline" size="sm" @click="issueToken(project.id)">Get token</UiButton>
                        <UiButton variant="danger" size="sm" @click="destroy(project.id)">Delete</UiButton>
                    </div>
                </div>
                <p v-if="!projects.length" class="py-3 text-sm text-neutral-500">No projects yet.</p>
            </div>
        </div>
    </AppLayout>
</template>
