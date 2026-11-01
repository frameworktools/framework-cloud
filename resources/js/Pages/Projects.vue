<script setup>
import { computed, ref, watch, onBeforeUnmount } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { PlusIcon } from '@heroicons/vue/20/solid';
import { AcademicCapIcon, CalendarIcon, FolderIcon, ListBulletIcon, PresentationChartBarIcon, Squares2X2Icon } from '@heroicons/vue/24/outline';
import AppLayout from '@/Layouts/AppLayout.vue';
import UiButton from '@/Components/UiButton.vue';
import UiBadge from '@/Components/UiBadge.vue';
import GitHubMark from '@/Components/GitHubMark.vue';
import PageHeader from '@/Components/PageHeader.vue';
import UiCallout from '@/Components/UiCallout.vue';
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

// Tiles by default; the choice is remembered in this browser
const VIEW_KEY = 'projects.view';
const view = ref((() => { try { return localStorage.getItem(VIEW_KEY) === 'list' ? 'list' : 'tiles'; } catch { return 'tiles'; } })());
watch(view, (value) => { try { localStorage.setItem(VIEW_KEY, value); } catch { /* storage unavailable */ } });

// The same icons as the Framework GUI's project cards
const typeIcon = (type) => (type?.startsWith('course') ? AcademicCapIcon : type?.startsWith('presentation') ? PresentationChartBarIcon : FolderIcon);
const created = (date) => new Date(date).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
</script>

