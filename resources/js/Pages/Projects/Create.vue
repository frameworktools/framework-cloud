<script setup>
import { computed, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { ComputerDesktopIcon, PencilSquareIcon, Squares2X2Icon } from '@heroicons/vue/24/outline';
import SectionLayout from '@/Layouts/SectionLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import UiPanel from '@/Components/UiPanel.vue';
import UiButton from '@/Components/UiButton.vue';
import UiInput from '@/Components/UiInput.vue';
import UiSelect from '@/Components/UiSelect.vue';
import UiSwitch from '@/Components/UiSwitch.vue';
import UiTextarea from '@/Components/UiTextarea.vue';
import UiCallout from '@/Components/UiCallout.vue';
import BlueprintContents from '@/Components/BlueprintContents.vue';
import { useServerArg, useServerFlag } from '@/cloud.js';
import { group, groups, languageOptions, stataTypes } from '@/library.js';

const props = defineProps({ blueprints: Array, selected: Number, github: Object });

const page = usePage();
const bare = props.blueprints.find((b) => b.handle === 'framework/bare');
const githubReady = computed(() => props.github.enabled && props.github.login && props.github.owners?.length);

const form = useForm({
    name: '',
    blueprint_id: props.selected ?? bare?.id ?? props.blueprints[0]?.id,
    description: '',
    language: 'r',
    destination: 'local',
    github: { owner: props.github.owners?.[0]?.login ?? null, name: '', private: true, nameEdited: false },
});

const sections = computed(() =>
    groups
        .map((g) => ({ ...g, rows: props.blueprints.filter((b) => group(b) === g.key) }))
        .filter((g) => g.rows.length),
);
const blueprint = computed(() => props.blueprints.find((b) => b.id === form.blueprint_id));
const sensitive = computed(() => blueprint.value?.sensitive ?? false);

const slugify = (value) => value.trim().toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
const slug = computed(() => slugify(form.name));
const repoName = computed(() => (form.github.nameEdited ? form.github.name : slug.value));
const editRepoName = (value) => { form.github.name = value; form.github.nameEdited = true; };
const ownerOptions = computed(() => (props.github.owners ?? []).map((o) => ({ value: o.login, label: o.login })));
watch(sensitive, (value) => { if (value) form.github.private = true; });
// A blueprint's language is the default for projects made from it
watch(blueprint, (value) => { form.language = value?.language ?? 'r'; }, { immediate: true });
const stata = computed(() => form.language === 'stata');
const stataAllowed = computed(() => stataTypes.includes(blueprint.value?.type ?? 'bare'));
const languages = computed(() => languageOptions.filter((o) => o.value !== 'stata' || stataAllowed.value));

const onGitHub = computed(() => form.destination === 'github');
const create = () => form
    .transform((data) => ({
        name: data.name,
        blueprint_id: data.blueprint_id,
        language: data.language,
        description: data.description,
        github: onGitHub.value
            ? { enabled: true, owner: data.github.owner, name: repoName.value, private: data.github.private }
            : { enabled: false },
    }))
    .post('/projects');

const githubError = computed(() => page.props.errors.github ?? form.errors['github.enabled']);

const serverArg = useServerArg();
const serverFlag = useServerFlag();

const items = [
    { label: 'Blueprint', anchor: 'blueprint', icon: Squares2X2Icon },
    { label: 'Name', anchor: 'name', icon: PencilSquareIcon },
    { label: 'Where to set it up', anchor: 'where', icon: ComputerDesktopIcon },
];
</script>

<template>
    <Head title="New project" />

    <SectionLayout heading="New project" :items="items">
        <Link href="/projects" class="text-sm text-slate-500 hover:underline">Projects</Link>
        <PageHeader title="New project" description="Pick what kind of project it is, name it, and choose where it gets set up." />

        <form id="create-form" class="mt-8 flex flex-col gap-8" @submit.prevent="create">
            <UiPanel id="blueprint" title="Blueprint" class="scroll-mt-6">
                <template #header>
                    <Link href="/blueprints/create" class="text-sm text-sky-600 hover:underline dark:text-sky-400">Build your own</Link>
                </template>
                <p v-if="form.errors.blueprint_id" class="text-sm text-red-600 dark:text-red-400">{{ form.errors.blueprint_id }}</p>

                <div v-for="section in sections" :key="section.key" class="flex flex-col gap-2">
                    <h3 v-if="sections.length > 1" class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ section.label }}</h3>
                    <div class="flex flex-col divide-y divide-slate-200 overflow-hidden rounded-lg border border-slate-200 bg-white dark:divide-slate-700 dark:border-slate-700 dark:bg-slate-900">
                        <div
                            v-for="b in section.rows" :key="b.id"
                            :class="b.id === form.blueprint_id ? 'bg-sky-50/60 dark:bg-sky-950/30' : ''"
                        >
                            <button
                                type="button" class="flex w-full items-start gap-3 p-4 text-left"
                                :aria-expanded="b.id === form.blueprint_id"
                                @click="form.blueprint_id = b.id"
                            >
                                <span
                                    class="mt-1 flex size-4 shrink-0 items-center justify-center rounded-full border"
                                    :class="b.id === form.blueprint_id ? 'border-sky-500' : 'border-slate-300 dark:border-slate-600'"
                                ><span v-if="b.id === form.blueprint_id" class="size-2 rounded-full bg-sky-600" /></span>
                                <span class="flex min-w-0 flex-1 flex-col gap-1">
                                    <span class="flex flex-wrap items-center gap-2">
                                        <span class="font-medium">{{ b.name }}</span>
                                        <span
                                            v-if="b.sensitive"
                                            class="rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-800 dark:bg-amber-900/40 dark:text-amber-200"
                                        >Restricted data</span>
                                        <span
                                            v-for="facet in b.facets" :key="facet"
                                            class="rounded bg-sky-50 px-1.5 py-0.5 text-xs text-sky-800 dark:bg-sky-950/50 dark:text-sky-300"
                                        >+ {{ facet }}</span>
                                    </span>
                                    <span v-if="b.description" class="text-sm text-slate-600 dark:text-slate-400">{{ b.description }}</span>
                                </span>
                                <svg
                                    class="mt-1 size-4 shrink-0 text-slate-400 transition-transform" :class="b.id === form.blueprint_id ? 'rotate-180' : ''"
                                    viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"
                                ><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.17l3.71-3.94a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" /></svg>
                            </button>
                            <div v-if="b.id === form.blueprint_id" class="px-4 pb-4 pl-11">
                                <BlueprintContents :version="b.fragments" />
                                <Link :href="`/blueprints/${b.id}`" class="mt-3 inline-block font-mono text-xs text-slate-500 hover:underline">{{ b.handle }} v{{ b.version }}</Link>
                            </div>
                        </div>
                    </div>
                </div>
            </UiPanel>

            <UiPanel id="name" title="Name" class="scroll-mt-6">
                <div>
                    <UiInput v-model="form.name" label="Project name" placeholder="e.g. NHANES Study" :error="form.errors.name" />
                    <p v-if="slug" class="mt-1.5 text-xs text-slate-500">Folder name: <span class="font-mono">{{ slug }}</span></p>
                </div>
                <UiTextarea v-model="form.description" label="Description (optional)" :rows="3" :error="form.errors.description" />
                <div>
                    <UiSelect v-model="form.language" label="Language" :options="languages" :error="form.errors.language" />
                    <p v-if="!stataAllowed" class="mt-1.5 text-xs text-slate-500">
                        Stata projects can use the standard, privacy-sensitive, or bare structure. This blueprint uses another one.
                    </p>
                    <p v-else-if="stata" class="mt-1.5 text-xs text-slate-500">
                        Framework creates the project's folders, files, and Git setup for Stata. It has no Stata commands for the analysis itself.
                    </p>
                </div>
            </UiPanel>

            <UiPanel id="where" title="Where to set it up" class="scroll-mt-6">
                <div class="grid gap-3 sm:grid-cols-2">
                    <button
                        type="button"
                        class="flex flex-col gap-1.5 rounded-lg border p-4 text-left transition-colors"
                        :class="!onGitHub
                            ? 'border-sky-500 bg-sky-50 ring-1 ring-sky-500 dark:bg-sky-950/30'
                            : 'border-slate-200 bg-white hover:border-slate-300 dark:border-slate-700 dark:bg-slate-900 dark:hover:border-slate-600'"
                        :aria-pressed="!onGitHub"
                        @click="form.destination = 'local'"
                    >
                        <span class="font-medium">On your computer</span>
                        <span class="text-sm text-slate-600 dark:text-slate-400">
                            <template v-if="stata">In a terminal, run <code class="font-mono text-xs">fw login</code>, then <code class="font-mono text-xs">fw new</code> with the project's name.</template>
                            <template v-else-if="form.language === 'python'">You get a project token. Run <code class="font-mono text-xs">framework.new(key=...)</code> with it in Python.</template>
                            <template v-else>You get a project token. Run <code class="font-mono text-xs">framework::setup()</code> with it in R.</template>
                        </span>
                    </button>
                    <button
                        v-if="github.enabled"
                        type="button"
                        class="flex flex-col gap-1.5 rounded-lg border p-4 text-left transition-colors"
                        :class="onGitHub
                            ? 'border-sky-500 bg-sky-50 ring-1 ring-sky-500 dark:bg-sky-950/30'
                            : 'border-slate-200 bg-white hover:border-slate-300 dark:border-slate-700 dark:bg-slate-900 dark:hover:border-slate-600'"
                        :aria-pressed="onGitHub"
                        @click="form.destination = 'github'"
                    >
                        <span class="font-medium">On GitHub</span>
                        <span class="text-sm text-slate-600 dark:text-slate-400">
                            Framework Cloud creates the repository and sets it up there. Then you clone it.
                        </span>
                    </button>
                </div>

                <div v-if="onGitHub" class="flex flex-col gap-4 rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
                    <p v-if="!github.login" class="text-sm text-slate-600 dark:text-slate-400">
                        <Link href="/settings/github" class="text-sky-600 hover:underline dark:text-sky-400">Connect GitHub</Link> first.
                    </p>
                    <p v-else-if="github.owners === null" class="text-sm text-slate-600 dark:text-slate-400">
                        GitHub could not be reached. Reload to try again.
                    </p>
                    <p v-else-if="!ownerOptions.length" class="text-sm text-slate-600 dark:text-slate-400">
                        <a :href="github.install_url" class="text-sky-600 hover:underline dark:text-sky-400">Install the Framework app</a>
                        on your account or an organization, then reload this page.
                    </p>
                    <template v-else>
                        <div class="flex flex-wrap items-end gap-2">
                            <div class="w-44"><UiSelect v-model="form.github.owner" label="Owner" :options="ownerOptions" :error="form.errors['github.owner']" /></div>
                            <span class="pb-2 text-slate-400">/</span>
                            <div class="min-w-48 flex-1">
                                <UiInput :model-value="repoName" label="Repository" placeholder="nhanes-study" :error="form.errors['github.name']" @update:model-value="editRepoName" />
                            </div>
                        </div>
                        <UiSwitch
                            v-model="form.github.private" label="Private repository"
                            :description="sensitive ? 'Required: this blueprint handles restricted data.' : 'Only you and people you add can see it.'"
                        />
                        <p v-if="form.errors['github.private']" class="text-sm text-red-600 dark:text-red-400">{{ form.errors['github.private'] }}</p>
                    </template>
                </div>
            </UiPanel>

            <UiCallout v-if="githubError" variant="danger" heading="GitHub">{{ githubError }}</UiCallout>
        </form>

        <!-- What you'll get -->
        <template #aside>
            <div v-if="blueprint" class="flex flex-col gap-3 rounded-lg bg-slate-50 p-4 text-sm dark:bg-slate-800/50">
                <div>
                    <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Summary</div>
                    <div class="mt-1 break-words font-semibold text-slate-900 dark:text-white">{{ form.name || 'Your project' }}</div>
                    <Link :href="`/blueprints/${blueprint.id}`" class="break-all font-mono text-xs text-slate-500 hover:underline">
                        {{ blueprint.handle }} v{{ blueprint.version }}
                    </Link>
                </div>

                <div class="flex flex-col gap-1.5 border-t border-slate-200 pt-3 text-xs dark:border-slate-700">
                    <div class="font-medium text-slate-900 dark:text-white">Next</div>
                    <ol v-if="onGitHub" class="list-decimal space-y-1 pl-4 text-slate-600 dark:text-slate-400">
                        <li>Framework Cloud creates <span class="break-all font-mono">{{ form.github.owner ?? 'owner' }}/{{ repoName || 'repository' }}</span>.</li>
                        <li>A one-time GitHub Action builds the project files.</li>
                        <li>You clone it and start working.</li>
                    </ol>
                    <ol v-else-if="stata" class="list-decimal space-y-1 pl-4 text-slate-600 dark:text-slate-400">
                        <li>In a terminal, run <code class="break-words font-mono">fw login{{ serverFlag }}</code> once.</li>
                        <li>Run <code class="break-words font-mono">fw new --from {{ slug || '&lt;project-name&gt;' }}{{ serverFlag }}</code>.</li>
                    </ol>
                    <ol v-else class="list-decimal space-y-1 pl-4 text-slate-600 dark:text-slate-400">
                        <li>You get a project token. Copy it.</li>
                        <li v-if="form.language === 'python'">In Python, run <code class="break-words font-mono">framework.new(key="&lt;token&gt;")</code>.</li>
                        <li v-else>In R, run <code class="break-words font-mono">framework::setup("&lt;token&gt;"{{ serverArg }})</code>.</li>
                    </ol>
                </div>
            </div>
        </template>

        <template #actions>
            <UiButton
                type="submit" form="create-form" class="w-full"
                :disabled="form.processing || (onGitHub && (!githubReady || (sensitive && !form.github.private)))"
            >{{ onGitHub ? 'Create on GitHub' : 'Create project' }}</UiButton>
            <Link href="/projects" class="block"><UiButton variant="ghost" class="w-full">Cancel</UiButton></Link>
        </template>
    </SectionLayout>
</template>
