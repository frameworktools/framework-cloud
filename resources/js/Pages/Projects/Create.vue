<script setup>
import { computed, ref, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import SectionLayout from '@/Layouts/SectionLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import UiPanel from '@/Components/UiPanel.vue';
import UiSeparator from '@/Components/UiSeparator.vue';
import UiButton from '@/Components/UiButton.vue';
import UiInput from '@/Components/UiInput.vue';
import UiSelect from '@/Components/UiSelect.vue';
import UiSwitch from '@/Components/UiSwitch.vue';
import UiTextarea from '@/Components/UiTextarea.vue';
import UiCallout from '@/Components/UiCallout.vue';
import ChoicesEditor from '@/Components/ChoicesEditor.vue';
import ProjectTreeView from '@/Components/ProjectTreeView.vue';
import UiModal from '@/Components/UiModal.vue';
import { agentSummary, fromChoices, NAMED, toChoices } from '@/agents.js';
import { group, groups, languageOptions } from '@/library.js';

const props = defineProps({ blueprints: Array, catalog: Object, defaults: Object, selected: Number, start: Object, github: Object, downloads: Boolean, githubUnavailable: Object });

const page = usePage();
// Your default blueprint, or the Standard Project when it isn't in the list
const defaultBlueprint = props.blueprints.find((b) => b.handle === props.defaults.blueprint) ?? props.blueprints.find((b) => b.handle === 'framework/project');
const githubReady = computed(() => props.github.enabled && props.github.login && props.github.owners?.length);

const form = useForm({
    name: '',
    blueprint_id: props.selected ?? defaultBlueprint?.id ?? props.blueprints[0]?.id,
    description: '',
    language: 'r',
    // Empty uses your default workflow for the language
    workflow: props.start?.workflow ?? '',
    // A destination passed in the link, when it's available here; otherwise a download, which needs nothing installed
    destination: ({ download: props.downloads, github: props.github.enabled, local: true })[props.start?.destination]
        ? props.start.destination
        : 'local',
    github: { owner: props.github.owners?.[0]?.login ?? null, name: '', private: true, nameEdited: false },
    // Changing pieces starts from the chosen blueprint's choices
    customized: false,
    structure: null,
    tools: {},
    package_sets: [],
    skill_sets: [],
    skills: [],
    settings: { sensitive: false, git: true },
    // The shared agent choices, an override for this project only
    agents: ['other'],
});

// The languages a blueprint supports, from its own metadata
const blueprintLanguages = (b) => b?.choices?.languages ?? ['r', 'python', 'stata'];
const fitsLanguage = (b) => blueprintLanguages(b).includes(form.language);
// Only blueprints for the chosen language
const sections = computed(() =>
    groups
        .map((g) => ({ ...g, rows: props.blueprints.filter((b) => group(b) === g.key && fitsLanguage(b)) }))
        .filter((g) => g.rows.length),
);
const blueprint = computed(() => props.blueprints.find((b) => b.id === form.blueprint_id));
// Groups you've hidden, remembered in this browser. A hidden group still shows the chosen blueprint.
const hiddenKey = 'fw.newProject.hiddenGroups';
const hidden = ref(JSON.parse(localStorage.getItem(hiddenKey) ?? '[]'));
const toggleGroup = (key) => {
    hidden.value = hidden.value.includes(key) ? hidden.value.filter((k) => k !== key) : [...hidden.value, key];
    localStorage.setItem(hiddenKey, JSON.stringify(hidden.value));
};
const shownRows = (section) => (hidden.value.includes(section.key) ? section.rows.filter((b) => b.id === form.blueprint_id) : section.rows);

// What the form would create, resolved by the server exactly as creating it
// would: the blueprint combined with your defaults, or the pieces chosen here
const choicePayload = (data) => ({
    blueprint_id: data.blueprint_id,
    language: data.language,
    workflow: data.workflow || undefined,
    name: data.name || undefined,
    ...(data.customized ? {
        customized: true,
        structure: data.structure,
        // Only tools for the project's language; a blueprint may carry others, such as uv
        tools: Object.fromEntries(Object.entries(data.tools).filter(([key]) => {
            const tool = props.catalog.tools.find((t) => t.key === key);
            return data.language !== 'stata' && (!tool?.language || tool.language.includes(data.language));
        })),
        package_sets: data.package_sets,
        skill_sets: data.skill_sets,
        skills: data.skills,
        settings: { ...data.settings, ai: agentSettings(data.agents) },
    } : {}),
});
// The agents as the resolver takes them: setup on or off, and each named agent in or out
const agentSettings = (choices) => {
    const { enabled, agents } = fromChoices(choices);
    return { enabled, assistants: Object.fromEntries(NAMED.map((a) => [a, agents.includes(a)])) };
};
const resolved = ref(null);
const resolvedFor = ref(null);
let request = 0;
let timer = null;
const payloadKey = computed(() => JSON.stringify(choicePayload(form.data())));
const resolve = async () => {
    const id = ++request;
    const key = payloadKey.value;
    const response = await fetch('/projects/preview', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': page.props.csrf, 'X-Requested-With': 'XMLHttpRequest' },
        body: key,
    }).catch(() => null);
    const data = await response?.json().catch(() => null);
    if (id !== request) return;
    resolved.value = response?.ok ? data : { problem: data?.problem ?? data?.message ?? "The preview couldn't be loaded." };
    resolvedFor.value = key;
};
watch(payloadKey, () => {
    clearTimeout(timer);
    timer = setTimeout(resolve, 250);
}, { immediate: true });
// A result for other choices than the form's is never shown, so nothing stale contradicts the form
const current = computed(() => (resolvedFor.value === payloadKey.value ? resolved.value : null));
// For a blueprint as it is, the result for that blueprint, even while a new name resolves
const shownFor = (b) => (current.value && b.id === form.blueprint_id ? current.value : null);

