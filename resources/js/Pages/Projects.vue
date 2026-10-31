<script setup>
import { computed, ref, watch, onBeforeUnmount } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import UiButton from '@/Components/UiButton.vue';
import UiCallout from '@/Components/UiCallout.vue';
import UiCard from '@/Components/UiCard.vue';
import UiBadge from '@/Components/UiBadge.vue';
import UiPanel from '@/Components/UiPanel.vue';
import CodeChip from '@/Components/CodeChip.vue';
import EmptyState from '@/Components/EmptyState.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { ChevronRightIcon, FolderIcon, PlusIcon } from '@heroicons/vue/24/outline';
import { useServerArg, useServerFlag, useSetupCommand } from '@/cloud.js';
import { languageNames } from '@/library.js';

const props = defineProps({ projects: Array });

const page = usePage();
const serverArg = useServerArg();
const serverFlag = useServerFlag();
const setupCommand = useSetupCommand();
const pullToken = computed(() =>
    page.props.flash.status === 'project-token' ? page.props.flash.token : null,
);

const githubError = computed(() => page.props.errors.github);
const refresh = (id) => router.post(`/projects/${id}/scaffold/refresh`, {}, { preserveScroll: true });
const retry = (id) => router.post(`/projects/${id}/scaffold/retry`, {}, { preserveScroll: true });
const statusVariant = { pending: 'amber', running: 'amber', done: 'green', failed: 'red' };
const statusLabel = { pending: 'Creating repository', running: 'Scaffolding on GitHub', done: 'Ready to clone', failed: 'Scaffold failed' };

// Poll while a scaffold is in progress; the webhook updates the row
const inProgress = computed(() => props.projects.some((p) => ['pending', 'running'].includes(p.github?.status)));
let timer = null;
watch(inProgress, (value) => {
    clearInterval(timer);
    if (value) timer = setInterval(() => router.reload({ only: ['projects'] }), 10000);
}, { immediate: true });
onBeforeUnmount(() => clearInterval(timer));

const issueToken = (id) => router.post(`/projects/${id}/token`);
const destroy = (id) => {
    if (confirm('Delete this project definition? Existing local copies are unaffected, but its tokens stop working.')) {
        router.delete(`/projects/${id}`);
    }
};

