<script setup>
import { computed, ref, watch, onBeforeUnmount, onMounted } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import ProjectLayout from '@/Layouts/ProjectLayout.vue';
import UiButton from '@/Components/UiButton.vue';
import UiCallout from '@/Components/UiCallout.vue';
import UiBadge from '@/Components/UiBadge.vue';
import UiPanel from '@/Components/UiPanel.vue';
import CodeChip from '@/Components/CodeChip.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { ChevronRightIcon } from '@heroicons/vue/24/outline';
import { clientUrl, useServerArg, useServerFlag, useSetupCommand } from '@/cloud.js';
import { languageNames } from '@/library.js';
import { statusLabel, statusVariant, typeName } from '@/projects.js';

const props = defineProps({ project: Object, section: String, setup: Object, downloads: Boolean });

const sectionTitles = { overview: 'Overview', setup: 'Project setup', github: 'GitHub & Pages', published: 'Published documents', computers: 'Computers & tokens' };
const kindTitles = { structure: 'Project structure', tool: 'Tools', package_set: 'Package sets', skill_set: 'Skill sets', setting: 'Settings' };
const piecePaths = { structure: 'structures', package_set: 'package-sets', skill_set: 'skill-sets' };
const pieceGroups = computed(() => Object.keys(kindTitles)
    .map((kind) => ({ kind, title: kindTitles[kind], pieces: (props.setup?.pieces ?? []).filter((p) => p.kind === kind) }))
    .filter((g) => g.pieces.length));
const hookNames = { data_security: 'Check for secrets', check_sensitive_dirs: 'Warn about private folders git would commit' };

const page = usePage();

// The address setup commands name (FW_CLIENT_URL or APP_URL), checked from
// this browser: a computer that can't reach it can't set the project up
const serverUrl = computed(() => clientUrl(page));
const viewingOrigin = typeof window !== 'undefined' ? window.location.origin : '';
const viewingElsewhere = computed(() => viewingOrigin !== serverUrl.value);
const reachable = ref(null);
const checkServer = async () => {
    reachable.value = 'checking';
    try {
        await fetch(`${serverUrl.value}/up`, { mode: 'no-cors', cache: 'no-store' });
        reachable.value = 'yes';
    } catch {
        reachable.value = 'no';
    }
};
const serverArg = useServerArg();
const serverFlag = useServerFlag();
const setupCommand = useSetupCommand();
const pullToken = computed(() => (page.props.flash.status === 'project-token' ? page.props.flash.token : null));
const githubError = computed(() => page.props.errors.github);

// A zip built fresh from the project's layers. The browser shows its own
// progress, so the note only covers the few seconds before the file arrives
const downloadUrl = computed(() => `/projects/${props.project.id}/download`);
const downloadError = computed(() => page.props.errors.download);
const justCreated = page.props.flash.status === 'project-download';
const preparing = ref(false);
let preparingTimer = null;
const download = () => {
    preparing.value = true;
    clearTimeout(preparingTimer);
    preparingTimer = setTimeout(() => { preparing.value = false; }, 15000);
    window.location.href = downloadUrl.value;
};
onMounted(() => { if (justCreated && props.downloads) download(); });
onBeforeUnmount(() => clearTimeout(preparingTimer));

const github = computed(() => props.project.github);
const refresh = () => router.post(`/projects/${props.project.id}/scaffold/refresh`, {}, { preserveScroll: true });
const retry = () => router.post(`/projects/${props.project.id}/scaffold/retry`, {}, { preserveScroll: true });

const pagesBusy = ref(false);
const enablePages = () => {
    const where = github.value.private ? ' The repository is private, but the site will not be.' : '';
    if (!confirm(`Publish the published/ folder of ${github.value.repo} with GitHub Pages? Anything in that folder will be public on the web.${where}`)) return;
    pagesBusy.value = true;
    router.post(`/projects/${props.project.id}/pages`, {}, { preserveScroll: true, onFinish: () => { pagesBusy.value = false; } });
};
const disablePages = () => {
    if (!confirm('Turn off GitHub Pages for this project? The site goes offline. The published/ folder stays in the repository.')) return;
    pagesBusy.value = true;
    router.delete(`/projects/${props.project.id}/pages`, { preserveScroll: true, onFinish: () => { pagesBusy.value = false; } });
};

// Poll while the scaffold is in progress; the webhook updates the project
const inProgress = computed(() => ['pending', 'running'].includes(github.value?.status));
let timer = null;
watch(inProgress, (value) => {
    clearInterval(timer);
    if (value) timer = setInterval(() => router.reload({ only: ['project'] }), 10000);
}, { immediate: true });
onBeforeUnmount(() => clearInterval(timer));

