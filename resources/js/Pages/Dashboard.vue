<script setup>
import RowMenu from '@/Components/RowMenu.vue';
import ShareDialog from '@/Components/ShareDialog.vue';
import { ask } from '@/confirm.js';
import { computed, ref } from 'vue';
import UiTabs from '@/Components/UiTabs.vue';
import CopyBlock from '@/Components/CopyBlock.vue';
import InfoPopover from '@/Components/InfoPopover.vue';
import PublishExamples from '@/Components/PublishExamples.vue';
import CollapsibleSection from '@/Components/CollapsibleSection.vue';
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
    { value: 'fw', label: 'CLI' },
];
const signIn = computed(() => ({
    r: { cmd: `framework::cloud_login(${named.value ? `url = "${server.value}"` : ''})` },
    python: { cmd: `import framework; framework.cloud_login(${named.value ? `url="${server.value}"` : ''})` },
    stata: { cmd: `fw_login${named.value ? `, url(${server.value})` : ''}` },
    fw: { cmd: `fw login${named.value ? ` --url ${server.value}` : ''}` },
}[client.value]));
// R installs from GitHub, which CRAN can lag behind. Both installs work on a
// fresh machine and update an existing install: they set a CRAN mirror, add
// any missing helpers, then always install framework.
const mirror = 'options(repos = c(CRAN = "https://cloud.r-project.org"))';
const githubInstall = `${mirror}
for (pkg in c("remotes", "httr2")) {
  if (!requireNamespace(pkg, quietly = TRUE)) install.packages(pkg)
}
remotes::install_github("frameworktools/framework", upgrade = "never")
packageVersion("framework")`;
const cranInstall = `${mirror}
for (pkg in c("httr2")) {
  if (!requireNamespace(pkg, quietly = TRUE)) install.packages(pkg)
}
install.packages("framework")
packageVersion("framework")`;
const signInInfo = 'Opens a browser to approve this computer. It stays signed in. Scripts and jobs that can\'t wait for a browser use a token.';
const highlighting = { r: 'r', python: 'python', stata: 'stata', fw: 'bash' };
const lag = 'We release to CRAN at most every two weeks or so, out of respect for the CRAN maintainers\' time';
const scaffoldNote = 'starts with scaffold(), which loads the project\'s packages and functions.';
// Each client's install choices (pills when there are several), its sign-in,
// and what it can create. Commands carry their own language for highlighting.
const quick = computed(() => ({
    r: {
        installs: [
            { key: 'latest', label: 'Latest (recommended)', cmd: githubInstall, language: 'r', info: `We recommend Latest. It installs the newest Framework from GitHub. ${lag}, so CRAN can lag behind. Adds remotes and httr2 first if they are missing. Restart R after updating.` },
            { key: 'cran', label: 'CRAN', cmd: cranInstall, language: 'r', info: `Installs the CRAN release, with httr2 for signing in. ${lag}, so it can lag behind Latest from GitHub.` },
        ],
        create: { title: 'Create resources in a project', note: null, commands: [
            { label: 'New notebook', cmd: 'framework::make_notebook("analysis")', language: 'r', info: `Fills in your defaults: the project's notebook format, notebooks folder, and author. The notebook ${scaffoldNote}` },
            { label: 'New script', cmd: 'framework::make_script("clean-data")', language: 'r', info: `Fills in your defaults: the project's scripts folder and author. The script ${scaffoldNote}` },
        ] },
        sync: { title: 'Sync with Framework Cloud', note: null, commands: [
            { label: 'Pull all', cmd: 'framework::pull_all()', language: 'r', info: 'Brings everything up to date with Framework Cloud: packages, skills, and the data registry. It lists the changes by part and asks once before applying them. Local edits are kept unless you ask to overwrite them. Data files never move.' },
            { label: 'Push all', cmd: 'framework::push_all()', language: 'r', info: 'Sends this project\'s packages, edited skills, and data registry to Framework Cloud. It lists the changes by part and asks once. If Cloud changed since your last pull, pull first. Data files never move.' },
            { label: 'Pull data registry', cmd: 'framework::pull_data_registry()', language: 'r', info: 'Brings the project\'s data registry up to date with Framework Cloud: data entry names, paths, and options. It lists the changes and asks before applying them. Local edits are kept unless you ask to overwrite them. Data files never move.' },
            { label: 'Push data registry', cmd: 'framework::push_data_registry()', language: 'r', info: 'Sends this project\'s data entries to Framework Cloud. It lists the changes and asks first. Data files and their hashes stay on this computer. If Cloud changed since your last pull, pull first.' },
            { label: 'Pull skills', cmd: 'framework::pull_skills()', language: 'r', info: 'Updates the project\'s AI skills to their latest versions and adds any new ones. Skills you edited are kept.' },
            { label: 'Push skills', cmd: 'framework::push_skills()', language: 'r', info: 'Sends skills you edited back to Framework Cloud as new versions. It warns first, because the new version replaces the skill wherever it\'s attached.' },
        ] },
    },
    python: {
        installs: [
            { key: 'latest', label: 'Latest (recommended)', cmd: 'pip install --upgrade "framework-py @ git+https://github.com/frameworktools/framework-py"', language: 'bash', info: 'We recommend Latest. It installs the newest framework-py from GitHub. PyPI releases come less often and can lag behind. Run it in a terminal, or use uv add with the same requirement.' },
            { key: 'pypi', label: 'PyPI', cmd: 'pip install --upgrade framework-py', language: 'bash', info: 'Installs the PyPI release. It can lag behind Latest from GitHub. Run it in a terminal, or use uv add framework-py.' },
        ],
        create: { title: 'Create resources in a project', note: null, commands: [
            { label: 'New notebook', cmd: 'import framework\nframework.make_notebook("analysis")', language: 'python', info: `Fills in your defaults: the project's notebook format, notebooks folder, and author. The notebook ${scaffoldNote}` },
            { label: 'New script', cmd: 'import framework\nframework.make_script("clean_data")', language: 'python', info: `Fills in your defaults: the project's scripts folder and author. The script ${scaffoldNote}` },
        ] },
        sync: { title: 'Sync with Framework Cloud', note: null, commands: [
            { label: 'Pull all', cmd: 'import framework\nframework.pull_all()', language: 'python', info: 'Brings everything up to date with Framework Cloud: packages, skills, and the data registry. It lists the changes by part and asks once before applying them. Local edits are kept unless you ask to overwrite them. Data files never move.' },
            { label: 'Push all', cmd: 'import framework\nframework.push_all()', language: 'python', info: 'Sends this project\'s packages, edited skills, and data registry to Framework Cloud. It lists the changes by part and asks once. If Cloud changed since your last pull, pull first. Data files never move.' },
            { label: 'Pull data registry', cmd: 'import framework\nframework.pull_data_registry()', language: 'python', info: 'Brings the project\'s data registry up to date with Framework Cloud: data entry names, paths, and options. It lists the changes and asks before applying them. Local edits are kept unless you ask to overwrite them. Data files never move.' },
            { label: 'Push data registry', cmd: 'import framework\nframework.push_data_registry()', language: 'python', info: 'Sends this project\'s data entries to Framework Cloud. It lists the changes and asks first. Data files and their hashes stay on this computer. If Cloud changed since your last pull, pull first.' },
            { label: 'Pull skills', cmd: 'framework.pull_skills()', language: 'python', info: 'Updates the project\'s AI skills to their latest versions and adds any new ones. Skills you edited are kept.' },
            { label: 'Push skills', cmd: 'framework.push_skills()', language: 'python', info: 'Sends skills you edited back to Framework Cloud as new versions. It warns first, because the new version replaces the skill wherever it\'s attached.' },
        ] },
    },
    stata: {
        installs: [
            { key: 'github', label: 'GitHub', cmd: 'net install framework, from("https://raw.githubusercontent.com/frameworktools/framework-stata/main") replace', language: 'stata', info: 'Installs the Framework commands for Stata 14 or newer from GitHub. Run it again to update. Signing in needs curl, which ships with macOS, Linux, and Windows 10 or later.' },
        ],
        create: { title: 'Create a project', note: 'Stata projects are made with the fw command-line tool.', commands: [
            { label: 'New Stata project', cmd: 'fw new my-study --lang stata', language: 'bash', info: 'Run it in a terminal. It asks where to create the project folder. Install fw from the CLI tab.' },
        ] },
        sync: { title: 'Sync with Framework Cloud', note: 'Run these with fw in a terminal, inside the project folder.', commands: [
            { label: 'Pull all', cmd: 'fw pull', language: 'bash', info: 'Brings everything up to date with Framework Cloud: packages, skills, and the data registry. It lists the changes by part and asks once before applying them. Local edits are kept unless you ask to overwrite them. Data files never move. Stata projects have no packages.' },
            { label: 'Push all', cmd: 'fw push', language: 'bash', info: 'Sends this project\'s packages, edited skills, and data registry to Framework Cloud. It lists the changes by part and asks once. If Cloud changed since your last pull, pull first. Data files never move. Stata projects have no packages.' },
            { label: 'Pull skills', cmd: 'fw skills pull', language: 'bash', info: 'Updates the project\'s AI skills to their latest versions and adds any new ones. Skills you edited are kept.' },
            { label: 'Push skills', cmd: 'fw skills push', language: 'bash', info: 'Sends skills you edited back to Framework Cloud as new versions. It warns first, because the new version replaces the skill wherever it\'s attached.' },
        ] },
    },
    fw: {
        installs: [
            { key: 'brew', label: 'Homebrew', cmd: 'brew install frameworktools/tap/fw', language: 'bash', info: 'Installs fw on macOS with Homebrew. Update it with brew upgrade fw. On Linux, clusters, and Windows, download it from the framework-cli releases on GitHub and put it on your PATH.', link: { href: 'https://github.com/frameworktools/framework-cli/releases', label: 'Releases' } },
        ],
        create: { title: 'Create a project', note: 'fw makes R, Python, and Stata projects.', commands: [
            { label: 'New project', cmd: 'fw new my-study', language: 'bash', info: 'Asks for the language and blueprint if you don\'t give them, then where to create the project folder.' },
        ] },
        sync: { title: 'Sync with Framework Cloud', note: 'Run these inside the project folder.', commands: [
            { label: 'Pull all', cmd: 'fw pull', language: 'bash', info: 'Brings everything up to date with Framework Cloud: packages, skills, and the data registry. It lists the changes by part and asks once before applying them. Local edits are kept unless you ask to overwrite them. Data files never move.' },
            { label: 'Push all', cmd: 'fw push', language: 'bash', info: 'Sends this project\'s packages, edited skills, and data registry to Framework Cloud. It lists the changes by part and asks once. If Cloud changed since your last pull, pull first. Data files never move. Stata projects have no packages.' },
            { label: 'Pull data registry', cmd: 'fw data pull', language: 'bash', info: 'Brings the project\'s data registry up to date with Framework Cloud: data entry names, paths, and options. It lists the changes and asks before applying them. Local edits are kept unless you ask to overwrite them. Data files never move.' },
            { label: 'Push data registry', cmd: 'fw data push', language: 'bash', info: 'Sends this project\'s data entries to Framework Cloud. It lists the changes and asks first. Data files and their hashes stay on this computer. If Cloud changed since your last pull, pull first.' },
            { label: 'Pull skills', cmd: 'fw skills pull', language: 'bash', info: 'Updates the project\'s AI skills to their latest versions and adds any new ones. Skills you edited are kept.' },
            { label: 'Push skills', cmd: 'fw skills push', language: 'bash', info: 'Sends skills you edited back to Framework Cloud as new versions. It warns first, because the new version replaces the skill wherever it\'s attached.' },
        ] },
    },
}[client.value]));
const installFrom = ref('latest');
const install = computed(() => quick.value.installs.find((i) => i.key === installFrom.value) ?? quick.value.installs[0]);
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
            <CollapsibleSection title="Recent activity" remember="dashboard_activity">
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
            </CollapsibleSection>
            <CollapsibleSection title="Recent projects" remember="dashboard_projects">
                <template #actions><UiButton href="/projects" variant="ghost" size="sm">All projects</UiButton></template>
                <UiTable v-if="projects.length" label="Recent projects" class="dashboard-projects">
                    <thead><tr><th>Name</th><th class="max-sm:hidden">Language</th><th class="actions"><span class="sr-only">Actions</span></th></tr></thead>
                    <tbody><tr v-for="project in projects.filter((p) => p.status === 'active').slice(0, 4)" :key="project.id">
                        <td class="strong max-w-96 truncate"><Link :href="`/projects/${project.slug}`" class="block truncate py-0.5 hover:underline">{{ project.name }}</Link></td>
                        <td class="max-sm:hidden">{{ languageNames[project.language] }}</td>
                        <td class="actions"><UiButton :href="`/projects/${project.slug}`" variant="ghost" size="sm">Open</UiButton></td>
                    </tr></tbody>
                </UiTable>
                <p v-else class="py-4 text-sm text-slate-500 dark:text-slate-400">No projects yet. Create a project to start.</p>
            </CollapsibleSection>
            <CollapsibleSection title="Quick commands" remember="dashboard_quick_commands">
                <p class="text-sm text-slate-500 dark:text-slate-400">Run these on your computer.</p>
                <UiTabs v-model="client" :tabs="clients" label="Client" class="mt-3 max-w-2xl" />
                <div class="mt-4 flex flex-col gap-6">
                    <div class="min-w-0">
                        <h3 class="mb-2 flex items-center gap-1.5 text-sm font-medium text-slate-800 dark:text-slate-200">
                            {{ client === 'fw' ? 'Install or update fw' : 'Install or update the package' }}
                            <InfoPopover :label="`installing from ${install.label}`">{{ install.info }}<template v-if="install.link"> <UiLink :href="install.link.href" external>{{ install.link.label }}</UiLink></template></InfoPopover>
                        </h3>
                        <div v-if="quick.installs.length > 1" role="radiogroup" aria-label="Install from" class="mb-2 inline-flex gap-1 rounded-lg bg-slate-100 p-1 dark:bg-slate-900">
                            <button v-for="option in quick.installs" :key="option.key" type="button" role="radio" :aria-checked="installFrom === option.key"
                                class="rounded-md px-2.5 py-0.5 text-xs font-medium transition" :class="pill(installFrom === option.key)" @click="installFrom = option.key">{{ option.label }}</button>
                        </div>
                        <CopyBlock :value="install.cmd" :label="`install from ${install.label}`" :language="install.language" />
                    </div>
                    <div class="min-w-0">
                        <h3 class="mb-2 flex items-center gap-1.5 text-sm font-medium text-slate-800 dark:text-slate-200">
                            Sign in
                            <InfoPopover label="signing in">{{ signInInfo }} <UiLink href="/tokens">Create a token</UiLink>.</InfoPopover>
                        </h3>
                        <CopyBlock :value="signIn.cmd" label="sign in" :language="highlighting[client]" />
                    </div>
                    <div v-for="group in [quick.create, quick.sync]" :key="`${client}-${group.title}`" class="min-w-0">
                        <h3 class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ group.title }}</h3>
                        <p v-if="group.note" class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ group.note }}</p>
                        <div class="mt-3 grid gap-x-6 gap-y-5 lg:grid-cols-2">
                            <div v-for="command in group.commands" :key="`${client}-${command.label}`" class="min-w-0">
                                <h4 class="mb-2 flex items-center gap-1.5 text-sm font-medium text-slate-700 dark:text-slate-300">
                                    {{ command.label }}
                                    <InfoPopover :label="command.label">{{ command.info }}</InfoPopover>
                                </h4>
                                <CopyBlock :value="command.cmd" :label="command.label.toLowerCase()" :language="command.language" />
                            </div>
                        </div>
                    </div>
                    <PublishExamples embedded :language="client" />
                </div>
            </CollapsibleSection>
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
