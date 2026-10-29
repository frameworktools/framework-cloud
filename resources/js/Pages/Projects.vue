<script setup>
import { computed, watch, onBeforeUnmount } from 'vue';
import { useForm, usePage, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import UiButton from '@/Components/UiButton.vue';
import UiInput from '@/Components/UiInput.vue';
import UiSelect from '@/Components/UiSelect.vue';
import UiCallout from '@/Components/UiCallout.vue';
import UiSwitch from '@/Components/UiSwitch.vue';

const props = defineProps({ projects: Array, blueprints: Array, selected: Number, github: Object });

const page = usePage();
const pullToken = computed(() =>
    page.props.flash.status === 'project-token' ? page.props.flash.token : null,
);

const options = computed(() =>
    props.blueprints.map((b) => ({ value: b.id, label: b.handle.startsWith('framework/') ? b.name : `${b.name} (${b.handle})` })),
);
const bare = props.blueprints.find((b) => b.handle === 'framework/bare');

const form = useForm({
    name: '',
    blueprint_id: props.selected ?? bare?.id ?? props.blueprints[0]?.id,
    description: '',
    github: { enabled: false, owner: props.github.owners?.[0]?.login ?? null, name: '', private: true, nameEdited: false },
});
const create = () => form
    .transform((data) => ({ ...data, github: data.github.enabled ? { ...data.github, name: repoName.value } : { enabled: false } }))
    .post('/projects', { onSuccess: () => form.reset('name', 'description', 'github') });

const ownerOptions = computed(() => (props.github.owners ?? []).map((o) => ({ value: o.login, label: o.login })));
const sensitive = computed(() => props.blueprints.find((b) => b.id === form.blueprint_id)?.sensitive ?? false);
const repoName = computed(() => (form.github.nameEdited ? form.github.name : form.name.trim().toLowerCase().replace(/[^a-z0-9._-]+/g, '-').replace(/^-+|-+$/g, '')));
const editRepoName = (value) => { form.github.name = value; form.github.nameEdited = true; };
watch(sensitive, (value) => { if (value) form.github.private = true; });

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

const headline = (key) => key.split('_').map((w) => w[0].toUpperCase() + w.slice(1)).join(' ');
</script>

<template>
    <Head title="Projects" />

    <AppLayout>
        <div class="flex flex-col gap-6">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Projects</h1>
                <p class="mt-1 text-neutral-600 dark:text-neutral-400">
                    Define a project here, generate its token, and set it up on any machine with
                    <code class="font-mono text-sm">framework::setup("&lt;project-token&gt;")</code>.
                </p>
            </div>

            <form class="flex flex-col gap-4 rounded-lg border border-neutral-200 p-4 dark:border-neutral-700" @submit.prevent="create">
                <div class="flex flex-wrap items-end gap-3">
                    <div class="w-56"><UiInput v-model="form.name" label="Name" placeholder="e.g. NHANES Study" :error="form.errors.name" /></div>
                    <div class="w-64"><UiSelect v-model="form.blueprint_id" label="Blueprint" :options="options" :error="form.errors.blueprint_id" /></div>
                    <div class="w-64"><UiInput v-model="form.description" label="Description (optional)" :error="form.errors.description" /></div>
                </div>

                <div v-if="github.enabled" class="flex max-w-2xl flex-col gap-3">
                    <UiSwitch
                        v-model="form.github.enabled"
                        label="Create it on GitHub"
                        description="Framework makes the repository and scaffolds it there with a one-time GitHub Action. Then you clone it."
                    />
                    <p v-if="form.errors['github.enabled']" class="text-sm text-red-600 dark:text-red-400">{{ form.errors['github.enabled'] }}</p>

                    <template v-if="form.github.enabled">
                        <p v-if="!github.login" class="text-sm text-neutral-600 dark:text-neutral-400">
                            <Link href="/settings/github" class="text-orange-600 hover:underline dark:text-orange-400">Connect GitHub</Link> first.
                        </p>
                        <p v-else-if="github.owners === null" class="text-sm text-neutral-600 dark:text-neutral-400">
                            GitHub could not be reached. Reload to try again.
                        </p>
                        <p v-else-if="!ownerOptions.length" class="text-sm text-neutral-600 dark:text-neutral-400">
                            <a :href="github.install_url" class="text-orange-600 hover:underline dark:text-orange-400">Install the Framework app</a>
                            on your account or an organization, then reload this page.
                        </p>
                        <div v-else class="flex flex-wrap items-end gap-3">
                            <div class="w-48"><UiSelect v-model="form.github.owner" label="Owner" :options="ownerOptions" :error="form.errors['github.owner']" /></div>
                            <span class="pb-2 text-neutral-400">/</span>
                            <div class="w-56">
                                <UiInput :model-value="repoName" label="Repository" placeholder="nhanes-study" :error="form.errors['github.name']" @update:model-value="editRepoName" />
                            </div>
                            <div class="w-56 pb-1">
                                <UiSwitch v-model="form.github.private" label="Private" :description="sensitive ? 'Required: this blueprint handles restricted data.' : null" />
                            </div>
                        </div>
                        <p v-if="form.errors['github.private']" class="text-sm text-red-600 dark:text-red-400">{{ form.errors['github.private'] }}</p>
                    </template>
                </div>

                <div><UiButton type="submit" :disabled="form.processing || (form.github.enabled && sensitive && !form.github.private)">Create project</UiButton></div>
            </form>

            <UiCallout v-if="githubError" variant="danger" heading="GitHub">{{ githubError }}</UiCallout>

            <UiCallout v-if="pullToken" variant="success" heading="Project token — copy it now, it won't be shown again">
                <code class="break-all font-mono text-sm">{{ pullToken }}</code>
                <code class="break-all font-mono text-xs">framework::setup("{{ pullToken }}")</code>
            </UiCallout>

            <div class="flex flex-col divide-y divide-neutral-200 dark:divide-neutral-700">
                <div v-for="project in projects" :key="project.id" class="flex items-center justify-between gap-4 py-3">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="font-medium">{{ project.name }}</span>
                            <span class="rounded-full bg-neutral-100 px-2 py-0.5 text-xs text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300">
                                {{ headline(project.project_type) }}
                            </span>
                            <Link
                                v-if="project.blueprint" :href="`/blueprints/${project.blueprint.id}?version=${project.blueprint.version}`"
                                class="font-mono text-xs text-neutral-500 hover:underline"
                            >{{ project.blueprint.handle }} v{{ project.blueprint.version }}</Link>
                        </div>
                        <p class="text-sm text-neutral-500 dark:text-neutral-400">
                            {{ project.slug }}<span v-if="project.description"> · {{ project.description }}</span>
                        </p>
                        <div v-if="project.github" class="mt-1 flex flex-wrap items-center gap-2 text-sm">
                            <a v-if="project.github.url" :href="project.github.url" class="font-mono text-xs hover:underline">{{ project.github.repo }}</a>
                            <span v-if="project.github.private" class="text-xs text-neutral-500">private</span>
                            <span
                                class="rounded-full px-2 py-0.5 text-xs"
                                :class="{
                                    'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200': ['pending', 'running'].includes(project.github.status),
                                    'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200': project.github.status === 'done',
                                    'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200': project.github.status === 'failed',
                                }"
                            >{{ statusLabel[project.github.status] }}</span>
                            <a v-if="project.github.run_url" :href="project.github.run_url" class="text-xs text-neutral-500 hover:underline">Run log</a>
                        </div>
                        <p v-if="project.github?.error" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ project.github.error }}</p>
                        <code v-if="project.github?.status === 'done'" class="mt-1 block font-mono text-xs text-neutral-600 dark:text-neutral-300">
                            git clone {{ project.github.url }}.git
                        </code>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <UiButton v-if="project.github?.status === 'running'" variant="ghost" size="sm" @click="refresh(project.id)">Check status</UiButton>
                        <UiButton v-if="project.github?.status === 'failed' && project.github.url" variant="outline" size="sm" @click="retry(project.id)">Retry</UiButton>
                        <UiButton variant="outline" size="sm" @click="issueToken(project.id)">Get token</UiButton>
                        <UiButton variant="danger" size="sm" @click="destroy(project.id)">Delete</UiButton>
                    </div>
                </div>
                <p v-if="!projects.length" class="py-3 text-sm text-neutral-500">No projects yet.</p>
            </div>
        </div>
    </AppLayout>
</template>
