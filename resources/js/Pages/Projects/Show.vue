<script setup>
import RowMenu from '@/Components/RowMenu.vue';
import ShareDialog from '@/Components/ShareDialog.vue';
import ShareQr from '@/Components/ShareQr.vue';
import { ask } from '@/confirm.js';
import { computed, ref, watch, onBeforeUnmount, onMounted } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import ProjectLayout from '@/Layouts/ProjectLayout.vue';
import UiButton from '@/Components/UiButton.vue';
import UiCallout from '@/Components/UiCallout.vue';
import UiBadge from '@/Components/UiBadge.vue';
import UiPanel from '@/Components/UiPanel.vue';
import UiLink from '@/Components/UiLink.vue';
import UiSection from '@/Components/UiSection.vue';
import UiTable from '@/Components/UiTable.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { artifactKind } from '@/report-artifacts.js';
import { ArrowTopRightOnSquareIcon, LockClosedIcon } from '@heroicons/vue/20/solid';
import { GlobeAltIcon, LinkIcon } from '@heroicons/vue/24/outline';
import ArtifactUpload from '@/Components/ArtifactUpload.vue';
import CodeChip from '@/Components/CodeChip.vue';
import ProjectGitHub from '@/Components/ProjectGitHub.vue';
import ProjectToken from '@/Components/ProjectToken.vue';
import PublishExamples from '@/Components/PublishExamples.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { ChevronRightIcon } from '@heroicons/vue/24/outline';
import { usePythonServerArg, useServerArg, useServerFlag } from '@/cloud.js';
import { languageNames } from '@/library.js';
import { statusLabel, statusVariant, typeName } from '@/projects.js';

const props = defineProps({ project: Object, section: String, setup: Object, githubSetup: Object, downloads: Boolean, canPublish: Boolean });
const publishing = ref(false);

const sectionTitles = { overview: 'Overview', setup: 'Project setup', github: 'GitHub & Pages', published: 'Artifacts', computers: 'Computers & tokens' };
const kindTitles = { structure: 'Project structure', tool: 'Tools', package_set: 'Package sets', skill_set: 'Skill sets', setting: 'Settings' };
const piecePaths = { structure: 'structures', package_set: 'package-sets', skill_set: 'skill-sets' };
const pieceGroups = computed(() => Object.keys(kindTitles)
    .map((kind) => ({ kind, title: kindTitles[kind], pieces: (props.setup?.pieces ?? []).filter((p) => p.kind === kind) }))
    .filter((g) => g.pieces.length));
const hookNames = { data_security: 'Check for secrets', check_sensitive_dirs: 'Warn about private folders git would commit' };

const page = usePage();

const serverArg = useServerArg();
const pythonServerArg = usePythonServerArg();
const serverFlag = useServerFlag();
// The language the flashed token was issued for
const tokenLanguage = computed(() => page.props.flash.token_language ?? props.project.language);
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
const githubOpen = ref(false);

// Overview's installation boxes, as New project's destinations describe them
const localText = computed(() => ({
    stata: 'In a terminal, run fw login, then fw new with the project name. Framework sets up Git.',
    python: 'Get a project token and pass it to framework.new(key=...) in Python, or to fw new in a terminal.',
}[props.project.language] ?? 'Get a project token and pass it to framework::setup() in R, or to fw new in a terminal.'));
const installCards = computed(() => 2 + (props.downloads ? 1 : 0));

const pagesBusy = ref(false);
const enablePages = async () => {
    const where = github.value.private ? ' The repository is private, but the site will not be.' : '';
    if (!await ask(`Publish the published/ folder of ${github.value.repo} with GitHub Pages? Anything in that folder will be public on the web.${where}`)) return;
    pagesBusy.value = true;
    router.post(`/projects/${props.project.id}/pages`, {}, { preserveScroll: true, onFinish: () => { pagesBusy.value = false; } });
};
const disablePages = async () => {
    if (!await ask('Turn off GitHub Pages for this project? The site goes offline. The published/ folder stays in the repository.')) return;
    pagesBusy.value = true;
    router.delete(`/projects/${props.project.id}/pages`, { preserveScroll: true, onFinish: () => { pagesBusy.value = false; } });
};