const issueToken = () => router.post(`/projects/${props.project.id}/token`, {}, { preserveScroll: true });
const revokeToken = (tokenId) => {
    if (confirm('Revoke this token? Computers that use it can no longer pull settings or publish.')) {
        router.delete(`/projects/${props.project.id}/tokens/${tokenId}`, { preserveScroll: true });
    }
};
const destroy = () => {
    if (confirm(`Delete ${props.project.name}? Copies on computers and on GitHub are not affected, but the project's tokens stop working.`)) {
        router.delete(`/projects/${props.project.id}`);
    }
};

const date = (value) => (value ? new Date(value).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' }) : 'never');

const rowClass = 'flex flex-col gap-1 py-3 first:pt-0 last:pb-0 sm:flex-row sm:items-center sm:gap-4';
const termClass = 'w-36 shrink-0 text-sm font-medium text-slate-900 dark:text-white';
</script>

<template>
    <Head :title="project.name" />

    <ProjectLayout :project="project">
        <div class="flex flex-col gap-8">
            <PageHeader :title="section === 'overview' ? project.name : sectionTitles[section]">
                <template #description>
                    <template v-if="section === 'overview'">
                        <span class="font-mono text-xs">{{ project.slug }}</span><span v-if="project.description"> · {{ project.description }}</span>
                    </template>
                    <template v-else>{{ project.name }}</template>
                </template>
                <template v-if="downloads && (section === 'overview' || section === 'computers')" #actions>
                    <UiButton variant="outline" size="sm" :disabled="preparing" @click="download">{{ preparing ? 'Preparing…' : 'Download zip' }}</UiButton>
                </template>
            </PageHeader>

            <UiCallout v-if="downloadError" variant="danger" heading="Download">{{ downloadError }}</UiCallout>
            <UiCallout v-else-if="justCreated && section === 'overview'" variant="success" heading="Your project is downloading">
                <p class="text-sm">
                    Unzip <span class="font-mono text-xs">{{ project.slug }}.zip</span> and open the folder in RStudio, Positron, VS Code, or Stata.
                    If the download doesn't start, use Download zip.
                </p>
                <p class="text-sm">
                    To connect the folder to Framework Cloud later, get a project token under
                    <Link :href="`/projects/${project.slug}/computers`" class="underline">Computers &amp; tokens</Link>
                    and paste it into the folder's <span class="font-mono text-xs">.env</span> file, where a line marks the spot.
                </p>
            </UiCallout>

            <UiCallout v-if="githubError && section === 'github'" variant="danger" heading="GitHub">{{ githubError }}</UiCallout>

            <UiCallout v-if="pullToken && section === 'computers'" variant="success" heading="Project token. Copy it now; it won't be shown again.">
                <code class="break-all font-mono text-sm">{{ pullToken }}</code>
                <code class="break-all font-mono text-xs">{{ setupCommand(page.props.flash.token_language, pullToken) }}</code>
                <p v-if="page.props.flash.token_language === 'stata'" class="text-sm">
                    Paste the token when fw asks for it. If you have run <code class="font-mono text-xs">fw login</code>,
                    <code class="font-mono text-xs">fw new --from {{ project.slug }}{{ serverFlag }}</code> needs no token.
                </p>
                <!-- Where the command connects, and whether this computer can reach it -->
                <p class="text-sm">
                    <template v-if="page.props.flash.token_language === 'python'">Python connects to the server you signed in to with <code class="font-mono text-xs">cloud_login()</code>, which should be {{ serverUrl }}.</template>
                    <template v-else>The command connects to {{ serverUrl }}.</template>
                    <template v-if="viewingElsewhere"> You're viewing this page at {{ viewingOrigin }}, so check that your computer can reach it.</template>
                    <button v-if="reachable !== 'checking'" type="button" class="ml-1 text-sky-700 underline dark:text-sky-400" @click="checkServer">Check browser connection</button>
                    <span v-else class="ml-1 text-slate-500">Checking…</span>
                </p>
                <p v-if="reachable === 'yes'" class="text-sm">This browser reached {{ serverUrl }}.</p>
                <p v-else-if="reachable === 'no'" class="text-sm text-red-700 dark:text-red-300">This browser couldn't reach {{ serverUrl }}. Ask whoever runs this server to set FW_CLIENT_URL to an address your computer can reach.</p>
            </UiCallout>

            <UiPanel v-if="section === 'overview'" title="Configuration">
                <template #header>
                    <Link :href="`/projects/${project.slug}/setup`" class="text-sm text-sky-600 hover:underline dark:text-sky-400">Project setup</Link>
                </template>
                <dl class="flex flex-col divide-y divide-slate-200 dark:divide-slate-700/70">
                    <div :class="rowClass">
                        <dt :class="termClass">Blueprint</dt>
                        <dd class="text-sm text-slate-700 dark:text-slate-300">
                            <Link
                                v-if="project.blueprint" :href="`/blueprints/${project.blueprint.id}?version=${project.blueprint.version}`"
                                class="hover:text-sky-600 hover:underline dark:hover:text-sky-400"
                            >{{ project.blueprint.name }} <span class="font-mono text-xs text-slate-500">{{ project.blueprint.handle }} v{{ project.blueprint.version }}</span></Link>
                            <span v-else class="text-slate-500">None</span>
                        </dd>
                    </div>
                    <div :class="rowClass">
                        <dt :class="termClass">Structure</dt>
                        <dd class="text-sm text-slate-700 dark:text-slate-300">{{ typeName(project.project_type) }}</dd>
                    </div>
                    <div :class="rowClass">
                        <dt :class="termClass">Language</dt>
                        <dd class="text-sm text-slate-700 dark:text-slate-300">{{ languageNames[project.language] }}</dd>
                    </div>
                    <div :class="rowClass">
                        <dt :class="termClass">Created</dt>
                        <dd class="text-sm text-slate-700 dark:text-slate-300">{{ date(project.created_at) }}</dd>
                    </div>
                </dl>
            </UiPanel>

            <UiPanel v-if="section === 'overview'" title="Connections">
                <dl class="flex flex-col divide-y divide-slate-200 dark:divide-slate-700/70">
                    <div :class="rowClass">
                        <dt :class="termClass">GitHub</dt>
                        <dd class="flex flex-wrap items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                            <UiBadge v-if="github?.status && github.status !== 'done'" :variant="statusVariant[github.status]">{{ statusLabel[github.status] }}</UiBadge>
                            <a v-if="github?.repo" :href="github.url" class="font-mono text-xs hover:underline">{{ github.repo }}</a>
                            <UiBadge v-if="github?.pages_url" variant="green">Pages</UiBadge>
                            <Link v-if="!github" :href="`/projects/${project.slug}/github`" class="text-slate-500 hover:underline">Not on GitHub</Link>
                        </dd>
                    </div>
                    <div :class="rowClass">
                        <dt :class="termClass">Computers</dt>
                        <dd class="text-sm text-slate-700 dark:text-slate-300">
                            <Link :href="`/projects/${project.slug}/computers`" class="hover:underline">
                                {{ project.tokens.length ? `${project.tokens.length} ${project.tokens.length === 1 ? 'token' : 'tokens'}` : 'None connected' }}
                            </Link>
                        </dd>
                    </div>
                    <div :class="rowClass">
                        <dt :class="termClass">Last sync</dt>
                        <dd class="text-sm text-slate-700 dark:text-slate-300">
                            <template v-if="project.spec">{{ date(project.spec.received_at) }}</template>
                            <span v-else class="text-slate-500">No computer has sent this project's settings yet.</span>
                        </dd>
                    </div>
                    <div :class="rowClass">
                        <dt :class="termClass">Published</dt>
                        <dd class="text-sm text-slate-700 dark:text-slate-300">
                            <Link :href="`/projects/${project.slug}/published`" class="hover:underline">
                                {{ project.documents.length ? `${project.documents.length} ${project.documents.length === 1 ? 'document' : 'documents'}` : 'Nothing yet' }}
                            </Link>
                        </dd>
                    </div>
                </dl>
                <details v-if="project.spec" class="group">
                    <summary class="inline-flex cursor-pointer items-center gap-1 text-sm text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-100">
                        <ChevronRightIcon class="size-3 transition-transform group-open:rotate-90" />
                        Settings from the last sync
                    </summary>
                    <pre class="mt-3 max-h-96 overflow-auto rounded-lg bg-slate-900 p-4 font-mono text-xs text-slate-100 dark:bg-slate-950 dark:ring-1 dark:ring-slate-700">{{ project.spec.json }}</pre>
                </details>
            </UiPanel>

            <UiPanel v-if="section === 'setup'" title="What the project is made of">
                <template #header>
                    <UiBadge v-if="setup.customized">Customized</UiBadge>
                </template>
                <p class="text-sm text-slate-600 dark:text-slate-400">
                    <template v-if="project.blueprint">
                        From the blueprint
                        <Link :href="`/blueprints/${project.blueprint.id}?version=${project.blueprint.version}`" class="text-sky-600 hover:underline dark:text-sky-400">{{ project.blueprint.name }}</Link>
                        v{{ project.blueprint.version }}<template v-if="setup.customized">, with pieces changed when the project was made</template>.
                    </template>
                    <template v-else>Made from pieces chosen one by one.</template>
                    This is what computers receive when they set the project up.
                </p>
                <dl class="flex flex-col divide-y divide-slate-200 dark:divide-slate-700/70">
                    <div v-for="group in pieceGroups" :key="group.kind" :class="rowClass">
                        <dt :class="termClass">{{ group.title }}</dt>
                        <dd class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-slate-700 dark:text-slate-300">
                            <span v-for="piece in group.pieces" :key="piece.handle">
                                <Link v-if="piecePaths[piece.kind]" :href="`/${piecePaths[piece.kind]}/${piece.id}/edit`" class="hover:text-sky-600 hover:underline dark:hover:text-sky-400">{{ piece.name }}</Link>
                                <template v-else>{{ piece.name }}</template>
                                <span v-if="piece.version" class="ml-1 font-mono text-xs text-slate-500">v{{ piece.version }}</span>
                            </span>
                        </dd>
                    </div>
                    <div :class="rowClass">
                        <dt :class="termClass">Skills</dt>
                        <dd class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-slate-700 dark:text-slate-300">
                            <span v-for="skill in setup.skills" :key="skill.key">{{ skill.name }}</span>
                            <span v-if="!setup.skills.length" class="text-slate-500">None</span>
                        </dd>
                    </div>
                    <div :class="rowClass">
                        <dt :class="termClass">Language</dt>
                        <dd class="text-sm text-slate-700 dark:text-slate-300">{{ languageNames[project.language] }}</dd>
                    </div>
                    <div :class="rowClass">
                        <dt :class="termClass">Sensitive data</dt>
                        <dd class="text-sm text-slate-700 dark:text-slate-300">{{ setup.settings.sensitive ? 'Yes' : 'No' }}</dd>
                    </div>
                    <div :class="rowClass">
                        <dt :class="termClass">Agents</dt>
                        <dd class="text-sm text-slate-700 dark:text-slate-300">{{ setup.settings.agents ? 'Set up' : 'Off' }}</dd>
                    </div>
                    <div :class="rowClass">
                        <dt :class="termClass">Git</dt>
                        <dd class="text-sm text-slate-700 dark:text-slate-300">
                            {{ setup.settings.git ? 'Yes' : 'No' }}<template v-if="setup.settings.git && setup.settings.hooks.length">, with hooks: {{ setup.settings.hooks.map((h) => hookNames[h] ?? h).join(', ') }}</template>
                        </dd>
                    </div>
                </dl>
            </UiPanel>

            <UiPanel v-if="section === 'github'" title="GitHub & Pages">
                <template v-if="github?.repo || github?.status">
                    <div class="flex flex-col gap-3 text-sm">
                        <div class="flex flex-wrap items-center gap-2">
                            <UiBadge :variant="statusVariant[github.status]">{{ statusLabel[github.status] }}</UiBadge>
                            <a v-if="github.url" :href="github.url" class="font-mono text-xs text-slate-700 hover:underline dark:text-slate-300">{{ github.repo }}</a>
                            <span v-if="github.private" class="text-xs text-slate-500">private</span>
                            <a v-if="github.run_url" :href="github.run_url" class="text-xs text-slate-500 hover:underline">Run log</a>
                            <UiButton v-if="github.status === 'running'" variant="ghost" size="sm" @click="refresh">Check status</UiButton>
                            <UiButton v-if="github.status === 'failed' && github.url" variant="outline" size="sm" @click="retry">Retry</UiButton>
                        </div>
                        <p v-if="github.error" class="text-red-600 dark:text-red-400">{{ github.error }}</p>
                        <div v-if="github.status === 'done'"><CodeChip>git clone {{ github.url }}.git</CodeChip></div>
                    </div>

                    <div v-if="github.status === 'done'" class="flex flex-col gap-3 border-t border-slate-200 pt-5 dark:border-slate-700">
                        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">GitHub Pages</h3>
                        <div v-if="github.pages_url" class="flex flex-wrap items-center gap-2 text-sm">
                            <UiBadge variant="green">Publishing</UiBadge>
                            <a :href="github.pages_url" class="font-mono text-xs text-slate-700 hover:underline dark:text-slate-300">{{ github.pages_url }}</a>
                            <span class="text-xs text-slate-500">Public. Deploys the published/ folder on each push.</span>
                            <UiButton variant="ghost" size="sm" :disabled="pagesBusy" @click="disablePages">Turn off</UiButton>
                        </div>
                        <div v-else class="flex flex-wrap items-center gap-3 text-sm">
                            <UiButton variant="outline" size="sm" :disabled="pagesBusy" @click="enablePages">
                                {{ pagesBusy ? 'Setting up…' : 'Publish with GitHub Pages' }}
                            </UiButton>
                            <span class="text-xs text-slate-500">Free. Notebooks you render into published/ go on a public site.</span>
                        </div>
                    </div>
                </template>
                <p v-else class="text-sm text-slate-600 dark:text-slate-400">This project was set up for a computer, so it has no repository here.</p>
            </UiPanel>

            <UiPanel v-if="section === 'published'" title="Published documents">
                <template #header>
                    <Link v-if="project.documents.length" href="/published" class="text-sm text-slate-600 hover:text-slate-900 hover:underline dark:text-slate-400 dark:hover:text-slate-100">Manage</Link>
                </template>
                <ul v-if="project.documents.length" class="flex flex-col divide-y divide-slate-200 text-sm dark:divide-slate-700/70">
                    <li v-for="doc in project.documents" :key="doc.id" class="flex flex-wrap items-center gap-x-3 gap-y-1 py-2 first:pt-0 last:pb-0">
                        <a :href="doc.url" class="font-medium text-slate-900 hover:underline dark:text-white">{{ doc.title }}</a>
                        <UiBadge v-if="!doc.readable">Private link</UiBadge>
                        <span class="flex-1 text-xs text-slate-500">{{ doc.versions || 1 }} {{ doc.versions > 1 ? 'versions' : 'version' }} · updated {{ date(doc.updated_at) }}</span>
                    </li>
                </ul>
                <p v-else class="text-sm text-slate-600 dark:text-slate-400">Documents you publish from this project appear here.</p>
            </UiPanel>

            <UiPanel v-if="section === 'computers'" title="Computers & tokens" description="A project token lets a computer set this project up and pull its settings.">
                <template #header>
                    <UiButton variant="outline" size="sm" @click="issueToken">Get token</UiButton>
                </template>
                <div class="text-sm">
                    <CodeChip v-if="project.language === 'r'">framework::setup("&lt;project-token&gt;"{{ serverArg }})</CodeChip>
                    <CodeChip v-else-if="project.language === 'python'">framework.new(key="&lt;project-token&gt;")</CodeChip>
                    <span v-else class="flex flex-wrap items-center gap-2 text-slate-600 dark:text-slate-400">
                        <CodeChip>fw login{{ serverFlag }}</CodeChip>
                        <span>then</span>
                        <CodeChip>fw new --from {{ project.slug }}{{ serverFlag }}</CodeChip>
                    </span>
                </div>
                <p v-if="downloads" class="text-sm text-slate-600 dark:text-slate-400">
                    Downloaded the project as a zip? Get a token, open the folder's <span class="font-mono text-xs">.env</span> file,
                    remove the <span class="font-mono text-xs">#</span> in front of <span class="font-mono text-xs">FW_PROJECT_TOKEN=</span>, and paste the token after the
                    <span class="font-mono text-xs">=</span>. If you use fw, run <CodeChip>fw project link --from {{ project.slug }}{{ serverFlag }}</CodeChip> in the folder instead.
                </p>
                <ul v-if="project.tokens.length" class="flex flex-col divide-y divide-slate-200 rounded-md bg-white text-xs dark:divide-slate-700 dark:bg-slate-900">
                    <li v-for="token in project.tokens" :key="token.id" class="flex flex-wrap items-center gap-x-3 gap-y-1 px-3 py-2">
                        <span class="font-medium text-slate-700 dark:text-slate-300">{{ token.name }}</span>
                        <span class="flex-1 text-slate-500">Created {{ date(token.created_at) }} · last used {{ date(token.last_used_at) }}</span>
                        <button type="button" class="text-red-600 hover:underline dark:text-red-400" @click="revokeToken(token.id)">Revoke</button>
                    </li>
                </ul>
            </UiPanel>

            <UiPanel v-if="section === 'setup'" title="Delete this project" description="Copies on computers and on GitHub are not affected, but the project's tokens stop working.">
                <div><UiButton variant="danger" @click="destroy">Delete project</UiButton></div>
            </UiPanel>
        </div>
    </ProjectLayout>
</template>
