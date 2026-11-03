<script setup>
import { computed, ref, watch, onBeforeUnmount } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { PlusIcon } from '@heroicons/vue/20/solid';
import AppLayout from '@/Layouts/AppLayout.vue';
import UiButton from '@/Components/UiButton.vue';
import UiBadge from '@/Components/UiBadge.vue';
import GitHubMark from '@/Components/GitHubMark.vue';
import PageHeader from '@/Components/PageHeader.vue';
import UiCallout from '@/Components/UiCallout.vue';
import { languageNames } from '@/library.js';
import { statusLabel, statusVariant, typeName } from '@/projects.js';

const props = defineProps({ projects: Array, recentArtifacts: { type: Array, default: () => [] }, recentReports: { type: Array, default: () => [] }, recentCollections: { type: Array, default: () => [] }, canPublish: Boolean });
const reportProject = ref(props.projects[0]?.id || '');
const collectionProject = ref(props.projects[0]?.id || '');
const inProgress = computed(() => props.projects.some((p) => ['pending', 'running'].includes(p.github?.status)));
let timer = null;
watch(inProgress, (value) => {
    clearInterval(timer);
    if (value) timer = setInterval(() => router.reload({ only: ['projects'] }), 10000);
}, { immediate: true });
onBeforeUnmount(() => clearInterval(timer));
const created = (date) => new Date(date).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
const rowClass = 'flex flex-wrap items-center gap-x-5 gap-y-2 py-3 text-sm';
const titleClass = 'font-medium text-slate-900 hover:underline hover:underline-offset-4 dark:text-slate-100';
// Row and section actions are small outline buttons, not blue text (cloud-design)
const actionClass = 'inline-flex h-8 shrink-0 items-center rounded-md border border-slate-200 border-b-slate-300/80 bg-white px-3 text-sm font-medium text-slate-800 shadow-xs hover:bg-slate-50 dark:border-white/10 dark:bg-white/10 dark:text-white dark:hover:bg-white/15';
const selectClass = 'max-w-48 rounded-md border border-slate-300 bg-white px-2 py-1 text-xs dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100';
</script>

