<script setup>
import { computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import UiButton from '@/Components/UiButton.vue';
import UiTable from '@/Components/UiTable.vue';
import UiCallout from '@/Components/UiCallout.vue';
import { usePage } from '@inertiajs/vue3';
import { PlusIcon } from '@heroicons/vue/24/outline';
import { artifactKind } from '@/report-artifacts.js';
import { languageNames } from '@/library.js';

const props = defineProps({ projects: Array, counts: Object, recentArtifacts: Array, recentCollections: Array, recentReports: Array });
const page = usePage();
const destinations = [
    { key: 'projects', label: 'Projects', href: '/projects' },
    { key: 'artifacts', label: 'Artifacts', href: '/published' },
    { key: 'collections', label: 'Collections', href: '/collections' },
    { key: 'reports', label: 'Reports', href: '/reports' },
];
const activity = computed(() => [
    ...props.recentArtifacts.map(item => ({ ...item, activityKey: `artifact-${item.id}`, type: artifactKind(item.kind).label })),
    ...props.recentCollections.map(item => ({ ...item, activityKey: `collection-${item.id}`, type: 'Collection' })),
    ...props.recentReports.map(item => ({ ...item, activityKey: `report-${item.id}`, type: 'Report' })),
].sort((a, b) => new Date(b.updated_at) - new Date(a.updated_at) || b.id - a.id).slice(0, 8));
const date = value => new Date(value).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
</script>

<template>
    <Head title="Dashboard" />
    <AppLayout>
        <div class="flex min-w-0 flex-col gap-8">
            <PageHeader title="Dashboard" description="Recent research and projects.">
                <template #actions><UiButton href="/projects/create"><PlusIcon aria-hidden="true" />New project</UiButton></template>
            </PageHeader>
            <UiCallout v-if="page.props.flash.status === 'setup-complete'" variant="success">Default settings saved.</UiCallout>
            <nav aria-label="Research overview" class="flex flex-wrap gap-x-8 gap-y-4 border-b border-slate-800/10 pb-5 dark:border-white/15">
                <Link v-for="destination in destinations" :key="destination.key" :href="destination.href" class="flex items-baseline gap-2 text-sm text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white">
                    {{ destination.label }}<span class="font-mono text-base font-medium text-slate-900 dark:text-white">{{ counts[destination.key] }}</span>
                </Link>
            </nav>
            <section>
                <h2 class="mb-3 text-base font-medium text-slate-900 dark:text-white">Recent activity</h2>
                <UiTable v-if="activity.length" label="Recent activity" class="dashboard-results">
                    <thead><tr><th>Title</th><th class="max-sm:hidden">Type</th><th class="max-lg:hidden">Updated</th><th class="actions"><span class="sr-only">Actions</span></th></tr></thead>
                    <tbody><tr v-for="item in activity" :key="item.activityKey">
                        <td class="strong max-w-96 truncate">
                            <Link :href="item.management_url" class="block truncate hover:underline">{{ item.title }}</Link>
                            <Link :href="`/projects/${item.project.slug}`" class="mt-1 block truncate text-xs font-normal text-slate-500 hover:underline">{{ item.project.name }}</Link>
                        </td>
                        <td class="max-sm:hidden">{{ item.type }}</td>
                        <td class="max-lg:hidden">{{ date(item.updated_at) }}</td>
                        <td class="actions"><div class="flex justify-end gap-1">
                            <UiButton v-if="item.url" :href="item.url" external variant="ghost" size="sm">View</UiButton>
                            <UiButton :href="item.management_url" variant="ghost" size="sm">Edit</UiButton>
                        </div></td>
                    </tr></tbody>
                </UiTable>
                <p v-else class="py-4 text-sm text-slate-500">No recent activity. Published artifacts, collections, and reports appear here.</p>
            </section>
            <section>
                <div class="mb-3 flex items-center justify-between gap-3">
                    <h2 class="text-base font-medium text-slate-900 dark:text-white">Recent projects</h2>
                    <UiButton href="/projects" variant="ghost" size="sm">All projects</UiButton>
                </div>
                <UiTable v-if="projects.length" label="Recent projects" class="dashboard-projects">
                    <thead><tr><th>Name</th><th class="max-sm:hidden">Environment</th><th class="actions"><span class="sr-only">Actions</span></th></tr></thead>
                    <tbody><tr v-for="project in projects.slice(0, 4)" :key="project.id">
                        <td class="strong max-w-96 truncate"><Link :href="`/projects/${project.slug}`" class="block truncate hover:underline">{{ project.name }}</Link></td>
                        <td class="max-sm:hidden">{{ languageNames[project.language] }}</td>
                        <td class="actions"><UiButton :href="`/projects/${project.slug}`" variant="ghost" size="sm">Open</UiButton></td>
                    </tr></tbody>
                </UiTable>
                <p v-else class="py-4 text-sm text-slate-500">No projects yet. Create a project to start.</p>
            </section>
        </div>
    </AppLayout>
</template>

<style scoped>
@media (max-width: 639px) {
    .dashboard-results :deep(table), .dashboard-projects :deep(table) { width: 100%; table-layout: fixed; }
    .dashboard-results :deep(th:last-child) { width: 8rem; }
    .dashboard-projects :deep(th:last-child) { width: 4.5rem; }
}
</style>
