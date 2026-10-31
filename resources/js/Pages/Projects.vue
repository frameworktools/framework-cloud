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

        <div v-if="projects.length" class="mt-6 flex flex-col gap-3">
            <article
                v-for="project in projects" :key="project.id"
                class="relative flex flex-col gap-2 rounded-lg border border-slate-200 bg-white p-4 transition hover:border-slate-300 sm:flex-row sm:items-start sm:justify-between sm:gap-6 dark:border-slate-800 dark:bg-slate-900 dark:hover:border-slate-700"
            >
                <div class="min-w-0">
                    <h2 class="flex flex-wrap items-baseline gap-x-2">
                        <Link :href="`/projects/${project.slug}`" class="font-semibold text-slate-900 after:absolute after:inset-0 hover:underline dark:text-white">{{ project.name }}</Link>
                        <span class="text-sm text-slate-500 dark:text-slate-400">{{ languageNames[project.language] }}</span>
                    </h2>
                    <p v-if="project.description" class="mt-0.5 text-sm text-slate-700 dark:text-slate-300">{{ project.description }}</p>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        <template v-if="project.blueprint">
                            {{ project.blueprint.name }}
                            <span class="font-mono text-xs">{{ project.blueprint.handle }} v{{ project.blueprint.version }}</span>
                            ·
                        </template>
                        Structure: {{ typeName(project.project_type) }}
                    </p>
                </div>
                <div v-if="project.github?.repo || project.github?.status" class="relative flex shrink-0 flex-wrap gap-x-4 gap-y-0.5 text-sm sm:flex-col sm:items-end">
                    <span v-if="project.github.status && project.github.status !== 'done'" class="text-slate-500">{{ statusLabel[project.github.status] }}</span>
                    <a v-if="project.github.url" :href="project.github.url" class="text-sky-700 hover:underline dark:text-sky-400">{{ project.github.repo }}</a>
                    <a v-if="project.github.pages_url" :href="project.github.pages_url" class="text-sky-700 hover:underline dark:text-sky-400">GitHub Pages</a>
                </div>
            </article>
        </div>

        <EmptyState v-else class="mt-8" compact title="No projects yet." />
    </ProjectsLayout>
</template>
