<script setup>
import { pageClass } from '@/ui.js';
import { computed, watch, onBeforeUnmount } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { PlusIcon } from '@heroicons/vue/20/solid';
import AppLayout from '@/Layouts/AppLayout.vue';
import UiButton from '@/Components/UiButton.vue';
import UiBadge from '@/Components/UiBadge.vue';
import GitHubMark from '@/Components/GitHubMark.vue';
import PageHeader from '@/Components/PageHeader.vue';
import UiCallout from '@/Components/UiCallout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { FolderIcon } from '@heroicons/vue/24/outline';
import { languageNames } from '@/library.js';
import { statusLabel, statusVariant, typeName } from '@/projects.js';

const props = defineProps({ projects: Array, recentArtifacts: { type: Array, default: () => [] }, recentReports: { type: Array, default: () => [] }, recentCollections: { type: Array, default: () => [] }, canPublish: Boolean });
const inProgress = computed(() => props.projects.some((p) => ['pending', 'running'].includes(p.github?.status)));
let timer = null;
watch(inProgress, (value) => {
    clearInterval(timer);
    if (value) timer = setInterval(() => router.reload({ only: ['projects'] }), 10000);
}, { immediate: true });
onBeforeUnmount(() => clearInterval(timer));
const rowClass = 'flex flex-wrap items-center gap-x-5 gap-y-2 py-3 text-sm';
const titleClass = 'font-medium text-slate-900 hover:underline hover:underline-offset-4 dark:text-slate-100';
</script>

<template>
    <Head title="Projects" />
    <AppLayout bleed>
        <div :class="pageClass">
            <PageHeader title="Projects">
                <template #description>Project files, setup, and connections.</template>
                <template #actions>
                    <UiButton href="/projects/create"><PlusIcon class="size-4" />New project</UiButton>
                </template>
            </PageHeader>
            <UiCallout v-if="usePage().props.flash.status === 'setup-complete'" variant="success" class="mt-6">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <span>Your defaults are saved. New projects start from them.</span>
                    <UiButton href="/projects/create" variant="ghost" size="sm">Create a project</UiButton>
                </div>
            </UiCallout>

            <template v-if="projects.length">
                <section class="mt-7" aria-labelledby="workspace-projects-title">
                    <div class="flex items-center justify-between gap-4 border-b border-slate-200 pb-3 dark:border-slate-700"><h2 id="workspace-projects-title" class="text-base font-semibold">Projects</h2><span class="text-xs text-slate-500 dark:text-slate-400">{{ projects.length }} {{ projects.length === 1 ? 'project' : 'projects' }}</span></div>
                    <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                        <li v-for="project in projects" :key="project.id" :class="rowClass">
                            <div class="min-w-0 flex-1 basis-full sm:basis-48">
                                <Link :href="`/projects/${project.slug}`" :class="titleClass">{{ project.name }}</Link>
                                <p class="mt-1 break-words text-xs text-slate-500 dark:text-slate-400"><span v-if="project.github?.repo" class="inline-flex items-center gap-1"><GitHubMark class="size-3" />{{ project.github.repo }}</span><span v-else-if="project.slug !== project.name">{{ project.slug }}</span><span v-if="project.description"><template v-if="project.github?.repo || project.slug !== project.name"> · </template>{{ project.description }}</span></p>
                                <p v-if="project.blueprint" class="mt-1 text-xs text-slate-500 dark:text-slate-400">Blueprint: {{ project.blueprint.handle }} v{{ project.blueprint.version }}</p>
                            </div>
                            <span class="text-xs text-slate-500 dark:text-slate-400">{{ typeName(project.project_type) }} · {{ languageNames[project.language] }}</span>
                            <UiBadge v-if="project.github?.status && project.github.status !== 'done'" :variant="statusVariant[project.github.status]">{{ statusLabel[project.github.status] }}</UiBadge>
                            <UiBadge v-if="project.github?.pages_url" variant="green">Pages</UiBadge>
                            <UiButton :href="`/projects/${project.slug}/setup`" :aria-label="`Set up ${project.name}`" variant="ghost" size="sm">Setup</UiButton>
                        </li>
                    </ul>
                </section>
            </template>
            <EmptyState
                v-else :icon="FolderIcon" title="No projects" class="mt-12"
                description="Start a project from your defaults or a blueprint. Its published artifacts and reports appear here."
            />
        </div>
    </AppLayout>
</template>
