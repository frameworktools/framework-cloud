<script setup>
import { computed, watch, onBeforeUnmount } from 'vue';
import { router } from '@inertiajs/vue3';
import ProjectsLayout from '@/Layouts/ProjectsLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import UiBadge from '@/Components/UiBadge.vue';
import GitHubMark from '@/Components/GitHubMark.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { languageNames } from '@/library.js';
import { statusLabel, statusVariant, typeName } from '@/projects.js';

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

        <div v-if="projects.length" class="mt-6 flex flex-col divide-y divide-slate-200 overflow-hidden rounded-lg border border-slate-200 bg-white dark:divide-slate-800 dark:border-slate-800 dark:bg-slate-900">
            <Link
                v-for="project in projects" :key="project.id" :href="`/projects/${project.slug}`"
                class="flex flex-col gap-1 px-4 py-3 transition hover:bg-slate-50 sm:flex-row sm:items-center sm:gap-4 dark:hover:bg-slate-800/50"
            >
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-semibold text-slate-900 dark:text-white">{{ project.name }}</span>
                        <UiBadge>{{ typeName(project.project_type) }}</UiBadge>
                        <UiBadge variant="sky">{{ languageNames[project.language] }}</UiBadge>
                    </div>
                    <p class="mt-0.5 truncate text-sm text-slate-500 dark:text-slate-400">
                        <span v-if="project.blueprint" class="font-mono text-xs">{{ project.blueprint.handle }} v{{ project.blueprint.version }}</span>
                        <span v-else class="font-mono text-xs">{{ project.slug }}</span>
                        <span v-if="project.description"> · {{ project.description }}</span>
                    </p>
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    <UiBadge v-if="project.github?.status && project.github.status !== 'done'" :variant="statusVariant[project.github.status]">{{ statusLabel[project.github.status] }}</UiBadge>
                    <span v-else-if="project.github?.repo" class="inline-flex items-center gap-1 font-mono text-xs text-slate-500"><GitHubMark class="size-3.5" />{{ project.github.repo }}</span>
                    <UiBadge v-if="project.github?.pages_url" variant="green">Pages</UiBadge>
                </div>
            </Link>
        </div>

        <EmptyState v-else class="mt-8" compact title="No projects yet." />
    </ProjectsLayout>
</template>