<template>
    <Head title="Projects" />

    <AppLayout bleed>
        <!-- Full width beside the sidebar, so the grid can fit three tiles on a laptop -->
        <div class="@container px-6 py-10 lg:px-12 xl:px-16">
            <PageHeader title="Projects">
                <template #actions>
                    <Link href="/projects/create"><UiButton><PlusIcon class="size-4" />New project</UiButton></Link>
                </template>
            </PageHeader>

            <!-- Just after guided setup: the defaults are saved, and the next step is a project -->
            <UiCallout v-if="usePage().props.flash.status === 'setup-complete'" variant="success" class="mt-6">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <span>Your defaults are saved. New projects start from them.</span>
                    <Link href="/projects/create"><UiButton size="sm">Create a project</UiButton></Link>
                </div>
            </UiCallout>

            <!-- How the projects are shown: its own row, apart from New project -->
            <div v-if="projects.length" class="mt-6 ml-auto flex w-fit rounded-md border border-slate-300 p-0.5 dark:border-slate-700" role="group" aria-label="View">
                <button
                    v-for="option in [{ key: 'tiles', label: 'Tiles', icon: Squares2X2Icon }, { key: 'list', label: 'List', icon: ListBulletIcon }]" :key="option.key"
                    type="button" :aria-pressed="view === option.key" :aria-label="option.label" :title="option.label"
                    class="inline-flex items-center rounded p-1.5 transition"
                    :class="view === option.key ? 'bg-slate-200 text-slate-900 dark:bg-slate-700 dark:text-white' : 'text-slate-500 hover:bg-slate-100 hover:text-slate-700 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-200'"
                    @click="view = option.key"
                >
                    <component :is="option.icon" class="size-5" />
                </button>
            </div>

            <div v-if="projects.length && view === 'tiles'" class="mt-3 grid grid-cols-1 gap-5 @2xl:grid-cols-2 @4xl:grid-cols-3">
                <div
                    v-for="project in projects" :key="project.id"
                    class="group relative flex flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition duration-300 hover:shadow-xl hover:shadow-slate-900/5 dark:border-slate-800 dark:bg-slate-900 dark:hover:shadow-sky-500/5"
                >
                    <div class="pointer-events-none absolute -right-6 -top-6 size-24 rounded-full bg-gradient-to-br from-sky-500/10 to-sky-600/10 blur-2xl transition duration-300 group-hover:scale-150" />

                    <div class="relative flex flex-1 flex-col">
                        <div class="flex flex-wrap items-center gap-3">
                            <div class="flex size-10 items-center justify-center rounded-xl bg-gradient-to-br from-sky-500 to-sky-600 shadow-lg shadow-sky-500/20">
                                <component :is="typeIcon(project.project_type)" class="size-5 text-white" />
                            </div>
                            <UiBadge variant="sky">{{ typeName(project.project_type) }}</UiBadge>
                            <UiBadge>{{ languageNames[project.language] }}</UiBadge>
                            <UiBadge v-if="project.github?.status && project.github.status !== 'done'" :variant="statusVariant[project.github.status]">{{ statusLabel[project.github.status] }}</UiBadge>
                            <UiBadge v-if="project.github?.pages_url" variant="green">Pages</UiBadge>
                        </div>

                        <h3 class="mt-3 text-xl font-bold text-slate-900 dark:text-white">{{ project.name }}</h3>
                        <p v-if="project.github?.repo" class="mt-2 flex items-center gap-1.5 truncate font-mono text-sm text-slate-600 dark:text-slate-400"><GitHubMark class="size-4 shrink-0" />{{ project.github.repo }}</p>
                        <p v-else class="mt-2 truncate font-mono text-sm text-slate-600 dark:text-slate-400">{{ project.slug }}</p>
                        <p v-if="project.description" class="mt-3 line-clamp-2 text-sm text-slate-600 dark:text-slate-400">{{ project.description }}</p>

                        <div class="mt-3 flex flex-col gap-1.5 text-xs text-slate-500">
                            <p v-if="project.blueprint">Blueprint: <span class="font-mono">{{ project.blueprint.handle }} v{{ project.blueprint.version }}</span></p>
                            <p class="flex items-center gap-2"><CalendarIcon class="size-4" />Created {{ created(project.created_at) }}</p>
                        </div>

                        <div class="mt-auto flex gap-2 pt-5">
                            <Link :href="`/projects/${project.slug}`" class="flex-1 rounded-md bg-sky-600 whitespace-nowrap px-4 py-2 text-center text-sm font-medium text-white transition hover:bg-sky-700">View project</Link>
                            <Link :href="`/projects/${project.slug}/setup`" class="flex-1 rounded-md border border-slate-300 whitespace-nowrap px-4 py-2 text-center text-sm font-medium text-slate-800 transition hover:bg-slate-50 dark:border-slate-600 dark:text-slate-100 dark:hover:bg-slate-800">Project setup</Link>
                        </div>
                    </div>
                </div>
            </div>

            <div v-else-if="projects.length" class="mt-3 flex flex-col divide-y divide-slate-200 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:divide-slate-800 dark:border-slate-800 dark:bg-slate-900">
                <Link
                    v-for="project in projects" :key="project.id" :href="`/projects/${project.slug}`"
                    class="flex flex-col gap-3 px-5 py-4 transition hover:bg-slate-50 sm:flex-row sm:items-center sm:gap-4 dark:hover:bg-slate-800/50"
                >
                    <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-sky-500 to-sky-600 shadow-md shadow-sky-500/20 max-sm:hidden">
                        <component :is="typeIcon(project.project_type)" class="size-5 text-white" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-bold text-slate-900 dark:text-white">{{ project.name }}</span>
                            <UiBadge variant="sky">{{ typeName(project.project_type) }}</UiBadge>
                            <UiBadge>{{ languageNames[project.language] }}</UiBadge>
                        </div>
                        <p class="mt-1 truncate text-sm text-slate-500 dark:text-slate-400">
                            <span v-if="project.github?.repo" class="font-mono text-slate-600 dark:text-slate-400">{{ project.github.repo }}</span>
                            <span v-else class="font-mono">{{ project.slug }}</span>
                            <span v-if="project.description"> · {{ project.description }}</span>
                        </p>
                    </div>
                    <div class="flex shrink-0 flex-col gap-1 text-xs text-slate-500 sm:items-end">
                        <div class="flex items-center gap-2">
                            <UiBadge v-if="project.github?.status && project.github.status !== 'done'" :variant="statusVariant[project.github.status]">{{ statusLabel[project.github.status] }}</UiBadge>
                            <span v-else-if="project.github?.repo" class="inline-flex items-center gap-1"><GitHubMark class="size-3.5" />GitHub</span>
                            <UiBadge v-if="project.github?.pages_url" variant="green">Pages</UiBadge>
                        </div>
                        <span v-if="project.blueprint" class="font-mono">{{ project.blueprint.handle }} v{{ project.blueprint.version }}</span>
                        <span class="inline-flex items-center gap-1.5"><CalendarIcon class="size-3.5" />Created {{ created(project.created_at) }}</span>
                    </div>
                </Link>
            </div>

            <!-- No projects: one tile that starts the first -->
            <Link
                v-else href="/projects/create"
                class="group mt-6 flex max-w-sm flex-col items-center rounded-2xl border-2 border-dashed border-slate-300 bg-white px-6 py-10 text-center transition hover:border-sky-400 hover:bg-sky-50/50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500 dark:border-slate-700 dark:bg-slate-900 dark:hover:border-sky-500 dark:hover:bg-sky-950/30"
            >
                <span class="flex size-12 items-center justify-center rounded-xl bg-gradient-to-br from-sky-500 to-sky-600 shadow-md shadow-sky-500/20 transition group-hover:scale-105">
                    <PlusIcon class="size-6 text-white" aria-hidden="true" />
                </span>
                <span class="mt-4 text-base font-semibold text-slate-900 dark:text-white">No projects yet</span>
                <span class="mt-1 text-sm text-slate-500 dark:text-slate-400">Start your first project from your defaults or a blueprint.</span>
                <span class="mt-4 text-sm font-medium text-sky-600 group-hover:underline dark:text-sky-400">New project</span>
            </Link>
        </div>
    </AppLayout>
</template>
