<script setup>
import { computed, ref, watch, onBeforeUnmount } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import ProjectsLayout from '@/Layouts/ProjectsLayout.vue';
import UiButton from '@/Components/UiButton.vue';
import UiBadge from '@/Components/UiBadge.vue';
import EmptyState from '@/Components/EmptyState.vue';
import PageHeader from '@/Components/PageHeader.vue';
import GitHubMark from '@/Components/GitHubMark.vue';
import { CheckIcon, FolderIcon } from '@heroicons/vue/24/outline';
import { languageNames } from '@/library.js';
import { statusLabel, statusVariant, typeName } from '@/projects.js';

const props = defineProps({ projects: Array, steps: Array, setup: Array });

const page = usePage();

const copy = {
    profile: { title: 'Fill in your profile', text: 'Your name and affiliation go on the notebooks and reports you create.', href: '/account/profile' },
    machine: { title: 'Connect your computer', text: 'Make a token, then run cloud_login() once in R or Python, or fw login in a terminal.', href: '/tokens' },
    github: { title: 'Connect GitHub', text: 'Create projects as GitHub repositories, ready to clone.', href: '/account/github' },
    project: { title: 'Create a project', text: 'Pick a blueprint and set it up on GitHub or your computer.', href: '/projects/create' },
};
const remaining = computed(() => props.steps.filter((s) => !s.done).length);
const setupCopy = { mail: 'email', github: 'GitHub' };

// The checklist can be put away for good in this browser
const DISMISS_KEY = 'projects-checklist-dismissed';
const dismissed = ref((() => { try { return localStorage.getItem(DISMISS_KEY) === '1'; } catch { return false; } })());
const dismiss = () => {
    dismissed.value = true;
    try { localStorage.setItem(DISMISS_KEY, '1'); } catch { /* storage unavailable */ }
};

// Poll while a scaffold is in progress; the webhook updates the row
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
        <div class="flex flex-col gap-8">
            <PageHeader title="Projects" description="Each project is made from a blueprint. Set it up on GitHub, or on any computer with a project token." />

            <p v-if="setup.length" class="-mt-4 rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:border-amber-700/60 dark:bg-amber-950/40 dark:text-amber-200">
                This server still needs {{ setup.map((item) => setupCopy[item]).join(' and ') }} set up.
                <Link href="/admin/setup" class="font-medium underline">Server administration</Link>
            </p>

            <div v-if="projects.length" class="flex flex-col divide-y divide-slate-200 overflow-hidden rounded-lg border border-slate-200 bg-white dark:divide-slate-800 dark:border-slate-800 dark:bg-slate-900">
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

            <EmptyState v-else :icon="FolderIcon" title="No projects yet." description="Pick a blueprint and set it up on GitHub or your computer.">
                <template #actions>
                    <Link href="/projects/create"><UiButton>New project</UiButton></Link>
                </template>
            </EmptyState>

            <details v-if="remaining && !dismissed" open class="group rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
                <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-4 py-3">
                    <span class="font-semibold text-slate-900 dark:text-white">Get started <span class="ml-1 text-sm font-normal text-slate-500">{{ steps.length - remaining }} of {{ steps.length }} done</span></span>
                    <span class="flex items-center gap-3 text-sm">
                        <button type="button" class="text-slate-500 hover:text-slate-900 dark:hover:text-white" @click.prevent="dismiss">Dismiss</button>
                        <span class="text-slate-400 group-open:hidden">Show</span>
                        <span class="hidden text-slate-400 group-open:inline">Hide</span>
                    </span>
                </summary>
                <ol class="flex flex-col divide-y divide-slate-200 border-t border-slate-200 dark:divide-slate-800 dark:border-slate-800">
                    <li v-for="(step, index) in steps" :key="step.key">
                        <Link :href="copy[step.key].href" class="flex items-start gap-3 px-4 py-3 hover:bg-slate-50 dark:hover:bg-slate-800/50">
                            <span
                                class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full text-xs font-medium"
                                :class="step.done ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-400' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'"
                            >
                                <CheckIcon v-if="step.done" class="size-3.5" />
                                <template v-else>{{ index + 1 }}</template>
                            </span>
                            <span class="flex min-w-0 flex-col">
                                <span class="text-sm font-medium" :class="step.done ? 'text-slate-500 line-through' : 'text-slate-900 dark:text-white'">{{ copy[step.key].title }}</span>
                                <span v-if="!step.done" class="text-sm text-slate-600 dark:text-slate-400">{{ copy[step.key].text }}</span>
                            </span>
                        </Link>
                    </li>
                </ol>
            </details>
        </div>
    </ProjectsLayout>
</template>
