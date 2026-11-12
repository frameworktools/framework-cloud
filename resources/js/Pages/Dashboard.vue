<script setup>
import RowMenu from '@/Components/RowMenu.vue';
import ShareDialog from '@/Components/ShareDialog.vue';
import { ask } from '@/confirm.js';
import { computed, ref } from 'vue';
import UiTabs from '@/Components/UiTabs.vue';
import CopyBlock from '@/Components/CopyBlock.vue';
import InfoPopover from '@/Components/InfoPopover.vue';
import UiLink from '@/Components/UiLink.vue';
import { DEFAULT_SERVER, clientUrl } from '@/cloud.js';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import UiButton from '@/Components/UiButton.vue';
import UiTable from '@/Components/UiTable.vue';
import UiCallout from '@/Components/UiCallout.vue';
import { router, usePage } from '@inertiajs/vue3';
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
    ...props.recentArtifacts.map(item => ({ ...item, activityKey: `artifact-${item.id}`, type: artifactKind(item.kind).label, shareType: 'artifact', deleteUrl: `/published/${item.id}` })),
    ...props.recentCollections.map(item => ({ ...item, activityKey: `collection-${item.id}`, type: 'Collection', shareType: 'collection', deleteUrl: `/collections/${item.id}?then=back` })),
    ...props.recentReports.map(item => ({ ...item, activityKey: `report-${item.id}`, type: 'Report', shareType: 'report', deleteUrl: `/reports/${item.id}?then=back` })),
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
// R installs from GitHub, which CRAN can lag behind. Both installs work on a
// fresh machine: they set a CRAN mirror and add what they need first.
const mirror = 'options(repos = c(CRAN = "https://cloud.r-project.org"))';
const githubInstall = `${mirror}
if (!requireNamespace("remotes", quietly = TRUE)) install.packages("remotes")
if (!requireNamespace("httr2", quietly = TRUE)) install.packages("httr2")
remotes::install_github("frameworktools/framework", upgrade = "never")
packageVersion("framework")`;
const cranInstall = `${mirror}
for (pkg in c("framework", "httr2")) {
  if (!requireNamespace(pkg, quietly = TRUE)) install.packages(pkg)
}
packageVersion("framework")`;
const signInInfo = 'Opens a browser to approve this computer. It stays signed in. Scripts and jobs that can\'t wait for a browser use a token.';
const highlighting = { r: 'r', python: 'python', stata: 'stata', fw: 'bash' };
const installs = [
    { key: 'latest', label: 'Latest (recommended)', cmd: githubInstall, info: 'Installs the newest Framework from GitHub. Adds remotes and httr2 first if they are missing. Restart R after updating.' },
    { key: 'cran', label: 'CRAN', cmd: cranInstall, info: 'Installs the CRAN release, with httr2 for signing in. CRAN can lag behind GitHub.' },
];
const installFrom = ref('latest');
const install = computed(() => installs.find((i) => i.key === installFrom.value));
const createCommands = [
    { label: 'New notebook', cmd: 'framework::make_notebook("analysis")', info: 'Fills in your defaults: the project\'s notebook format, notebooks folder, and author. The notebook starts with scaffold(), which loads the project\'s packages and functions.' },
    { label: 'New script', cmd: 'framework::make_script("clean-data")', info: 'Fills in your defaults: the project\'s scripts folder and author. The script starts with scaffold(), which loads the project\'s packages and functions.' },
];
const pill = (on) => (on
    ? 'bg-white text-slate-900 shadow-sm ring-1 ring-slate-200 dark:bg-slate-800 dark:text-white dark:ring-slate-700'
    : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white');
const date = value => new Date(value).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });

const shareTarget = ref(null);
const removeItem = async (item) => {
    if (await ask(`Delete ${item.title}? It goes to the Trash, where you can restore it.`)) router.delete(item.deleteUrl, { preserveScroll: true });
};
const menuFor = (item) => [
    ...(item.url ? [{ label: 'View', href: item.url, external: true }] : []),
    { label: 'Share', onSelect: () => { shareTarget.value = { type: item.shareType, id: item.id, title: item.title, settings: item.shareType === 'artifact' ? `${item.management_url}?tab=sharing` : item.management_url }; } },
    { divider: true },
    { label: 'Delete', danger: true, onSelect: () => removeItem(item) },
];
</script>

