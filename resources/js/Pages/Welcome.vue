<script setup>
import { pageClass } from '@/ui.js';
import { computed, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import BlueprintPreview from '@/Components/BlueprintPreview.vue';
import UiButton from '@/Components/UiButton.vue';
import UiCheckboxCards from '@/Components/UiCheckboxCards.vue';
import UiRadioCards from '@/Components/UiRadioCards.vue';
import { agentOptions, agentSummary, fromChoices, pick, toChoices } from '@/agents.js';

/**
 * Guided setup: six questions, then a review. The answers are a draft until
 * Save defaults on the review; Skip setup and leaving save nothing. Setup
 * starts from what the account saved and recommends only where it saved
 * nothing.
 */
const props = defineProps({ blueprints: Array, values: Object, again: Boolean, saved: Object });

const languageNames = { r: 'R', python: 'Python', stata: 'Stata' };
const workflowNames = { quarto: 'Quarto', rmarkdown: 'R Markdown', jupyter: 'Jupyter', scripts: 'Scripts only' };
const recommendedWorkflows = { r: 'quarto', python: 'jupyter', stata: 'scripts' };
const recommendedEditors = { r: ['rstudio'], python: ['vscode'], stata: [] };
const ideEditors = { rstudio: ['rstudio'], vscode: ['vscode'], positron: ['vscode'], both: ['rstudio', 'vscode'], none: [] };

// The saved answers, read the same way setup asks them. R Markdown is R's Quarto workflow writing .Rmd.
const savedWorkflow = (lang) => (lang === 'r' && props.values.workflows.r === 'quarto' && props.values.rmarkdown ? 'rmarkdown' : props.values.workflows[lang]);
const savedAgents = () => toChoices(props.values.ai_enabled, props.values.ai_agents, props.values.ai_other);
const fromSaved = {
    language: props.values.language ?? 'r',
    project_type: props.values.project_type,
    workflows: Object.fromEntries(['r', 'python', 'stata'].map((l) => [l, savedWorkflow(l)])),
    editors: ideEditors[props.values.ide] ?? [],
    git: props.values.use_git,
    agents: savedAgents(),
};

// Progress saved when someone left for the structure builder, picked up once
const STORE = 'welcome.progress';
const restored = (() => {
    try {
        const saved = JSON.parse(sessionStorage.getItem(STORE) ?? 'null');
        sessionStorage.removeItem(STORE);
        return saved;
    } catch {
        return null;
    }
})();

const startLanguage = props.saved.language ? fromSaved.language : 'r';
const draft = useForm(restored?.answers ?? {
    language: startLanguage,
    project_type: props.saved.any ? fromSaved.project_type : 'project',
    // One workflow answer per language, so switching languages never changes another's
    workflows: Object.fromEntries(['r', 'python', 'stata'].map((l) => [l, props.saved.workflows.includes(l) ? fromSaved.workflows[l] : recommendedWorkflows[l]])),
    editors: props.saved.any ? fromSaved.editors : recommendedEditors[startLanguage],
    git: fromSaved.git,
    agents: fromSaved.agents,
});
// Without saved editors, the recommendation follows the language until the person picks
const editorsChosen = ref(restored?.editorsChosen ?? props.saved.any);
watch(() => draft.language, (language) => {
    if (!editorsChosen.value) draft.editors = [...recommendedEditors[language]];
});

const blueprint = computed(() => props.blueprints.find((b) => b.key === draft.project_type));
const supports = (b, language = draft.language) => b.languages.includes(language);
// R Markdown rides on the Quarto workflow
const workflowSupported = (w) => (blueprint.value?.workflows ?? []).includes(w === 'rmarkdown' ? 'quarto' : w);
const workflow = computed({
    get: () => draft.workflows[draft.language],
    set: (w) => (draft.workflows = { ...draft.workflows, [draft.language]: w }),
});

// What's out of step, shown where it happens and on the review; nothing changes on its own
const problems = computed(() => ({
    project: blueprint.value && !supports(blueprint.value) ? `${blueprint.value.name} isn't available for ${languageNames[draft.language]}. Choose another structure.` : null,
    workflow: draft.language !== 'stata' && blueprint.value && supports(blueprint.value) && !workflowSupported(workflow.value)
        ? `${blueprint.value.name} uses ${blueprint.value.workflows.map((w) => workflowNames[w]).join(' or ')}. Choose one of those, or another structure.`
        : null,
}));

const steps = computed(() => [
    { key: 'language', title: 'Primary language' },
    { key: 'project', title: 'Project structure' },
    ...(draft.language === 'stata' ? [] : [
        { key: 'workflow', title: 'Notebook workflow' },
        { key: 'editors', title: 'Editors' },
    ]),
    { key: 'git', title: 'Git version control' },
    { key: 'agents', title: 'Coding agents' },
    { key: 'review', title: 'Default settings' },
]);
const index = ref(restored?.index ?? 0);
const step = computed(() => steps.value[Math.min(index.value, steps.value.length - 1)]);
const blocked = computed(() => !!problems.value[step.value.key]);
const next = () => (index.value = Math.min(index.value + 1, steps.value.length - 1));
const back = () => (index.value = Math.max(index.value - 1, 0));
const edit = (key) => (index.value = steps.value.findIndex((s) => s.key === key));

const languageOptions = [
    { value: 'r', label: 'R' },
    { value: 'python', label: 'Python' },
    { value: 'stata', label: 'Stata' },
];
// What sets each structure apart; the tree beside them shows the folders
const structureNotes = {
    project: 'Separate folders for inputs, scripts, notebooks, and outputs. Suits most analyses.',
    project_sensitive: 'The standard folders, with inputs and outputs split into private and public. Private files stay out of Git.',
    presentation: 'One slide deck, with the data and code behind it.',
    course: 'Slides, modules, assignments, and readings for teaching.',
    bare: 'Start with the essential Framework settings and organize your own folders.',
};
const projectOptions = computed(() => props.blueprints.map((b) => ({
    value: b.key,
    label: b.name,
    description: supports(b) ? structureNotes[b.key] ?? b.description : `Not available for ${languageNames[draft.language]}.`,
    disabled: !supports(b) && draft.project_type !== b.key,
})));
const workflowNotes = {
    quarto: 'Write notebooks as Quarto documents (.qmd).',
    rmarkdown: 'Write notebooks as R Markdown documents (.Rmd).',
    jupyter: 'Write notebooks in Jupyter (.ipynb). Quarto still renders them.',
    scripts: 'Write your analysis as scripts, without notebooks.',
};
const workflowOptions = computed(() => (draft.language === 'r' ? ['quarto', 'rmarkdown', 'jupyter', 'scripts'] : ['quarto', 'jupyter', 'scripts']).map((w) => ({
    value: w,
    label: workflowNames[w],
    description: workflowSupported(w) ? workflowNotes[w] : `${blueprint.value?.name} doesn't use it.`,
    disabled: !workflowSupported(w) && workflow.value !== w,
})));
const editorOptions = [
    { value: 'rstudio', label: 'RStudio', description: 'Adds a .Rproj file to R projects.' },
    { value: 'vscode', label: 'Positron / VS Code', description: 'Adds a .code-workspace file.' },
];
const editors = computed({
    get: () => draft.editors,
    set: (e) => {
        draft.editors = e;
        editorsChosen.value = true;
    },
});
const gitOptions = [
    { value: 'yes', label: 'Yes', description: 'Each new project starts as a Git repository with a first commit.' },
    { value: 'no', label: 'No', description: 'You can run git init in a project later.' },
];
const gitChoice = computed({ get: () => (draft.git ? 'yes' : 'no'), set: (v) => (draft.git = v === 'yes') });
// None stands alone; the rest combine freely
const agents = computed({
    get: () => draft.agents,
    set: (picked) => (draft.agents = pick(draft.agents, picked)),
});

// The summary previews your other defaults with these answers in place
const ide = computed(() => {
    const e = draft.editors;
    return e.includes('rstudio') && e.includes('vscode') ? 'both' : e.includes('rstudio') ? 'rstudio' : e.includes('vscode') ? 'vscode' : 'none';
});
const aiOn = computed(() => fromChoices(draft.agents).enabled);
const named = computed(() => fromChoices(draft.agents).agents);
const sensitiveHooks = computed(() => (draft.project_type === 'project_sensitive' && draft.git && !Object.values(props.values.hooks ?? {}).some(Boolean)
    ? { data_security: true, check_sensitive_dirs: true }
    : null));
const previewValues = computed(() => {
    const w = workflow.value;
    return {
        ...props.values,
        workflows: { ...props.values.workflows, [draft.language]: w === 'rmarkdown' ? 'quarto' : w },
        rmarkdown: draft.language === 'r' ? w === 'rmarkdown' : props.values.rmarkdown,
        ide: ide.value,
        use_git: draft.git,
        // A privacy-sensitive setup turns on the safety hooks, unless you chose some already
        hooks: sensitiveHooks.value ?? props.values.hooks,
        ai_enabled: aiOn.value,
        ai_agents: aiOn.value ? named.value : props.values.ai_agents,
    };
});

const describe = {
    language: (a) => languageNames[a.language],
    project: (a) => props.blueprints.find((b) => b.key === a.project_type)?.name,
    workflow: (a) => workflowNames[a.workflows[a.language]],
    editors: (a) => a.editors.map((e) => editorOptions.find((o) => o.value === e)?.label).join(', ') || 'None',
    git: (a) => (a.git ? 'Yes' : 'No'),
    agents: (a) => {
        const { enabled, agents: named, other } = fromChoices(a.agents);
        return agentSummary(enabled, named, other);
    },
};
const review = computed(() => [
    { key: 'language', label: 'Language' },
    { key: 'project', label: 'Project structure' },
    ...(draft.language === 'stata' ? [{ key: 'workflow', label: 'Notebook workflow', fixed: 'Scripts only' }] : [
        { key: 'workflow', label: 'Notebook workflow' },
        { key: 'editors', label: 'Editor support' },
    ]),
    { key: 'git', label: 'Git' },
    { key: 'agents', label: 'Agents' },
].map((row) => {
    const value = row.fixed ?? describe[row.key](draft);
    // Returning people see what differs from what they saved
    const before = props.saved.any && !row.fixed ? describe[row.key]({ ...fromSaved, language: row.key === 'workflow' ? draft.language : fromSaved.language }) : null;
    return { ...row, value, before: before !== null && before !== value ? before : null, problem: problems.value[row.key] };
}));
const savable = computed(() => !Object.values(problems.value).some(Boolean));

// Leaving for the structure builder keeps the draft and place for when you come back
const remember = () => {
    try {
        sessionStorage.setItem(STORE, JSON.stringify({ answers: draft.data(), index: index.value, editorsChosen: editorsChosen.value }));
    } catch {}
};

const save = () => draft
    .transform((d) => ({
        language: d.language,
        project_type: d.project_type,
        workflow: d.language === 'stata' ? 'scripts' : d.workflows[d.language],
        editors: d.language === 'stata' ? fromSaved.editors : d.editors,
        git: d.git,
        ai: aiOn.value,
        agents: named.value,
        other_agents: fromChoices(d.agents).other,
    }))
    .post('/welcome');
const skip = () => router.post('/welcome/skip');
const heading = 'text-sm font-semibold text-slate-900 dark:text-white';
const note = 'text-sm text-slate-600 dark:text-slate-400';
</script>

<template>
    <Head title="Default settings" />

    <AppLayout bleed>
        <div :class="[pageClass, 'flex flex-col gap-8']">
            <div>
                <p class="text-sm text-slate-500">{{ step.key === 'review' ? 'Review' : `Step ${index + 1} of ${steps.length - 1}` }}</p>
                <h1 class="mt-1 text-2xl font-semibold text-slate-900 dark:text-white">{{ step.title }}</h1>
                <p v-if="index === 0" :class="note" class="mt-2">Your answers set the defaults for new projects. You can change them later in Default settings.</p>
            </div>

            <!-- One question at a time -->
            <div class="min-h-64">
                <UiRadioCards v-if="step.key === 'language'" v-model="draft.language" name="language" :legend="step.title" hide-legend :options="languageOptions" stacked />

                <div v-else-if="step.key === 'project'" class="flex flex-col gap-3">
                    <p :class="note">These structures suit anything from a small analysis to a larger project. Choose a default here. Each project can still use a different one.</p>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Choose Bare for a custom folder layout. You can also <Link href="/structures/create" class="text-sky-600 hover:underline dark:text-sky-400" @click="remember">create a custom project structure</Link>.</p>
                    <p v-if="problems.project" class="text-sm text-amber-800 dark:text-amber-300" role="alert">{{ problems.project }}</p>
                    <!-- The chosen structure's folders beside the choices -->
                    <div class="grid gap-8 lg:grid-cols-[minmax(0,28rem)_minmax(0,1fr)]">
                        <UiRadioCards v-model="draft.project_type" name="project_type" :legend="step.title" hide-legend :options="projectOptions" stacked />
                        <section class="self-start rounded-lg border border-slate-200 p-4 lg:sticky lg:top-6 dark:border-slate-700" aria-live="polite">
                            <h2 class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-500">{{ blueprint?.name }}</h2>
                            <BlueprintPreview v-if="blueprint && supports(blueprint)" tree-only :blueprint="blueprint" :language="draft.language" :values="previewValues" />
                        </section>
                    </div>
                </div>

                <div v-else-if="step.key === 'workflow'" class="flex flex-col gap-3">
                    <p v-if="problems.workflow" class="text-sm text-amber-800 dark:text-amber-300" role="alert">{{ problems.workflow }}</p>
                    <UiRadioCards v-model="workflow" :name="`workflow_${draft.language}`" :legend="step.title" hide-legend :options="workflowOptions" stacked />
                </div>

                <div v-else-if="step.key === 'editors'" class="flex flex-col gap-3">
                    <p :class="note">Select all that apply.</p>
                    <UiCheckboxCards v-model="editors" :legend="step.title" hide-legend :options="editorOptions" />
                </div>

                <UiRadioCards v-else-if="step.key === 'git'" v-model="gitChoice" name="git" :legend="step.title" hide-legend :options="gitOptions" stacked />

                <div v-else-if="step.key === 'agents'" class="flex flex-col gap-3">
                    <p :class="note">Select all that apply.</p>
                    <UiCheckboxCards v-model="agents" :legend="step.title" hide-legend :options="agentOptions" />
                    <details class="max-w-md text-sm text-slate-600 dark:text-slate-400">
                        <summary class="cursor-pointer text-sky-700 hover:underline dark:text-sky-400">Agent files</summary>
                        <ul class="mt-2 flex list-disc flex-col gap-1 pl-5">
                            <li>Every choice except None adds AGENTS.md, the project's instructions, and Framework's skills.</li>
                            <li>Claude Code: a CLAUDE.md that imports AGENTS.md, with skills in .claude/skills.</li>
                            <li>Codex: skills in .agents/skills.</li>
                            <li>GitHub Copilot: .github/copilot-instructions.md, with skills in .agents/skills.</li>
                        </ul>
                    </details>
                </div>

                <div v-else class="flex max-w-3xl flex-col gap-6">
                    <dl class="divide-y divide-slate-200 rounded-lg border border-slate-200 dark:divide-slate-800 dark:border-slate-700">
                        <div v-for="row in review" :key="row.key" class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 px-4 py-2.5 text-sm">
                            <dt class="text-slate-500">{{ row.label }}</dt>
                            <dd class="flex items-center gap-4 text-right">
                                <span class="flex flex-col items-end">
                                    <span class="text-slate-900 dark:text-white">{{ row.value }}</span>
                                    <span v-if="row.before" class="text-xs text-slate-500">Was {{ row.before }}</span>
                                    <span v-if="row.problem" class="text-xs text-amber-800 dark:text-amber-300">{{ row.problem }}</span>
                                </span>
                                <button v-if="!row.fixed" type="button" class="text-sky-600 hover:underline dark:text-sky-400" @click="edit(row.key)">Edit<span class="sr-only"> {{ row.label }}</span></button>
                            </dd>
                        </div>
                    </dl>
                    <section class="flex flex-col gap-3">
                        <h2 :class="heading">Project contents</h2>
                        <div class="rounded-lg border border-slate-200 p-4 dark:border-slate-700">
                            <BlueprintPreview v-if="blueprint && savable" :blueprint="blueprint" :language="draft.language" :values="previewValues" />
                        </div>
                    </section>
                    <p class="text-sm text-slate-500">Nothing is saved until you choose Save defaults.</p>
                    <p v-if="Object.keys(draft.errors).length" class="text-sm text-red-600">{{ Object.values(draft.errors)[0] }}</p>
                </div>
            </div>

            <div class="flex items-center justify-between border-t border-slate-200 pt-6 dark:border-slate-800">
                <UiButton variant="ghost" @click="skip">{{ again ? 'Cancel' : 'Skip setup' }}</UiButton>
                <div class="flex items-center gap-3">
                    <UiButton v-if="index > 0" variant="outline" @click="back">Back</UiButton>
                    <UiButton v-if="step.key !== 'review'" :disabled="blocked" @click="next">{{ index === steps.length - 2 ? 'Review defaults' : 'Continue' }}</UiButton>
                    <UiButton v-else :disabled="draft.processing || !savable" @click="save">{{ draft.processing ? 'Saving…' : 'Save defaults' }}</UiButton>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
