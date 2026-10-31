<script setup>
import { computed, watch, onBeforeUnmount } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import UiButton from '@/Components/UiButton.vue';
import UiCallout from '@/Components/UiCallout.vue';
import UiBadge from '@/Components/UiBadge.vue';
import EmptyState from '@/Components/EmptyState.vue';
import PageHeader from '@/Components/PageHeader.vue';
import GitHubMark from '@/Components/GitHubMark.vue';
import { ArrowRightIcon, CheckIcon, ChevronRightIcon, ComputerDesktopIcon, FolderIcon, FolderPlusIcon, PlusIcon, UserCircleIcon } from '@heroicons/vue/24/outline';
import { languageNames } from '@/library.js';
import { statusLabel, statusVariant, typeName } from '@/projects.js';

const props = defineProps({ projects: Array, steps: Array, setup: Array });

const page = usePage();

const copy = {
    profile: { title: 'Fill in your profile', text: 'Your name and affiliation go on the notebooks and reports you create.', href: '/settings/profile', icon: UserCircleIcon },
    machine: { title: 'Connect your computer', text: 'Make a token, then run cloud_login() once in R or Python, or fw login in a terminal.', href: '/tokens', icon: ComputerDesktopIcon },
    github: { title: 'Connect GitHub', text: 'Create projects as GitHub repositories, ready to clone.', href: '/settings/github', icon: GitHubMark },
    project: { title: 'Create a project', text: 'Pick a blueprint and set it up on GitHub or your computer.', href: '/projects/create', icon: FolderPlusIcon },
};
const remaining = computed(() => props.steps.filter((s) => !s.done).length);
const setupCopy = {
    mail: 'Email isn\'t set up, so sign-in links aren\'t being sent.',
    github: 'GitHub isn\'t set up, so nobody can sign in with GitHub or create projects there.',
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

    <AppLayout>
        <div class="flex flex-col gap-10">
            <PageHeader title="Projects" description="Each project is made from a blueprint. Set it up on GitHub, or on any computer with a project token.">
                <template #actions>
                    <Link href="/projects/create"><UiButton><PlusIcon class="size-4" />New project</UiButton></Link>
                </template>
            </PageHeader>

            <UiCallout v-if="setup.length" variant="danger" heading="Finish setting up this server">
                <span v-for="item in setup" :key="item">{{ setupCopy[item] }}</span>
                <Link href="/admin/setup" class="font-medium text-red-700 hover:underline dark:text-red-300">Open server setup</Link>
            </UiCallout>

            <section v-if="remaining" class="flex flex-col gap-4">
                <div class="flex items-baseline justify-between gap-4">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Get started</h2>
                    <span class="text-sm text-slate-500 dark:text-slate-400">{{ steps.length - remaining }} of {{ steps.length }} done</span>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <Link
                        v-for="(step, index) in steps" :key="step.key" :href="copy[step.key].href"
                        class="group flex gap-4 rounded-lg border p-5 transition"
                        :class="step.done
                            ? 'border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-900/40'
                            : 'border-slate-200 bg-white shadow-xs hover:border-sky-300 hover:shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:shadow-none dark:hover:border-sky-700'"
                    >
                        <span
                            class="flex size-10 shrink-0 items-center justify-center rounded-lg"
                            :class="step.done
                                ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-400'
                                : 'bg-sky-50 text-sky-600 dark:bg-sky-400/10 dark:text-sky-400'"
                        >
                            <CheckIcon v-if="step.done" class="size-5" />
                            <component :is="copy[step.key].icon" v-else class="size-5" />
                        </span>
                        <span class="flex min-w-0 flex-1 flex-col gap-1">
                            <span class="text-xs font-medium text-slate-500 dark:text-slate-400">
                                Step {{ index + 1 }}<span v-if="step.done"> · Done</span>
                            </span>
                            <span class="font-semibold" :class="step.done ? 'text-slate-500 line-through dark:text-slate-400' : 'text-slate-900 dark:text-white'">{{ copy[step.key].title }}</span>
                            <span v-if="!step.done" class="text-sm text-slate-600 dark:text-slate-400">{{ copy[step.key].text }}</span>
                        </span>
                        <ArrowRightIcon v-if="!step.done" class="mt-1 size-4 shrink-0 text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-sky-600 dark:text-slate-600 dark:group-hover:text-sky-400" />
                    </Link>
                </div>
            </section>

            <div v-if="projects.length" class="flex flex-col divide-y divide-slate-200 overflow-hidden rounded-lg border border-slate-200 bg-white dark:divide-slate-800 dark:border-slate-800 dark:bg-slate-900">
                <Link
                    v-for="project in projects" :key="project.id" :href="`/projects/${project.slug}`"
                    class="group flex items-center gap-4 px-5 py-4 transition hover:bg-slate-50 dark:hover:bg-slate-800/50"
                >
                    <div class="hidden size-10 shrink-0 items-center justify-center rounded-lg bg-sky-50 text-sky-600 sm:flex dark:bg-sky-400/10 dark:text-sky-400">
                        <FolderIcon class="size-5" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-base font-semibold text-slate-900 dark:text-white">{{ project.name }}</span>
                            <UiBadge>{{ typeName(project.project_type) }}</UiBadge>
                            <UiBadge v-if="project.language !== 'r'" variant="sky">{{ languageNames[project.language] }}</UiBadge>
                        </div>
                        <p class="mt-1 truncate text-sm text-slate-500 dark:text-slate-400">
                            <span v-if="project.blueprint" class="font-mono text-xs">{{ project.blueprint.handle }} v{{ project.blueprint.version }}</span>
                            <span v-else class="font-mono text-xs">{{ project.slug }}</span>
                            <span v-if="project.description"> · {{ project.description }}</span>
                        </p>
                    </div>
                    <div class="hidden shrink-0 items-center gap-2 sm:flex">
                        <UiBadge v-if="project.github?.status && project.github.status !== 'done'" :variant="statusVariant[project.github.status]">{{ statusLabel[project.github.status] }}</UiBadge>
                        <span v-else-if="project.github?.repo" class="inline-flex items-center gap-1 font-mono text-xs text-slate-500"><GitHubMark class="size-3.5" />{{ project.github.repo }}</span>
                        <UiBadge v-if="project.github?.pages_url" variant="green">Pages</UiBadge>
                    </div>
                    <ChevronRightIcon class="size-4 shrink-0 text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-sky-600 dark:text-slate-600 dark:group-hover:text-sky-400" />
                </Link>
            </div>

            <EmptyState v-else :icon="FolderIcon" title="No projects yet." description="Pick a blueprint and set it up on GitHub or your computer.">
                <template #actions>
                    <Link href="/projects/create"><UiButton><PlusIcon class="size-4" />Create your first project</UiButton></Link>
                </template>
            </EmptyState>
        </div>
    </AppLayout>
</template>