// Poll while the scaffold is in progress; the webhook updates the project
const inProgress = computed(() => ['pending', 'running'].includes(github.value?.status) || ['pending', 'running'].includes(github.value?.update?.status));
let timer = null;
watch(inProgress, (value) => {
    clearInterval(timer);
    if (value) timer = setInterval(() => router.reload({ only: ['project'] }), 10000);
}, { immediate: true });
onBeforeUnmount(() => clearInterval(timer));

const issueToken = () => router.post(`/projects/${props.project.id}/token`, {}, { preserveScroll: true });
const revokeToken = async (tokenId) => {
    if (await ask('Revoke this token? Computers that use it can no longer pull settings or publish.')) {
        router.delete(`/projects/${props.project.id}/tokens/${tokenId}`, { preserveScroll: true });
    }
};
const setStatus = (status) => router.put(`/projects/${props.project.id}/status`, { status }, { preserveScroll: true });
const statusNames = { active: 'Active', triaged: 'Triaged', completed: 'Completed', inactive: 'Inactive' };
const setPublicDownload = (on) => router.put(`/projects/${props.project.id}/public-download`, { on }, { preserveScroll: true });
const destroy = async () => {
    if (await ask(`Delete ${props.project.name}? Its tokens stop working, and its artifacts, collections, and reports go offline. Copies on computers and on GitHub stay.`)) {
        router.delete(`/projects/${props.project.id}`);
    }
};