<template>
    <Head title="Your artifacts" />
    <AppLayout bleed>
        <div class="mx-auto max-w-7xl px-6 py-8 lg:px-12">
            <PageHeader title="Your artifacts">
                <template #description>Your projects, outputs, reports, and collections.</template>
                <template #actions>
                    <Link v-if="projects.length" href="/published"><UiButton variant="outline">Publish an output</UiButton></Link>
                    <Link href="/projects/create"><UiButton><PlusIcon class="size-4" />New project</UiButton></Link>
                </template>
            </PageHeader>
            <UiCallout v-if="usePage().props.flash.status === 'setup-complete'" variant="success" class="mt-6">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <span>Your defaults are saved. New projects start from them.</span>
                    <Link href="/projects/create"><UiButton size="sm">Create a project</UiButton></Link>
                </div>
            </UiCallout>

            <template v-if="projects.length">
                <section class="mt-8" aria-labelledby="recent-outputs-title">
                    <div class="flex items-center justify-between gap-4 border-b border-slate-200 pb-3 dark:border-slate-700">
                        <h2 id="recent-outputs-title" class="text-base font-semibold">Recent outputs</h2>
                        <Link href="/published" :class="actionClass">All outputs</Link>
                    </div>
                    <ul v-if="recentArtifacts.length" class="divide-y divide-slate-100 dark:divide-slate-800">
                        <li v-for="artifact in recentArtifacts" :key="artifact.id" :class="rowClass">
                            <div class="min-w-0 flex-1 basis-full sm:basis-48">
                                <Link :href="artifact.management_url" :class="titleClass">{{ artifact.title }}</Link>
                                <p class="mt-1 text-xs text-slate-500">{{ artifact.project.name }} · {{ artifact.version_count }} {{ artifact.version_count === 1 ? 'version' : 'versions' }}</p>
                            </div>
                            <span class="hidden w-14 text-xs capitalize sm:block text-slate-500">{{ artifact.kind || 'artifact' }}</span>
                            <div class="w-24 text-xs text-slate-500">{{ artifact.visibility }}<span v-if="artifact.password_protected" class="block">Password protected</span></div>
                            <time :datetime="artifact.updated_at" class="hidden w-24 text-right text-xs text-slate-500 xl:block">{{ created(artifact.updated_at) }}</time>
                            <a :href="artifact.url" target="_blank" rel="noopener" :aria-label="`View ${artifact.title}`" class="max-sm:ml-auto" :class="actionClass">View</a>
                        </li>
                    </ul>
                    <p v-else class="py-4 text-sm text-slate-500">No outputs yet. <Link href="/published" class="font-medium text-slate-800 underline decoration-slate-800/25 underline-offset-4 hover:decoration-current dark:text-white">Publish your first output</Link>.</p>
                </section>

                <section class="mt-7" aria-labelledby="workspace-reports-title">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 pb-3 dark:border-slate-700">
                        <h2 id="workspace-reports-title" class="text-base font-semibold">Reports</h2>
                        <div class="flex items-center gap-3">
                            <select v-if="projects.length > 1" v-model="reportProject" aria-label="Project for new report" :class="selectClass"><option v-for="project in projects" :key="project.id" :value="project.id">{{ project.name }}</option></select>
                            <Link :href="`/projects/${reportProject}/reports`" :class="actionClass">New report</Link>
                        </div>
                    </div>
                    <ul v-if="recentReports.length" class="divide-y divide-slate-100 dark:divide-slate-800">
                        <li v-for="report in recentReports" :key="report.id" :class="rowClass">
                            <div class="min-w-0 flex-1 basis-48"><Link :href="report.management_url" :class="titleClass">{{ report.title }}</Link><p class="mt-1 text-xs text-slate-500">{{ report.project.name }} · {{ report.version_count ? `${report.version_count} published ${report.version_count === 1 ? 'version' : 'versions'}` : 'No published versions' }}</p></div>
                            <span class="w-24 text-xs text-slate-500">{{ report.version_count ? report.visibility : 'Draft' }}</span>
                            <a v-if="report.url" :href="report.url" target="_blank" rel="noopener" :aria-label="`View ${report.title}`" :class="actionClass">View</a>
                            <Link :href="report.management_url" :aria-label="`Edit ${report.title}`" :class="actionClass">Edit</Link>
                        </li>
                    </ul>
                    <p v-else class="py-4 text-sm text-slate-500">No reports yet. Combine text and artifacts to explain your findings.</p>
                </section>

                <section class="mt-7" aria-labelledby="workspace-collections-title">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 pb-3 dark:border-slate-700">
                        <h2 id="workspace-collections-title" class="text-base font-semibold">Collections</h2>
                        <div class="flex items-center gap-3">
                            <select v-if="projects.length > 1" v-model="collectionProject" aria-label="Project for new collection" :class="selectClass"><option v-for="project in projects" :key="project.id" :value="project.id">{{ project.name }}</option></select>
                            <Link :href="`/projects/${collectionProject}/collections`" :class="actionClass">New collection</Link>
                        </div>
                    </div>
                    <ul v-if="recentCollections.length" class="divide-y divide-slate-100 dark:divide-slate-800">
                        <li v-for="collection in recentCollections" :key="collection.id" :class="rowClass">
                            <div class="min-w-0 flex-1 basis-48"><Link :href="collection.management_url" :class="titleClass">{{ collection.title }}</Link><p class="mt-1 text-xs text-slate-500">{{ collection.project.name }} · {{ collection.item_count }} {{ collection.item_count === 1 ? 'artifact' : 'artifacts' }}</p></div>
                            <div class="w-24 text-xs text-slate-500">{{ collection.visibility }}<span v-if="collection.password_protected" class="block">Password protected</span></div>
                            <a :href="collection.url" target="_blank" rel="noopener" :aria-label="`View ${collection.title}`" :class="actionClass">View</a>
                            <Link :href="collection.management_url" :aria-label="`Edit ${collection.title}`" :class="actionClass">Edit</Link>
                        </li>
                    </ul>
                    <p v-else class="py-4 text-sm text-slate-500">No collections yet. Group related outputs for a paper, review, or analysis.</p>
                </section>

                <section class="mt-7" aria-labelledby="workspace-projects-title">
                    <div class="flex items-center justify-between gap-4 border-b border-slate-200 pb-3 dark:border-slate-700"><h2 id="workspace-projects-title" class="text-base font-semibold">Projects</h2><span class="text-xs text-slate-500">{{ projects.length }} {{ projects.length === 1 ? 'project' : 'projects' }}</span></div>
                    <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                        <li v-for="project in projects" :key="project.id" :class="rowClass">
                            <div class="min-w-0 flex-1 basis-48">
                                <Link :href="`/projects/${project.slug}`" :class="titleClass">{{ project.name }}</Link>
                                <p class="mt-1 break-words text-xs text-slate-500"><span v-if="project.github?.repo" class="inline-flex items-center gap-1"><GitHubMark class="size-3" />{{ project.github.repo }}</span><span v-else>{{ project.slug }}</span><span v-if="project.description"> · {{ project.description }}</span></p>
                                <p v-if="project.blueprint" class="mt-1 text-xs text-slate-500">Blueprint: {{ project.blueprint.handle }} v{{ project.blueprint.version }}</p>
                            </div>
                            <span class="text-xs text-slate-500">{{ typeName(project.project_type) }} · {{ languageNames[project.language] }}</span>
                            <UiBadge v-if="project.github?.status && project.github.status !== 'done'" :variant="statusVariant[project.github.status]">{{ statusLabel[project.github.status] }}</UiBadge>
                            <UiBadge v-if="project.github?.pages_url" variant="green">Pages</UiBadge>
                            <Link :href="`/projects/${project.slug}/setup`" :aria-label="`Set up ${project.name}`" :class="actionClass">Setup</Link>
                        </li>
                    </ul>
                </section>
            </template>
            <div v-else class="mt-12 border-t border-slate-200 pt-8 dark:border-slate-700">
                <h2 class="font-semibold">New project</h2>
                <p class="mt-2 text-sm text-slate-500">Use your defaults or a blueprint, then publish outputs and build reports here.</p>
                <Link href="/projects/create" class="mt-4" :class="actionClass">New project</Link>
            </div>
        </div>
    </AppLayout>
</template>