<template>
    <Head title="Dashboard" />
    <AppLayout>
        <div class="flex min-w-0 flex-col gap-8">
            <PageHeader title="Dashboard" description="Recent research and projects.">
                <template #actions><UiButton href="/projects/create"><PlusIcon aria-hidden="true" />New project</UiButton></template>
            </PageHeader>
            <UiCallout v-if="page.props.flash.status === 'setup-complete'" variant="success">Default blueprint saved.</UiCallout>
            <nav aria-label="Research overview" class="flex flex-wrap gap-x-8 gap-y-4 border-b border-slate-800/10 pb-5 dark:border-white/15">
                <Link v-for="destination in destinations" :key="destination.key" :href="destination.href" class="flex items-baseline gap-2 text-sm text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white">
                    {{ destination.label }}<span class="font-mono text-base font-medium text-slate-900 dark:text-white">{{ counts[destination.key] }}</span>
                </Link>
            </nav>
            <section aria-labelledby="quick-commands-heading">
                <h2 id="quick-commands-heading" class="text-base font-medium text-slate-900 dark:text-white">Quick commands</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Run these on your computer.</p>
                <UiTabs v-model="client" :tabs="clients" label="Client" class="mt-3 max-w-2xl" />
                <div class="mt-4 flex flex-col gap-6">
                    <div v-if="client === 'r'" class="min-w-0">
                        <div class="mb-2 flex flex-wrap items-center gap-3">
                            <h3 class="text-sm font-medium text-slate-800 dark:text-slate-200">Install</h3>
                            <div role="radiogroup" aria-label="Install from" class="inline-flex gap-1 rounded-lg bg-slate-100 p-1 dark:bg-slate-900">
                                <button v-for="option in installs" :key="option.key" type="button" role="radio" :aria-checked="installFrom === option.key"
                                    class="rounded-md px-2.5 py-0.5 text-xs font-medium transition" :class="pill(installFrom === option.key)" @click="installFrom = option.key">{{ option.label }}</button>
                            </div>
                            <InfoPopover :label="`installing from ${install.label}`">{{ install.info }}</InfoPopover>
                        </div>
                        <CopyBlock :value="install.cmd" :label="`install from ${install.label}`" language="r" />
                    </div>
                    <div class="min-w-0">
                        <h3 class="mb-2 flex items-center gap-1.5 text-sm font-medium text-slate-800 dark:text-slate-200">
                            Sign in
                            <InfoPopover label="signing in">{{ signInInfo }} <UiLink href="/tokens">Create a token</UiLink>.</InfoPopover>
                        </h3>
                        <CopyBlock :value="signIn.cmd" label="sign in" :language="highlighting[client]" />
                    </div>
                    <div v-if="client === 'r'" class="min-w-0">
                        <h3 class="text-sm font-medium text-slate-800 dark:text-slate-200">Create resources in a project</h3>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Run these inside a project. They fill in your defaults.</p>
                        <div class="mt-3 grid gap-x-6 gap-y-5 lg:grid-cols-2">
                            <div v-for="command in createCommands" :key="command.label" class="min-w-0">
                                <h4 class="mb-2 flex items-center gap-1.5 text-sm font-medium text-slate-700 dark:text-slate-300">
                                    {{ command.label }}
                                    <InfoPopover :label="command.label">{{ command.info }}</InfoPopover>
                                </h4>
                                <CopyBlock :value="command.cmd" :label="command.label.toLowerCase()" language="r" />
                            </div>
                        </div>
                    </div>
                </div>
            </section>
            <section>
                <h2 class="mb-3 text-base font-medium text-slate-900 dark:text-white">Recent activity</h2>
                <UiTable v-if="activity.length" label="Recent activity" class="dashboard-results">
                    <thead><tr><th>Title</th><th class="max-sm:hidden">Type</th><th class="max-lg:hidden">Updated</th><th class="actions"><span class="sr-only">Actions</span></th></tr></thead>
                    <tbody><tr v-for="item in activity" :key="item.activityKey">
                        <td class="strong max-w-96 truncate">
                            <Link :href="item.management_url" class="block truncate py-0.5 hover:underline">{{ item.title }}</Link>
                            <Link :href="`/projects/${item.project.slug}`" class="block truncate py-1 text-xs font-normal text-slate-500 dark:text-slate-400 hover:underline">{{ item.project.name }}</Link>
                        </td>
                        <td class="max-sm:hidden">{{ item.type }}</td>
                        <td class="max-lg:hidden">{{ date(item.updated_at) }}</td>
                        <td class="actions"><div class="flex justify-end gap-1">
                            <UiButton :href="item.management_url" variant="ghost" size="sm">Edit</UiButton>
                            <RowMenu :items="menuFor(item)" :label="`Actions for ${item.title}`" />
                        </div></td>
                    </tr></tbody>
                </UiTable>
                <p v-else class="py-4 text-sm text-slate-500 dark:text-slate-400">No recent activity. Published artifacts, collections, and reports appear here.</p>
                <ShareDialog :target="shareTarget" @close="shareTarget = null" />
            </section>
            <section>
                <div class="mb-3 flex items-center justify-between gap-3">
                    <h2 class="text-base font-medium text-slate-900 dark:text-white">Recent projects</h2>
                    <UiButton href="/projects" variant="ghost" size="sm">All projects</UiButton>
                </div>
                <UiTable v-if="projects.length" label="Recent projects" class="dashboard-projects">
                    <thead><tr><th>Name</th><th class="max-sm:hidden">Language</th><th class="actions"><span class="sr-only">Actions</span></th></tr></thead>
                    <tbody><tr v-for="project in projects.filter((p) => p.status === 'active').slice(0, 4)" :key="project.id">
                        <td class="strong max-w-96 truncate"><Link :href="`/projects/${project.slug}`" class="block truncate py-0.5 hover:underline">{{ project.name }}</Link></td>
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