const date = (value) => (value ? new Date(value).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' }) : 'never');

const rowClass = 'flex flex-col gap-1 py-3 first:pt-0 last:pb-0 sm:flex-row sm:items-center sm:gap-4';
const termClass = 'w-36 shrink-0 text-sm font-medium text-slate-900 dark:text-white';

const shareTarget = ref(null);
const artifactMenu = (doc) => [
    { label: 'Manage', href: `/artifacts/${doc.id}` },
    { label: 'Share', onSelect: () => { shareTarget.value = { type: 'artifact', id: doc.id, title: doc.title, settings: `/artifacts/${doc.id}?tab=sharing` }; } },
    { divider: true },
    // A linked artifact belongs to another project: take it off this one instead
    doc.linked_from
        ? { label: 'Remove from this project', danger: true, onSelect: async () => { if (await ask(`Remove ${doc.title} from ${props.project.name}? It stays in its own project.`)) router.delete(`/artifacts/${doc.id}/projects/${props.project.id}`, { preserveScroll: true }); } }
        : { label: 'Delete', danger: true, onSelect: async () => { if (await ask(`Delete ${doc.title}? It goes to the Trash, where you can restore it.`)) router.delete(`/published/${doc.id}`, { preserveScroll: true }); } },
];
</script>

<template>
    <Head :title="project.name" />

    <ProjectLayout :project="project">
        <div class="flex flex-col gap-8">
            <PageHeader :title="section === 'overview' ? project.name : sectionTitles[section]">
                <template #description>
                    <template v-if="section === 'overview'">
                        <span v-if="project.slug !== project.name" class="font-mono text-xs">{{ project.slug }}</span><span v-if="project.description"><template v-if="project.slug !== project.name"> · </template>{{ project.description }}</span>
                    </template>
                    <template v-else>{{ project.name }}</template>
                </template>
                <template v-if="downloads && section === 'computers'" #actions>
                    <UiButton variant="outline" size="sm" :disabled="preparing" @click="download">{{ preparing ? 'Preparing…' : 'Download zip' }}</UiButton>
                </template>
            </PageHeader>

            <UiCallout v-if="downloadError" variant="danger" heading="Download">{{ downloadError }}</UiCallout>
            <UiCallout v-else-if="justCreated && section === 'overview'" variant="success" heading="Project download">
                <p class="text-sm">
                    Unzip <span class="font-mono text-xs">{{ project.slug }}.zip</span> and open the folder in RStudio, Positron, VS Code, or Stata.
                    If the download doesn't start, use Download zip below.
                </p>
                <p class="text-sm">
                    To connect the folder to Framework Cloud later, get a project token under Local setup
                    and paste it into the folder's <span class="font-mono text-xs">.env</span> file, where a line marks the spot.
                </p>
            </UiCallout>

<UiCallout v-if="page.props.flash.status === 'project-updated'" variant="success" heading="Project saved">
                <p class="text-sm">New tokens, setup commands, and downloads use the saved setup. Folders already set up keep their files.<template v-if="github?.repo"> {{ github.repo }} keeps its current files until you use Update on GitHub.</template></p>
            </UiCallout>

            <UiCallout v-if="githubError && section === 'github'" variant="danger" heading="GitHub">{{ githubError }}</UiCallout>

            <UiCallout v-if="page.props.flash.project_created && section === 'computers'" variant="success"><span class="font-medium text-slate-900 dark:text-slate-100">Project created</span></UiCallout>
            <section v-if="pullToken && section === 'computers'" aria-label="Project token" class="max-w-2xl">
                <ProjectToken :project="project" :token="pullToken" :language="tokenLanguage" />
            </section>

            <UiPanel v-if="section === 'overview'" flush title="Installation" description="Set the project up on a computer, on GitHub, or from a zip. Each uses the project's current setup.">
                <div class="grid gap-3" :class="installCards === 3 ? 'lg:grid-cols-3' : 'sm:grid-cols-2'">
                    <section class="flex min-w-0 flex-col gap-3 rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900" :class="pullToken ? (installCards === 3 ? 'lg:col-span-3' : 'sm:col-span-2') : ''" aria-labelledby="install-local">
                        <h3 id="install-local" class="font-medium text-slate-900 dark:text-white">Local setup</h3>
                        <ProjectToken v-if="pullToken" :project="project" :token="pullToken" :language="tokenLanguage" />
                        <template v-else>
                            <p class="flex-1 text-sm text-slate-600 dark:text-slate-400">{{ localText }}</p>
                            <div><UiButton variant="ghost" size="sm" @click="issueToken">Get token</UiButton></div>
                        </template>
                    </section>
                    <section class="flex min-w-0 flex-col gap-3 rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900" :class="githubOpen ? (installCards === 3 ? 'lg:col-span-3' : 'sm:col-span-2') : ''" aria-labelledby="install-github">
                        <h3 id="install-github" class="font-medium text-slate-900 dark:text-white">GitHub repository</h3>
                        <p v-if="!github?.repo" class="text-sm text-slate-600 dark:text-slate-400">Framework creates a repository on your GitHub account and sets the project up there. Clone it to work on it.</p>
                        <ProjectGitHub :project="project" :setup="githubSetup" collapsed @open="githubOpen = true" />
                    </section>
                    <section v-if="downloads" class="flex min-w-0 flex-col gap-3 rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900" aria-labelledby="install-zip">
                        <h3 id="install-zip" class="font-medium text-slate-900 dark:text-white">ZIP download</h3>
                        <p class="flex-1 text-sm text-slate-600 dark:text-slate-400">Framework builds the project and your browser downloads it. Unzip it and open the folder. You need no Git and no Framework packages.</p>
                        <div><UiButton variant="ghost" size="sm" :disabled="preparing" @click="download">{{ preparing ? 'Preparing…' : 'Download zip' }}</UiButton></div>
                    </section>
                </div>
            </UiPanel>

            <PublishExamples v-if="section === 'overview'" :language="project.language" remember="project_publish" />

            <UiPanel v-if="section === 'overview'" title="Configuration">
                <template #header>
                    <UiButton :href="`/projects/${project.slug}/setup`" variant="subtle" size="sm">Project setup</UiButton>
                    <UiButton :href="`/projects/${project.slug}/edit`" variant="ghost" size="sm">Edit project</UiButton>
                </template>
                <dl class="flex flex-col divide-y divide-slate-200 dark:divide-slate-700/70">
                    <div :class="rowClass">
                        <dt :class="termClass">Blueprint</dt>
                        <dd class="text-sm text-slate-700 dark:text-slate-300">
                            <Link
                                v-if="project.blueprint" :href="`/blueprints/${project.blueprint.id}?version=${project.blueprint.version}`"
                                class="hover:text-sky-700 hover:underline dark:hover:text-sky-400"
                            >{{ project.blueprint.name }} <span class="font-mono text-xs text-slate-500 dark:text-slate-400">{{ project.blueprint.handle }} v{{ project.blueprint.version }}</span></Link>
                            <span v-else class="text-slate-500 dark:text-slate-400">None</span>
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
                        <dt :class="termClass">{{ project.adopted_at ? 'Added' : 'Created' }}</dt>
                        <dd class="text-sm text-slate-700 dark:text-slate-300">
                            {{ date(project.adopted_at ?? project.created_at) }}
                            <span v-if="project.adopted_at" class="text-slate-500 dark:text-slate-400">from an existing project on a computer</span>
                        </dd>
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
                            <Link v-if="!github" :href="`/projects/${project.slug}/github`" class="text-slate-500 dark:text-slate-400 hover:underline">Not on GitHub</Link>
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
                            <span v-else class="text-slate-500 dark:text-slate-400">No computer has sent this project's settings yet.</span>
                        </dd>
                    </div>
                    <div :class="rowClass">
                        <dt :class="termClass">Published</dt>
                        <dd class="text-sm text-slate-700 dark:text-slate-300">
                            <Link :href="`/projects/${project.slug}/published`" class="hover:underline">
                                {{ project.documents.length ? `${project.documents.length} ${project.documents.length === 1 ? 'artifact' : 'artifacts'}` : 'Nothing yet' }}
                            </Link>
                        </dd>
                    </div>
                </dl>
                <details v-if="project.spec" class="group">
                    <summary class="inline-flex cursor-pointer items-center gap-1 text-sm text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-100">
                        <ChevronRightIcon class="size-3 transition-transform group-open:rotate-90" />
                        Last synced settings
                    </summary>
                    <pre class="mt-3 max-h-96 overflow-auto rounded-lg bg-slate-900 p-4 font-mono text-xs text-slate-100 dark:bg-slate-950 dark:ring-1 dark:ring-slate-700">{{ project.spec.json }}</pre>
                </details>
            </UiPanel>

            <UiPanel v-if="section === 'setup'" title="Project components">
                <template #header>
                    <UiBadge v-if="setup.customized">Customized</UiBadge>
                </template>
                <p class="text-sm text-slate-600 dark:text-slate-400">
                    <template v-if="project.blueprint">
                        From the blueprint
                        <Link :href="`/blueprints/${project.blueprint.id}?version=${project.blueprint.version}`" class="text-sky-700 hover:underline dark:text-sky-400">{{ project.blueprint.name }}</Link>
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
                                <Link v-if="piecePaths[piece.kind]" :href="`/${piecePaths[piece.kind]}/${piece.id}/edit`" class="hover:text-sky-700 hover:underline dark:hover:text-sky-400">{{ piece.name }}</Link>
                                <template v-else>{{ piece.name }}</template>
                                <span v-if="piece.version" class="ml-1 font-mono text-xs text-slate-500 dark:text-slate-400">v{{ piece.version }}</span>
                            </span>
                        </dd>
                    </div>
                    <div :class="rowClass">
                        <dt :class="termClass">Skills</dt>
                        <dd class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-slate-700 dark:text-slate-300">
                            <span v-for="skill in setup.skills" :key="skill.key">{{ skill.name }}</span>
                            <span v-if="!setup.skills.length" class="text-slate-500 dark:text-slate-400">None</span>
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
                        <dt :class="termClass">Agent files</dt>
                        <dd class="text-sm text-slate-700 dark:text-slate-300">{{ setup.settings.agents ? 'Yes' : 'No' }}</dd>
                    </div>
                    <div :class="rowClass">
                        <dt :class="termClass">Git</dt>
                        <dd class="text-sm text-slate-700 dark:text-slate-300">
                            {{ setup.settings.git ? 'Yes' : 'No' }}<template v-if="setup.settings.git && setup.settings.hooks.length">, with hooks: {{ setup.settings.hooks.map((h) => hookNames[h] ?? h).join(', ') }}</template>
                        </dd>
                    </div>
                </dl>
            </UiPanel>

            <UiPanel v-if="section === 'github'" title="GitHub & Pages" :description="github?.repo ? null : 'Framework creates a repository on your GitHub account and sets the project up there with its current setup.'">
                <ProjectGitHub :project="project" :setup="githubSetup" :errors="false" />
                <template v-if="github?.repo">
                    <div v-if="github.status === 'done'" class="flex flex-col gap-3 border-t border-slate-200 pt-5 dark:border-slate-700">
                        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">GitHub Pages</h3>
                        <div v-if="github.pages_url" class="flex flex-wrap items-center gap-2 text-sm">
                            <UiBadge variant="green">Publishing</UiBadge>
                            <a :href="github.pages_url" class="font-mono text-xs text-slate-700 hover:underline dark:text-slate-300">{{ github.pages_url }}</a>
                            <span class="text-xs text-slate-500 dark:text-slate-400">Public. Deploys the published/ folder on each push.</span>
                            <UiButton variant="ghost" size="sm" :disabled="pagesBusy" @click="disablePages">Turn off</UiButton>
                        </div>
                        <div v-else class="flex flex-wrap items-center gap-3 text-sm">
                            <UiButton variant="outline" size="sm" :disabled="pagesBusy" @click="enablePages">
                                {{ pagesBusy ? 'Setting up…' : 'Publish with GitHub Pages' }}
                            </UiButton>
                            <span class="text-xs text-slate-500 dark:text-slate-400">Free. Notebooks you render into published/ go on a public site.</span>
                        </div>
                    </div>
                </template>
            </UiPanel>

            <UiSection v-if="section === 'published'" title="Published artifacts" description="Figures, tables, models, data, and documents published from this project or linked to it. Each has a reader page and a version history.">
                <template #actions>
                    <UiButton v-if="canPublish" :variant="publishing ? 'outline' : 'primary'" @click="publishing = !publishing">{{ publishing ? 'Close' : 'Publish an artifact' }}</UiButton>
                </template>
                <ArtifactUpload v-if="canPublish && publishing" :project-id="project.id" @published="publishing = false" />
                <UiTable v-if="project.documents.length" label="Published artifacts">
                    <thead>
                        <tr><th>Name</th><th class="max-sm:hidden">Kind</th><th class="max-xl:hidden">Sharing</th><th class="max-sm:hidden">Updated</th><th class="actions"><span class="sr-only">Actions</span></th></tr>
                    </thead>
                    <tbody>
                        <tr v-for="doc in project.documents" :key="doc.id">
                            <td class="strong max-w-80 truncate">
                                <span class="flex items-center gap-2.5">
                                    <component :is="artifactKind(doc.kind).icon" class="size-4 shrink-0 text-slate-400" aria-hidden="true" />
                                    <Link :href="`/artifacts/${doc.id}`" class="truncate hover:underline hover:underline-offset-4">{{ doc.title }}</Link>
                                    <LinkIcon v-if="doc.linked_from" class="size-3.5 shrink-0 text-slate-400" :aria-label="`Linked from ${doc.linked_from}`"><title>Linked from {{ doc.linked_from }}</title></LinkIcon>
                                </span>
                            </td>
                            <td class="max-sm:hidden">{{ artifactKind(doc.kind).label }}</td>
                            <td class="max-xl:hidden"><span class="inline-flex items-center gap-1.5">{{ doc.visibility === 'private' ? 'Private' : doc.visibility === 'public' ? 'Public' : 'Anyone with the link' }}<LockClosedIcon v-if="doc.password_protected" class="size-3.5" aria-label="Password protected" /></span></td>

                            <td class="max-sm:hidden">{{ date(doc.updated_at) }}</td>
                            <td class="actions">
                                <div class="flex justify-end gap-1">
                                    <UiButton :href="doc.url" external variant="ghost" size="sm" square :aria-label="`View ${doc.title}`" title="View"><ArrowTopRightOnSquareIcon aria-hidden="true" /></UiButton>
                                    <UiButton :href="`/artifacts/${doc.id}`" variant="outline" size="sm">Manage</UiButton>
                                    <RowMenu :items="artifactMenu(doc)" :label="`Actions for ${doc.title}`" />
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </UiTable>
                <EmptyState v-else :icon="GlobeAltIcon" title="No published artifacts" compact>
                    Publish a figure, table, model, data file, or rendered report to give it a reader page and version history.
                </EmptyState>
                <p v-if="!canPublish" class="text-sm text-slate-500 dark:text-slate-400">Hosting artifacts on Framework Cloud is part of a paid plan. You can also publish to your own bucket or GitHub Pages.</p>
            </UiSection>

            <UiPanel v-if="section === 'computers'" flush title="Computers & tokens" description="A project token lets a computer set this project up and pull its settings.">
                <template #header>
                    <UiButton variant="outline" size="sm" @click="issueToken">Get token</UiButton>
                </template>
                <div v-if="!pullToken" class="text-sm">
                    <CodeChip v-if="project.language === 'r'">framework::setup("&lt;project-token&gt;"{{ serverArg }})</CodeChip>
                    <CodeChip v-else-if="project.language === 'python'">framework.new(key="&lt;project-token&gt;"{{ pythonServerArg }})</CodeChip>
                    <span v-else class="flex flex-wrap items-center gap-2 text-slate-600 dark:text-slate-400">
                        <CodeChip>fw login{{ serverFlag }}</CodeChip>
                        <span>then</span>
                        <CodeChip>fw new --from {{ project.slug }}{{ serverFlag }}</CodeChip>
                    </span>
                </div>
                <p v-if="downloads && !pullToken" class="text-sm text-slate-600 dark:text-slate-400">
                    Downloaded the project as a zip? Get a token, open the folder's <span class="font-mono text-xs">.env</span> file,
                    remove the <span class="font-mono text-xs">#</span> in front of <span class="font-mono text-xs">FW_PROJECT_TOKEN=</span>, and paste the token after the
                    <span class="font-mono text-xs">=</span>. If you use fw, run <CodeChip>fw project link --from {{ project.slug }}{{ serverFlag }}</CodeChip> in the folder instead.
                </p>
                <ul v-if="project.tokens.length" class="flex flex-col divide-y divide-slate-200 rounded-md bg-white text-xs dark:divide-slate-700 dark:bg-slate-900">
                    <li v-for="token in project.tokens" :key="token.id" class="flex flex-wrap items-center gap-x-3 gap-y-1 px-3 py-2">
                        <span class="font-medium text-slate-700 dark:text-slate-300">{{ token.name }}</span>
                        <span class="flex-1 text-slate-500 dark:text-slate-400">Created {{ date(token.created_at) }} · last used {{ date(token.last_used_at) }}</span>
                        <button type="button" class="text-red-600 hover:underline dark:text-red-400" @click="revokeToken(token.id)">Revoke</button>
                    </li>
                </ul>
            </UiPanel>

            <UiPanel v-if="section === 'setup'" title="Project status" description="Active projects make the main Projects list. Triaged, completed, and inactive ones each have their own tab. Nothing else changes.">
                <div class="flex flex-wrap gap-2">
                    <UiButton v-for="(label, key) in statusNames" :key="key" :variant="project.status === key ? 'primary' : 'ghost'" size="sm" :aria-pressed="project.status === key" @click="setStatus(key)">{{ label }}</UiButton>
                </div>
            </UiPanel>

            <UiPanel v-if="section === 'setup'" title="Public download" description="A link anyone can use to download the project as a zip, without an account. With a GitHub repository, the zip holds the repository as it is now.">
                <div class="flex flex-col gap-3">
                    <p v-if="project.public_download_url" class="break-all text-sm"><UiLink :href="project.public_download_url">{{ project.public_download_url }}</UiLink></p>
                    <p v-if="$page.props.errors.public_download" class="text-sm text-red-600 dark:text-red-400">{{ $page.props.errors.public_download }}</p>
                    <div><UiButton variant="ghost" @click="setPublicDownload(!project.public_download_url)">{{ project.public_download_url ? 'Turn off link' : 'Create link' }}</UiButton></div>
                </div>
            </UiPanel>

            <ShareQr v-if="section === 'setup'" :share="project.share" unavailable="Create the public download link above to get a QR code for it." />

            <UiPanel v-if="section === 'setup'" title="Project deletion" description="Its tokens stop working, and its artifacts, collections, and reports go offline. Copies on computers and on GitHub stay.">
                <div><UiButton variant="danger" @click="destroy">Delete project</UiButton></div>
            </UiPanel>
        </div>
        <ShareDialog :target="shareTarget" @close="shareTarget = null" />
    </ProjectLayout>
</template>