// Changing pieces starts from the resolved result, so the switches show what the project would get
const pieces = () => JSON.stringify([form.structure, form.tools, form.package_sets, form.skill_sets, form.skills, form.settings, form.agents]);
const untouched = ref(null);
const startFrom = (r) => {
    if (!r || r.problem) return;
    form.structure = r.structure;
    form.tools = Object.fromEntries(Object.entries(r.tools).map(([key, t]) => [key, t.on]));
    form.package_sets = r.package_sets.map((s) => s.key);
    form.skill_sets = r.skill_sets.map((s) => s.key);
    form.skills = [...r.skills];
    form.settings = { sensitive: r.settings.sensitive, git: r.settings.git };
    form.agents = toChoices(r.agents.enabled, r.agents.named, r.agents.other);
    untouched.value = pieces();
};
const customize = () => {
    startFrom(current.value);
    form.customized = true;
};
// Edits to the pieces, which switching blueprints would discard
const edited = computed(() => form.customized && untouched.value !== null && pieces() !== untouched.value);
const pendingBlueprint = ref(null);
const confirming = computed({ get: () => pendingBlueprint.value !== null, set: (open) => { if (!open) pendingBlueprint.value = null; } });
const useBlueprint = (id) => {
    form.blueprint_id = id;
    form.customized = false;
    untouched.value = null;
    pendingBlueprint.value = null;
};
const chooseBlueprint = (id) => {
    if (id === form.blueprint_id) return;
    if (edited.value) pendingBlueprint.value = id;
    else useBlueprint(id);
};
// Tools the workflow or blueprint needs, and why
const requiredTools = computed(() => Object.fromEntries(Object.entries(resolved.value?.tools ?? {}).filter(([, t]) => t.required).map(([key, t]) => [key, t.required])));
const structurePiece = computed(() => props.catalog.structures.find((s) => s.key === form.structure));
const sensitive = computed(() => (form.customized ? form.settings.sensitive || !!structurePiece.value?.sensitive : blueprint.value?.sensitive ?? false));
const pieceName = (list, key) => props.catalog[list].find((p) => p.key === key)?.name ?? key;

