<script setup>
import { computed, ref, watch, onBeforeUnmount } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import UiButton from '@/Components/UiButton.vue';
import UiCallout from '@/Components/UiCallout.vue';
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
        <div class="flex flex-col gap-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">Projects</h1>
                    <p class="mt-1 max-w-2xl text-slate-600 dark:text-slate-400">
                        Set a project up on GitHub, or on any machine with
                        <code class="font-mono text-sm">framework::setup("&lt;project-token&gt;"{{ serverArg }})</code> in R.
                        For Stata projects, run <code class="font-mono text-sm">fw login{{ serverFlag }}</code> and then
                        <code class="font-mono text-sm">fw new --from &lt;project-name&gt;{{ serverFlag }}</code> in a terminal.
                    </p>
                </div>
                <Link href="/projects/create"><UiButton>New project</UiButton></Link>
            </div>

            <UiCallout v-if="githubError" variant="danger" heading="GitHub">{{ githubError }}</UiCallout>

            <UiCallout v-if="pullToken" variant="success" heading="Project token — copy it now, it won't be shown again">
                <code class="break-all font-mono text-sm">{{ pullToken }}</code>
                <code class="break-all font-mono text-xs">{{ setupCommand(page.props.flash.token_language, pullToken) }}</code>
                <p v-if="page.props.flash.token_language === 'stata'" class="text-sm">
                    Paste the token when fw asks for it. If you have run <code class="font-mono text-xs">fw login</code>,
                    <code class="font-mono text-xs">fw new --from &lt;project-name&gt;{{ serverFlag }}</code> needs no token.
                </p>
            </UiCallout>

            <div class="flex flex-col divide-y divide-slate-200 dark:divide-slate-800">
                <div v-for="project in projects" :key="project.id" class="flex items-center justify-between gap-4 py-3">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="font-medium">{{ project.name }}</span>
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                {{ headline(project.project_type) }}
                            </span>
                            <span
                                v-if="project.language !== 'r'"
                                class="rounded-full bg-sky-100 px-2 py-0.5 text-xs text-sky-800 dark:bg-sky-950/50 dark:text-sky-300"
                            >{{ languageNames[project.language] }}</span>
                            <Link
                                v-if="project.blueprint" :href="`/blueprints/${project.blueprint.id}?version=${project.blueprint.version}`"
                                class="font-mono text-xs text-slate-500 hover:underline"
                            >{{ project.blueprint.handle }} v{{ project.blueprint.version }}</Link>
                        </div>
                        <p class="text-sm text-slate-500 dark:text-slate-400">
                            {{ project.slug }}<span v-if="project.description"> · {{ project.description }}</span>
                        </p>
                        <div v-if="project.github" class="mt-1 flex flex-wrap items-center gap-2 text-sm">
                            <a v-if="project.github.url" :href="project.github.url" class="font-mono text-xs hover:underline">{{ project.github.repo }}</a>
                            <span v-if="project.github.private" class="text-xs text-slate-500">private</span>
                            <span
                                class="rounded-full px-2 py-0.5 text-xs"
                                :class="{
                                    'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200': ['pending', 'running'].includes(project.github.status),
                                    'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200': project.github.status === 'done',
                                    'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200': project.github.status === 'failed',
                                }"
                            >{{ statusLabel[project.github.status] }}</span>
                            <a v-if="project.github.run_url" :href="project.github.run_url" class="text-xs text-slate-500 hover:underline">Run log</a>
                        </div>
                        <p v-if="project.github?.error" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ project.github.error }}</p>
                        <code v-if="project.github?.status === 'done'" class="mt-1 block font-mono text-xs text-slate-600 dark:text-slate-300">
                            git clone {{ project.github.url }}.git
                        </code>
                        <button
                            v-if="project.tokens.length"
                            type="button"
                            class="mt-1 text-xs text-slate-500 hover:underline"
                            :aria-expanded="tokensOpen === project.id"
                            @click="tokensOpen = tokensOpen === project.id ? null : project.id"
                        >{{ project.tokens.length }} {{ project.tokens.length === 1 ? 'token' : 'tokens' }}</button>
                        <ul v-if="tokensOpen === project.id" class="mt-2 flex flex-col gap-1.5 text-xs">
                            <li v-for="token in project.tokens" :key="token.id" class="flex flex-wrap items-center gap-x-3 gap-y-1">
                                <span class="font-medium text-slate-700 dark:text-slate-300">{{ token.name }}</span>
                                <span class="text-slate-500">Created {{ date(token.created_at) }} · last used {{ date(token.last_used_at) }}</span>
                                <button type="button" class="text-red-600 hover:underline dark:text-red-400" @click="revokeToken(project.id, token.id)">Revoke</button>
                            </li>
                        </ul>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <UiButton v-if="project.github?.status === 'running'" variant="ghost" size="sm" @click="refresh(project.id)">Check status</UiButton>
                        <UiButton v-if="project.github?.status === 'failed' && project.github.url" variant="outline" size="sm" @click="retry(project.id)">Retry</UiButton>
                        <UiButton variant="outline" size="sm" @click="issueToken(project.id)">Get token</UiButton>
                        <UiButton variant="danger" size="sm" @click="destroy(project.id)">Delete</UiButton>
                    </div>
                </div>
                <div v-if="!projects.length" class="flex flex-col items-start gap-3 rounded-lg border border-dashed border-slate-300 p-8 dark:border-slate-700">
                    <p class="text-slate-600 dark:text-slate-400">No projects yet.</p>
                    <Link href="/projects/create"><UiButton>Create your first project</UiButton></Link>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
