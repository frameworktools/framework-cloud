<script setup>
import { computed, watch, onBeforeUnmount } from 'vue';
import { router } from '@inertiajs/vue3';
import ProjectsLayout from '@/Layouts/ProjectsLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { languageNames } from '@/library.js';
import { statusLabel, typeName } from '@/projects.js';

const props = defineProps({ projects: Array });

// Poll while a scaffold is in progress; the webhook updates the card
const inProgress = computed(() => props.projects.some((p) => ['pending', 'running'].includes(p.github?.status)));
let timer = null;
watch(inProgress, (value) => {
    clearInterval(timer);
    if (value) timer = setInterval(() => router.reload({ only: ['projects'] }), 10000);
}, { immediate: true });
onBeforeUnmount(() => clearInterval(timer));
</script>

<template>
    <Head title="Projects" />

    <ProjectsLayout>
        <PageHeader title="Projects" />

        <div v-if="projects.length" class="mt-8 flex flex-col gap-4">
            <article
                v-for="project in projects" :key="project.id"
                class="relative rounded-xl border border-slate-200 bg-white p-6 transition hover:border-slate-300 hover:shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:hover:border-slate-700"
            >
                <h2 class="text-lg font-semibold text-slate-900 dark:text-white">
                    <Link :href="`/projects/${project.slug}`" class="after:absolute after:inset-0 hover:underline">{{ project.name }}</Link>
                </h2>
                <p v-if="project.description" class="mt-1 text-sm text-slate-700 dark:text-slate-300">{{ project.description }}</p>
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ languageNames[project.language] }} · {{ typeName(project.project_type) }}</p>
                <p v-if="project.github?.repo || project.github?.status" class="relative mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm">
                    <span v-if="project.github.status && project.github.status !== 'done'" class="text-slate-500">{{ statusLabel[project.github.status] }}</span>
                    <a v-if="project.github.url" :href="project.github.url" class="text-sky-700 hover:underline dark:text-sky-400">{{ project.github.repo }}</a>
                    <a v-if="project.github.pages_url" :href="project.github.pages_url" class="text-sky-700 hover:underline dark:text-sky-400">GitHub Pages</a>
                </p>
            </article>
        </div>

        <EmptyState v-else class="mt-8" compact title="No projects yet." />
    </ProjectsLayout>
</template>