const slugify = (value) => value.trim().toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
const slug = computed(() => slugify(form.name));
const repoName = computed(() => (form.github.nameEdited ? form.github.name : slug.value));
const editRepoName = (value) => { form.github.name = value; form.github.nameEdited = true; };
const ownerOptions = computed(() => (props.github.owners ?? []).map((o) => ({ value: o.login, label: o.login })));
watch(sensitive, (value) => { if (value) form.github.private = true; });
// The page starts in the language it was opened with, or the starting blueprint's.
// Framework's own blueprints take every language, so they start from the account's language.
const accountLanguage = (value) => (value?.handle?.startsWith('framework/') && value?.choices?.languages?.includes(props.defaults?.language) ? props.defaults.language : null);
watch(blueprint, (value, old) => {
    if (old === undefined) form.language = props.start?.language || accountLanguage(value) || value?.language || 'r';
    else if (value && !fitsLanguage(value)) form.language = accountLanguage(value) || value.language || blueprintLanguages(value)[0];
}, { immediate: true });
// A language the chosen blueprint can't take moves to your default blueprint, the standard one, or the first that fits
watch(() => form.language, () => {
    if (!blueprint.value || fitsLanguage(blueprint.value)) return;
    const next = [defaultBlueprint, props.blueprints.find((b) => b.handle === 'framework/project'), ...sections.value.flatMap((g) => g.rows)]
        .find((b) => b && fitsLanguage(b));
    if (next) useBlueprint(next.id);
});
const stata = computed(() => form.language === 'stata');
// Every language some blueprint here supports
const languages = computed(() => languageOptions.filter((o) => props.blueprints.some((b) => blueprintLanguages(b).includes(o.value))));
const workflowNames = { quarto: 'Quarto', jupyter: 'Jupyter', scripts: 'Scripts only' };
const workflows = computed(() => (blueprint.value?.choices?.workflows ?? Object.keys(workflowNames))
    .filter((w) => form.language !== 'stata' || w === 'scripts'));
const workflowOptions = computed(() => workflows.value.map((w) => ({ value: w, label: workflowNames[w] })));
// Until you pick one, the select shows the workflow your defaults give this language and blueprint
const shownWorkflow = computed({
    get: () => form.workflow || (current.value ?? resolved.value)?.workflow?.key || '',
    set: (value) => { form.workflow = value; },
});
// A workflow the blueprint or language can't take goes back to your default
watch(workflows, (list) => { if (form.workflow && !list.includes(form.workflow)) form.workflow = ''; }, { immediate: true });

const onGitHub = computed(() => form.destination === 'github');
// Why GitHub can't set up a project in this language here, if it can't
const githubBlocked = computed(() => props.githubUnavailable?.[form.language] ?? null);
watch(githubBlocked, (reason) => { if (reason && onGitHub.value) form.destination = 'local'; });
const create = () => form
    .transform((data) => ({
        name: data.name,
        description: data.description,
        ...choicePayload(data),
        destination: data.destination,
        github: onGitHub.value
            ? { enabled: true, owner: data.github.owner, name: repoName.value, private: data.github.private }
            : { enabled: false },
    }))
    .post('/projects');

// Local setup first, always. Then GitHub before the ZIP when GitHub is ready to use, and after it when not.
const destinations = computed(() => {
    const local = {
        key: 'local',
        title: 'Local setup',
        text: form.language === 'stata'
            ? 'In a terminal, run fw login, then fw new with the project name. Framework sets up Git.'
            : form.language === 'python'
                ? 'Framework issues a project token. Pass it to framework.new(key=...) in Python, or to fw new in a terminal. Framework sets up Git.'
                : 'Framework issues a project token. Pass it to framework::setup() in R, or to fw new in a terminal. Framework sets up Git.',
    };
    const download = props.downloads ? [{
        key: 'download',
        title: 'ZIP download',
        text: 'Framework builds the project and your browser downloads it. Unzip it and open the folder. You need no Git and no Framework packages.',
    }] : [];
    const github = props.github.enabled ? [{
        key: 'github',
        title: 'GitHub repository',
        text: githubBlocked.value ?? 'Framework creates a repository on your GitHub account and sets the project up there. Open it in VS Code, Positron, or RStudio by cloning it.',
        disabled: !!githubBlocked.value,
    }] : [];

    return githubReady.value ? [local, ...github, ...download] : [local, ...download, ...github];
});
const submitLabel = computed(() => ({ download: 'Create and download', github: 'Create on GitHub' })[form.destination] ?? 'Create project');
const destinationName = computed(() => destinations.value.find((d) => d.key === form.destination)?.title ?? '');

const githubError = computed(() => page.props.errors.github ?? form.errors['github.enabled']);


const items = computed(() => [
    { label: 'Name', anchor: 'name' },
    { label: 'Blueprint', anchor: 'blueprint' },
    ...(form.customized ? [{ label: 'Pieces', anchor: 'structure' }] : []),
    { label: 'Destination', anchor: 'where' },
]);
</script>

