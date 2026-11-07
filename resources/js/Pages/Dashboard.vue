<script setup>
import { computed, ref } from 'vue';
import { CheckIcon, ClipboardDocumentIcon } from '@heroicons/vue/20/solid';
import UiTabs from '@/Components/UiTabs.vue';
import { DEFAULT_SERVER, clientUrl } from '@/cloud.js';
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
// Sign-in commands for each client. Each opens this server's approval page in a browser.
const server = computed(() => clientUrl(page));
const named = computed(() => server.value !== DEFAULT_SERVER);
const client = ref('r');
const clients = [
    { value: 'r', label: 'R' },
    { value: 'python', label: 'Python' },
    { value: 'stata', label: 'Stata' },
    { value: 'fw', label: 'Terminal' },
];
const signIn = computed(() => ({
    r: { cmd: `framework::cloud_login(${named.value ? `url = "${server.value}"` : ''})` },
    python: { cmd: `import framework; framework.cloud_login(${named.value ? `url="${server.value}"` : ''})` },
    stata: { cmd: `fw_login${named.value ? `, url(${server.value})` : ''}` },
    fw: { cmd: `fw login${named.value ? ` --url ${server.value}` : ''}` },
}[client.value]));
const copied = ref(false);
async function copySignIn() {
    await navigator.clipboard?.writeText(signIn.value.cmd);
    copied.value = true;
    setTimeout(() => (copied.value = false), 1500);
}
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
            <section aria-labelledby="sign-in-heading">
                <h2 id="sign-in-heading" class="text-base font-medium text-slate-900 dark:text-white">Computer sign-in</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Run this on your computer. A browser opens to approve it, and the computer stays signed in.</p>
                <UiTabs v-model="client" :tabs="clients" label="Client" class="mt-3" />
                <div class="mt-4 flex max-w-2xl items-center gap-2 rounded-lg bg-[#0c1526] py-1 ps-3 pe-1">
                    <code class="min-w-0 flex-1 overflow-x-auto whitespace-nowrap py-1 font-mono text-xs text-slate-100">{{ signIn.cmd }}</code>
                    <button type="button" class="flex shrink-0 items-center rounded-md p-1.5 text-slate-400 hover:bg-white/10 hover:text-white" :aria-label="`Copy ${signIn.cmd}`" @click="copySignIn">
                        <component :is="copied ? CheckIcon : ClipboardDocumentIcon" class="size-4" aria-hidden="true" />
                    </button>
                </div>
                <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">For scripts and jobs that can't wait for a browser, <Link href="/tokens" class="underline underline-offset-2">create a token</Link>.</p>
            </section>
            <section>
                <h2 class="mb-3 text-base font-medium text-slate-900 dark:text-white">Recent activity</h2>
                <UiTable v-if="activity.length" label="Recent activity" class="dashboard-results">
                    <thead><tr><th>Title</th><th class="max-sm:hidden">Type</th><th class="max-lg:hidden">Updated</th><th class="actions"><span class="sr-only">Actions</span></th></tr></thead>
                    <tbody><tr v-for="item in activity" :key="item.activityKey">
                        <td class="strong max-w-96 truncate">
                            <Link :href="item.management_url" class="block truncate hover:underline">{{ item.title }}</Link>
                            <Link :href="`/projects/${item.project.slug}`" class="block truncate py-1 text-xs font-normal text-slate-500 dark:text-slate-400 hover:underline">{{ item.project.name }}</Link>
                        </td>
                        <td class="max-sm:hidden">{{ item.type }}</td>
                        <td class="max-lg:hidden">{{ date(item.updated_at) }}</td>
                        <td class="actions"><div class="flex justify-end gap-1">
                            <UiButton v-if="item.url" :href="item.url" external variant="ghost" size="sm">View</UiButton>
                            <UiButton :href="item.management_url" variant="ghost" size="sm">Edit</UiButton>
                        </div></td>
                    </tr></tbody>
                </UiTable>
                <p v-else class="py-4 text-sm text-slate-500 dark:text-slate-400">No recent activity. Published artifacts, collections, and reports appear here.</p>
            </section>
            <section>
                <div class="mb-3 flex items-center justify-between gap-3">
                    <h2 class="text-base font-medium text-slate-900 dark:text-white">Recent projects</h2>
                    <UiButton href="/projects" variant="ghost" size="sm">All projects</UiButton>
                </div>
                <UiTable v-if="projects.length" label="Recent projects" class="dashboard-projects">
                    <thead><tr><th>Name</th><th class="max-sm:hidden">Language</th><th class="actions"><span class="sr-only">Actions</span></th></tr></thead>
                    <tbody><tr v-for="project in projects.slice(0, 4)" :key="project.id">
                        <td class="strong max-w-96 truncate"><Link :href="`/projects/${project.slug}`" class="block truncate hover:underline">{{ project.name }}</Link></td>
                        <td class="max-sm:hidden">{{ languageNames[project.language] }}</td>
                        <td class="actions"><UiButton :href="`/projects/${project.slug}`" variant="ghost" size="sm">Open</UiButton></td>
                    </tr></tbody>
                </UiTable>
                <p v-else class="py-4 text-sm text-slate-500 dark:text-slate-400">No projects yet. Create a project to start.</p>
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