// Which project's token list is open
const tokensOpen = ref(null);
const revokeToken = (projectId, tokenId) => {
    if (confirm('Revoke this token? Computers that use it can no longer pull settings or publish.')) {
        router.delete(`/projects/${projectId}/tokens/${tokenId}`, { preserveScroll: true });
    }
};
const date = (value) => (value ? new Date(value).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' }) : 'never');

const headline = (key) => key.split('_').map((w) => w[0].toUpperCase() + w.slice(1)).join(' ');
</script>

<template>
    <Head title="Projects" />

    <AppLayout>
        <div class="flex flex-col gap-8">
            <PageHeader title="Projects" description="Set a project up on GitHub, or on any computer with a project token.">
                <template #actions>
                    <Link href="/projects/create"><UiButton><PlusIcon class="size-4" />New project</UiButton></Link>
                </template>
            </PageHeader>

            <UiCallout v-if="githubError" variant="danger" heading="GitHub">{{ githubError }}</UiCallout>

            <UiCallout v-if="pullToken" variant="success" heading="Project token — copy it now, it won't be shown again">
                <code class="break-all font-mono text-sm">{{ pullToken }}</code>
                <code class="break-all font-mono text-xs">{{ setupCommand(page.props.flash.token_language, pullToken) }}</code>
                <p v-if="page.props.flash.token_language === 'stata'" class="text-sm">
                    Paste the token when fw asks for it. If you have run <code class="font-mono text-xs">fw login</code>,
                    <code class="font-mono text-xs">fw new --from &lt;project-name&gt;{{ serverFlag }}</code> needs no token.
                </p>
            </UiCallout>

            <div v-if="projects.length" class="flex flex-col gap-3">
                <UiCard v-for="project in projects" :key="project.id" hover>
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div class="flex min-w-0 flex-1 items-start gap-4">
                            <div class="hidden size-10 shrink-0 items-center justify-center rounded-lg sm:flex bg-sky-50 text-sky-600 dark:bg-sky-400/10 dark:text-sky-400">
                                <FolderIcon class="size-5" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="text-base font-semibold text-slate-900 dark:text-white">{{ project.name }}</h2>
                                    <UiBadge>{{ headline(project.project_type) }}</UiBadge>
                                    <UiBadge v-if="project.language !== 'r'" variant="sky">{{ languageNames[project.language] }}</UiBadge>
                                    <Link
                                        v-if="project.blueprint" :href="`/blueprints/${project.blueprint.id}?version=${project.blueprint.version}`"
                                        class="font-mono text-xs text-slate-500 hover:text-sky-600 hover:underline dark:text-slate-400 dark:hover:text-sky-400"
                                    >{{ project.blueprint.handle }} v{{ project.blueprint.version }}</Link>
                                </div>
                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                    <span class="font-mono text-xs">{{ project.slug }}</span><span v-if="project.description"> · {{ project.description }}</span>
                                </p>
                            </div>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <UiButton v-if="project.github?.status === 'running'" variant="ghost" size="sm" @click="refresh(project.id)">Check status</UiButton>
                            <UiButton v-if="project.github?.status === 'failed' && project.github.url" variant="outline" size="sm" @click="retry(project.id)">Retry</UiButton>
                            <UiButton variant="outline" size="sm" @click="issueToken(project.id)">Get token</UiButton>
                            <UiButton variant="danger" size="sm" @click="destroy(project.id)">Delete</UiButton>
                        </div>
                    </div>

                    <div
                        v-if="project.github || project.tokens.length"
                        class="mt-4 flex flex-col gap-2 border-t border-slate-100 pt-4 text-sm sm:pl-14 dark:border-slate-800"
                    >
                        <div v-if="project.github" class="flex flex-wrap items-center gap-2">
                            <UiBadge :variant="statusVariant[project.github.status]">{{ statusLabel[project.github.status] }}</UiBadge>
                            <a v-if="project.github.url" :href="project.github.url" class="font-mono text-xs text-slate-700 hover:underline dark:text-slate-300">{{ project.github.repo }}</a>
                            <span v-if="project.github.private" class="text-xs text-slate-500">private</span>
                            <a v-if="project.github.run_url" :href="project.github.run_url" class="text-xs text-slate-500 hover:underline">Run log</a>
                        </div>
                        <p v-if="project.github?.error" class="text-sm text-red-600 dark:text-red-400">{{ project.github.error }}</p>
                        <div v-if="project.github?.status === 'done'"><CodeChip>git clone {{ project.github.url }}.git</CodeChip></div>
                        <div v-if="project.tokens.length">
                            <button
                                type="button"
                                class="inline-flex items-center gap-1 text-xs text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200"
                                :aria-expanded="tokensOpen === project.id"
                                @click="tokensOpen = tokensOpen === project.id ? null : project.id"
                            >
                                <ChevronRightIcon class="size-3 transition-transform" :class="tokensOpen === project.id ? 'rotate-90' : ''" />
                                {{ project.tokens.length }} {{ project.tokens.length === 1 ? 'token' : 'tokens' }}
                            </button>
                            <ul v-if="tokensOpen === project.id" class="mt-2 flex flex-col divide-y divide-slate-200 rounded-md bg-slate-50 text-xs dark:divide-slate-800 dark:bg-slate-800/50">
                                <li v-for="token in project.tokens" :key="token.id" class="flex flex-wrap items-center gap-x-3 gap-y-1 px-3 py-2">
                                    <span class="font-medium text-slate-700 dark:text-slate-300">{{ token.name }}</span>
                                    <span class="flex-1 text-slate-500">Created {{ date(token.created_at) }} · last used {{ date(token.last_used_at) }}</span>
                                    <button type="button" class="text-red-600 hover:underline dark:text-red-400" @click="revokeToken(project.id, token.id)">Revoke</button>
                                </li>
                            </ul>
                        </div>
                    </div>
                </UiCard>
            </div>

            <EmptyState v-else :icon="FolderIcon" title="No projects yet." description="Pick a blueprint and set it up on GitHub or your computer.">
                <template #actions>
                    <Link href="/projects/create"><UiButton><PlusIcon class="size-4" />Create your first project</UiButton></Link>
                </template>
            </EmptyState>

            <UiPanel title="Set up a project on your computer" description="Click Get token on a project, then run the command for its language.">
                <dl class="flex flex-col divide-y divide-slate-200 text-sm dark:divide-slate-700/70">
                    <div class="flex flex-col gap-2 py-3 first:pt-0 last:pb-0 sm:flex-row sm:items-center sm:gap-4">
                        <dt class="w-16 shrink-0 font-medium text-slate-900 dark:text-white">R</dt>
                        <dd class="min-w-0"><CodeChip>framework::setup("&lt;project-token&gt;"{{ serverArg }})</CodeChip></dd>
                    </div>
                    <div class="flex flex-col gap-2 py-3 first:pt-0 last:pb-0 sm:flex-row sm:items-center sm:gap-4">
                        <dt class="w-16 shrink-0 font-medium text-slate-900 dark:text-white">Python</dt>
                        <dd class="min-w-0"><CodeChip>framework.new(key="&lt;project-token&gt;")</CodeChip></dd>
                    </div>
                    <div class="flex flex-col gap-2 py-3 first:pt-0 last:pb-0 sm:flex-row sm:items-center sm:gap-4">
                        <dt class="w-16 shrink-0 font-medium text-slate-900 dark:text-white">Stata</dt>
                        <dd class="flex min-w-0 flex-wrap items-center gap-2 text-slate-600 dark:text-slate-400">
                            <CodeChip>fw login{{ serverFlag }}</CodeChip>
                            <span>then</span>
                            <CodeChip>fw new --from &lt;project-name&gt;{{ serverFlag }}</CodeChip>
                            <span>in a terminal</span>
                        </dd>
                    </div>
                </dl>
            </UiPanel>
        </div>
    </AppLayout>
</template>