<template>
    <Head title="New project" />

    <SectionLayout heading="New project" :back="{ label: 'Back to all projects', href: '/projects' }" :items="items">
        <PageHeader title="New project" description="Name the project and choose a blueprint. Download a ZIP, create a GitHub repository, or set up locally." />

        <form id="create-form" class="mt-8 flex flex-col gap-8" @submit.prevent="create">
            <UiPanel id="name" title="Name" flush class="scroll-mt-6">
                <div>
                    <UiInput v-model="form.name" label="Project name" placeholder="e.g. NHANES Study" :error="form.errors.name" />
                    <p v-if="slug" class="mt-1.5 text-xs text-slate-500 dark:text-slate-400">Folder name: <span class="font-mono">{{ slug }}</span></p>
                </div>
                <UiTextarea v-model="form.description" label="Description (optional)" :rows="3" :error="form.errors.description" />
                <div>
                    <UiSelect v-model="form.language" label="Language" :options="languages" :error="form.errors.language" />
                    <p v-if="stata" class="mt-1.5 text-xs text-slate-500 dark:text-slate-400">
                        Stata projects use do-files and the framework Stata package for paths, data, and publishing.
                    </p>
                </div>
                <div v-if="!stata">
                    <UiSelect v-model="shownWorkflow" label="Notebook workflow" :options="workflowOptions" :error="form.errors.workflow" />
                </div>
            </UiPanel>

            <UiSeparator />

            <UiPanel id="blueprint" title="Blueprint" flush class="scroll-mt-6">
                <template #header>
                    <Link href="/blueprints/create" class="text-sm text-sky-700 hover:underline dark:text-sky-400">Build your own</Link>
                </template>
                <p v-if="form.errors.blueprint_id" class="text-sm text-red-600 dark:text-red-400">{{ form.errors.blueprint_id }}</p>

                <div v-for="section in sections" :key="section.key" class="flex flex-col gap-1">
                    <div v-if="sections.length > 1" class="flex items-center justify-between gap-3">
                        <h3 class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ section.label }}</h3>
                        <button
                            type="button" class="text-xs text-sky-700 hover:underline dark:text-sky-400"
                            :aria-expanded="!hidden.includes(section.key)" @click="toggleGroup(section.key)"
                        >{{ hidden.includes(section.key) ? `Show ${section.rows.length}` : 'Hide' }}</button>
                    </div>
                    <div class="-mx-4 flex flex-col divide-y divide-slate-800/10 dark:divide-white/10">
                        <div
                            v-for="b in shownRows(section)" :key="b.id"
                            :class="b.id === form.blueprint_id ? 'bg-sky-50/60 dark:bg-sky-950/30' : ''"
                        >
                            <button
                                type="button" class="flex w-full items-start gap-3 p-4 text-left"
                                :aria-expanded="b.id === form.blueprint_id"
                                @click="chooseBlueprint(b.id)"
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
                                        >Sensitive data</span>
                                    </span>
                                    <span v-if="b.description" class="text-sm text-slate-600 dark:text-slate-400">{{ b.description }}</span>
                                </span>
                                <svg
                                    class="mt-1 size-4 shrink-0 text-slate-400 transition-transform" :class="b.id === form.blueprint_id ? 'rotate-180' : ''"
                                    viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"
                                ><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.17l3.71-3.94a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" /></svg>
                            </button>
                            <div v-if="b.id === form.blueprint_id" class="flex flex-col gap-2 px-4 pb-4 pl-11 text-sm">
                                <!-- What the project gets: the blueprint with your defaults, or the pieces chosen below -->
                                <p v-if="!shownFor(b)" class="text-slate-500 dark:text-slate-400">Resolving…</p>
                                <p v-else-if="shownFor(b).problem" class="text-amber-800 dark:text-amber-300">{{ shownFor(b).problem }}</p>
                                <dl v-else class="grid grid-cols-[auto_minmax(0,1fr)] gap-x-4 gap-y-1 text-slate-600 dark:text-slate-400">
                                    <dt class="text-xs text-slate-500 dark:text-slate-400">Structure</dt><dd>{{ pieceName('structures', shownFor(b).structure) }}</dd>
                                    <template v-if="shownFor(b).workflow"><dt class="text-xs text-slate-500 dark:text-slate-400">Workflow</dt><dd>{{ shownFor(b).workflow.name }}</dd></template>
                                    <dt class="text-xs text-slate-500 dark:text-slate-400">Tools</dt>
                                    <dd>{{ Object.entries(shownFor(b).tools).filter(([, t]) => t.on).map(([k]) => pieceName('tools', k)).join(', ') || 'None' }}</dd>
                                    <template v-if="shownFor(b).package_sets.length">
                                        <dt class="text-xs text-slate-500 dark:text-slate-400">Packages</dt>
                                        <dd><template v-for="(s, i) in shownFor(b).package_sets" :key="s.key">{{ i ? ', ' : '' }}{{ s.name }}<span v-if="s.from === 'yours'" class="text-xs text-slate-500 dark:text-slate-400"> (your default)</span></template></dd>
                                    </template>
                                    <dt class="text-xs text-slate-500 dark:text-slate-400">Agents</dt><dd>{{ agentSummary(shownFor(b).agents.enabled, shownFor(b).agents.named, shownFor(b).agents.other) }}</dd>
                                    <template v-if="shownFor(b).agents.enabled && shownFor(b).skill_sets.length">
                                        <dt class="text-xs text-slate-500 dark:text-slate-400">Skills</dt>
                                        <dd><template v-for="(s, i) in shownFor(b).skill_sets" :key="s.key">{{ i ? ', ' : '' }}{{ s.name }}<span v-if="s.from === 'yours'" class="text-xs text-slate-500 dark:text-slate-400"> (your default)</span></template></dd>
                                    </template>
                                    <template v-for="n in shownFor(b).notices" :key="n"><dt class="sr-only">Note</dt><dd class="col-span-2 text-xs text-amber-800 dark:text-amber-300">{{ n }}</dd></template>
                                </dl>
                                <div class="flex items-center gap-3">
                                    <UiButton v-if="!form.customized" size="sm" variant="outline" :disabled="!shownFor(b) || !!shownFor(b).problem" @click="customize">Change pieces</UiButton>
                                    <UiButton v-else size="sm" variant="ghost" @click="form.customized = false">Use the blueprint as is</UiButton>
                                    <Link :href="`/blueprints/${b.id}`" class="font-mono text-xs text-slate-500 dark:text-slate-400 hover:underline">{{ b.handle }} v{{ b.version }}</Link>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </UiPanel>

            <UiSeparator v-if="form.customized" />
            <ChoicesEditor v-if="form.customized" :form="form" :catalog="catalog" :languages="[form.language]" :required="requiredTools" agent-picker flush>
                <!-- The files and folders this project gets, as the pieces stand -->
                <template #structure-preview>
                    <div class="flex flex-col gap-3 rounded-md bg-white p-3 dark:bg-slate-900" :class="current ? '' : 'opacity-60'">
                        <ProjectTreeView v-if="(current ?? resolved)?.tree" :entries="(current ?? resolved).tree" />
                        <div v-if="(current ?? resolved)?.render_dirs?.length" class="text-xs text-slate-600 dark:text-slate-400">
                            <div class="font-medium text-slate-900 dark:text-white">Render destinations</div>
                            <ul class="mt-1 font-mono">
                                <li v-for="d in (current ?? resolved).render_dirs" :key="d.from">{{ d.from }}/ → {{ d.to }}/</li>
                            </ul>
                        </div>
                        <p v-if="(current ?? resolved)?.settings.sensitive" class="text-xs text-amber-800 dark:text-amber-300">Sensitive data: private folders stay out of Git, and Framework warns before anything private is uploaded or published.</p>
                    </div>
                </template>
            </ChoicesEditor>

            <UiSeparator />

            <UiPanel id="where" title="Destination" flush class="scroll-mt-6">
                <div class="grid gap-3" :class="destinations.length === 3 ? 'lg:grid-cols-3' : 'sm:grid-cols-2'">
                    <button
                        v-for="d in destinations" :key="d.key"
                        type="button"
                        class="flex flex-col gap-1.5 rounded-lg border p-4 text-left transition-colors disabled:cursor-not-allowed disabled:opacity-60"
                        :class="form.destination === d.key
                            ? 'border-sky-500 bg-sky-50 ring-1 ring-sky-500 dark:bg-sky-950/30'
                            : 'border-slate-200 bg-white hover:border-slate-300 dark:border-slate-700 dark:bg-slate-900 dark:hover:border-slate-600'"
                        :aria-pressed="form.destination === d.key"
                        :disabled="d.disabled"
                        @click="form.destination = d.key"
                    >
                        <span class="font-medium">{{ d.title }}</span>
                        <span class="text-sm text-slate-600 dark:text-slate-400">{{ d.text }}</span>
                    </button>
                </div>
                <p v-if="form.errors.destination" class="text-sm text-red-600 dark:text-red-400">{{ form.errors.destination }}</p>
                <p v-if="form.destination === 'download'" class="text-sm text-slate-600 dark:text-slate-400">
                    The project stays here too. You can download it again from its page, and connect the folder to it later with a project token.
                </p>

                <div v-if="onGitHub" class="flex flex-col gap-4 rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
                    <p v-if="!github.login" class="text-sm text-slate-600 dark:text-slate-400">
                        <Link href="/account/github" class="text-sky-700 hover:underline dark:text-sky-400">Connect GitHub</Link> first.
                    </p>
                    <p v-else-if="github.owners === null" class="text-sm text-slate-600 dark:text-slate-400">
                        GitHub could not be reached. Reload to try again.
                    </p>
                    <p v-else-if="!ownerOptions.length" class="text-sm text-slate-600 dark:text-slate-400">
                        <a :href="github.install_url" class="text-sky-700 hover:underline dark:text-sky-400">Install the Framework app</a>
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

            <UiSeparator />

            <!-- What the project gets, just before creating it -->
            <UiPanel v-if="blueprint" id="summary" title="Summary" flush class="scroll-mt-6">
                <dl class="grid grid-cols-[auto_minmax(0,1fr)] gap-x-6 gap-y-2 text-sm">
                    <dt class="text-slate-500 dark:text-slate-400">Name</dt><dd class="break-words font-medium text-slate-900 dark:text-white">{{ form.name || 'Not named yet' }}</dd>
                    <dt class="text-slate-500 dark:text-slate-400">Blueprint</dt>
                    <dd>
                        {{ blueprint.name }}
                        <Link :href="`/blueprints/${blueprint.id}`" class="ml-1 font-mono text-xs text-slate-500 hover:underline dark:text-slate-400">{{ blueprint.handle }} v{{ blueprint.version }}</Link>
                    </dd>
                    <dt class="text-slate-500 dark:text-slate-400">Language</dt><dd>{{ languages.find((l) => l.value === form.language)?.label ?? form.language }}</dd>
                    <template v-if="current?.workflow"><dt class="text-slate-500 dark:text-slate-400">Workflow</dt><dd>{{ current.workflow.name }}</dd></template>
                    <dt class="text-slate-500 dark:text-slate-400">Pieces</dt><dd>{{ form.customized ? 'Changed for this project' : 'As the blueprint has them' }}</dd>
                    <dt class="text-slate-500 dark:text-slate-400">Destination</dt><dd>{{ destinationName }}</dd>
                </dl>
            </UiPanel>

            <UiCallout v-if="githubError" variant="danger" heading="GitHub">{{ githubError }}</UiCallout>
            <div class="flex items-center justify-end gap-3 border-t border-slate-200 pt-6 dark:border-slate-800">
                <Link href="/projects"><UiButton variant="ghost">Cancel</UiButton></Link>
                <UiButton
                    type="submit"
                    :disabled="form.processing || (onGitHub && (!githubReady || (sensitive && !form.github.private)))"
                >{{ submitLabel }}</UiButton>
            </div>
        </form>

        <UiModal v-model="confirming" title="Blueprint change">
            <p class="text-sm text-slate-600 dark:text-slate-400">You changed this project's pieces. Switching to {{ blueprints.find((b) => b.id === pendingBlueprint)?.name }} starts from that blueprint and discards those changes. The name, description, and where it's set up stay.</p>
            <div class="flex justify-end gap-3">
                <UiButton variant="outline" @click="confirming = false">Keep editing</UiButton>
                <UiButton @click="useBlueprint(pendingBlueprint)">Switch blueprint</UiButton>
            </div>
        </UiModal>
    </SectionLayout>
</template>
