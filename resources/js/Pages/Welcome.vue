<script setup>
import { computed, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import BlueprintPreview from '@/Components/BlueprintPreview.vue';
import UiButton from '@/Components/UiButton.vue';
import UiCheckboxCards from '@/Components/UiCheckboxCards.vue';
import UiRadioCards from '@/Components/UiRadioCards.vue';

/**
 * Guided setup: six questions that set your defaults for new projects, then
 * a summary of what new projects will include. Every answer is preselected
 * from the ones before it, and nothing is saved until the summary.
 */
const props = defineProps({ blueprints: Array, values: Object, again: Boolean });

const languageNames = { r: 'R', python: 'Python', stata: 'Stata' };
const workflowNames = { quarto: 'Quarto', jupyter: 'Jupyter', scripts: 'Scripts only' };
const initialWorkflows = { r: 'quarto', python: 'jupyter', stata: 'scripts' };
const ideEditors = { rstudio: ['rstudio'], vscode: ['vscode'], positron: ['vscode'], both: ['rstudio', 'vscode'], none: [] };

const form = useForm({
    language: props.values.language ?? 'r',
    project_type: props.values.project_type,
    workflow: null,
    editors: props.again ? (ideEditors[props.values.ide] ?? []) : null,
    git: props.values.use_git,
    ai: props.values.ai_enabled,
    agents: [...props.values.ai_agents],
});

const blueprint = computed(() => props.blueprints.find((b) => b.key === form.project_type));
const supported = (b) => b.languages.includes(form.language);
const workflowsFor = (b) => (b?.workflows ?? []).filter((w) => form.language !== 'stata' || w === 'scripts');

// Earlier answers preselect later ones: a project type the language has, the
// language's workflow where the project type has it, and the language's editor
const preferredWorkflow = () => props.values.workflows[form.language] ?? initialWorkflows[form.language];
watch(() => form.language, () => {
    if (!blueprint.value || !supported(blueprint.value)) form.project_type = 'project';
    if (!props.again || form.editors === null) form.editors = form.language === 'r' ? ['rstudio'] : form.language === 'python' ? ['vscode'] : [];
}, { immediate: true });
// A new language starts from its own workflow; a new project type keeps the
// workflow when it has it
watch([() => form.language, () => form.project_type], ([language], [previous]) => {
    const options = workflowsFor(blueprint.value);
    const preferred = preferredWorkflow();
    if (language !== previous || !options.includes(form.workflow)) form.workflow = options.includes(preferred) ? preferred : options[0];
}, { immediate: true });

const steps = computed(() => [
    { key: 'language', title: 'Which language do you work in most?' },
    { key: 'project', title: 'What kind of project do you usually start?' },
    ...(form.language === 'stata' ? [] : [
        { key: 'workflow', title: 'How do you write notebooks?' },
        { key: 'editors', title: 'Which editors do you use?' },
    ]),
    { key: 'git', title: 'Should new projects use Git?' },
    { key: 'agents', title: 'Do you work with AI coding agents?' },
    { key: 'summary', title: 'Your project defaults' },
]);
const index = ref(0);
const step = computed(() => steps.value[Math.min(index.value, steps.value.length - 1)]);
const next = () => (index.value = Math.min(index.value + 1, steps.value.length - 1));
const back = () => (index.value = Math.max(index.value - 1, 0));
const go = (key) => (index.value = steps.value.findIndex((s) => s.key === key));

const languageOptions = [
    { value: 'r', label: 'R', description: 'Projects built by the framework R package, with renv for package versions.' },
    { value: 'python', label: 'Python', description: 'Projects built by the framework Python package, with uv for the environment.' },
    { value: 'stata', label: 'Stata', description: 'Folders, master.do, and do-files, set up by the fw command-line tool. Framework doesn\'t manage Stata packages.' },
];
const projectOptions = computed(() => props.blueprints.filter(supported).map((b) => ({ value: b.key, label: b.name, description: b.description })));
const workflowOptions = computed(() => workflowsFor(blueprint.value).map((w) => ({
    value: w,
    label: workflowNames[w],
    description: {
        quarto: 'Quarto notebooks (.qmd) that render to HTML, PDF, and Word.',
        jupyter: 'Jupyter notebooks (.ipynb) with the kernel for your language. Quarto stays available to render and publish them.',
        scripts: 'No notebooks. Your analysis lives in scripts.',
    }[w],
})));
const editorOptions = [
    { value: 'rstudio', label: 'RStudio', description: 'Adds a .Rproj file to R projects.' },
    { value: 'vscode', label: 'Positron / VS Code', description: 'Adds a .code-workspace file.' },
];
const gitOptions = [
    { value: 'yes', label: 'Yes', description: 'Each new project starts as a Git repository with a first commit.' },
    { value: 'no', label: 'No', description: 'You can still run git init in a project later.' },
];
const aiOptions = [
    { value: 'yes', label: 'Yes', description: 'Adds an AGENTS.md that tells agents how the project works, and Framework skills they load when they need them.' },
    { value: 'no', label: 'No', description: 'No agent files.' },
];
const agentOptions = [
    { value: 'claude', label: 'Claude Code', description: 'Adds a CLAUDE.md that imports AGENTS.md, and skills in .claude/skills.' },
    { value: 'codex', label: 'Codex', description: 'Reads AGENTS.md, and skills in .agents/skills.' },
    { value: 'copilot', label: 'GitHub Copilot', description: 'Adds .github/copilot-instructions.md, and skills in .agents/skills.' },
];
const yesNo = (key) => computed({ get: () => (form[key] ? 'yes' : 'no'), set: (v) => (form[key] = v === 'yes') });
const gitChoice = yesNo('git');
const aiChoice = yesNo('ai');

// What the summary previews: your other defaults with these answers in place
const ide = computed(() => {
    const e = form.editors ?? [];
    return e.includes('rstudio') && e.includes('vscode') ? 'both' : e.includes('rstudio') ? 'rstudio' : e.includes('vscode') ? 'vscode' : 'none';
});
const previewValues = computed(() => ({
    ...props.values,
    workflows: { ...props.values.workflows, [form.language]: form.workflow },
    ide: ide.value,
    use_git: form.git,
    ai_enabled: form.ai,
    ai_agents: form.ai ? form.agents : props.values.ai_agents,
}));
const answers = computed(() => [
    { key: 'language', label: 'Language', value: languageNames[form.language] },
    { key: 'project', label: 'Project type', value: blueprint.value?.name },
    ...(form.language === 'stata' ? [] : [
        { key: 'workflow', label: 'Workflow', value: workflowNames[form.workflow] },
        { key: 'editors', label: 'Editors', value: (form.editors ?? []).map((e) => editorOptions.find((o) => o.value === e)?.label).join(', ') || 'None' },
    ]),
    { key: 'git', label: 'Git', value: form.git ? 'Yes' : 'No' },
    { key: 'agents', label: 'AI agents', value: form.ai ? (form.agents.map((a) => agentOptions.find((o) => o.value === a)?.label).join(', ') || 'Yes, AGENTS.md only') : 'No' },
]);

const save = () => form.transform((data) => ({ ...data, editors: data.editors ?? [] })).post('/welcome');
const skip = () => router.post('/welcome/skip');
const heading = 'text-sm font-semibold text-slate-900 dark:text-white';
</script>

<template>
    <Head title="Set up your defaults" />

    <AppLayout bleed>
        <div class="mx-auto flex max-w-3xl flex-col gap-8 px-6 py-10 lg:px-12">
            <div class="flex items-start justify-between gap-6">
                <div>
                    <p class="text-sm text-slate-500">{{ step.key === 'summary' ? 'Review' : `Question ${index + 1} of ${steps.length - 1}` }}</p>
                    <h1 class="mt-1 text-2xl font-semibold text-slate-900 dark:text-white">{{ step.title }}</h1>
                    <p v-if="index === 0" class="mt-2 text-sm text-slate-600 dark:text-slate-400">
                        A few questions set your defaults for new projects. Every way of creating a project uses them: this site, R, Python, and fw.
                        You can change any of them later in Project defaults.
                    </p>
                </div>
                <button type="button" class="shrink-0 text-sm text-slate-500 hover:text-slate-900 hover:underline dark:hover:text-white" @click="skip">
                    {{ again ? 'Cancel' : 'Skip setup' }}
                </button>
            </div>

            <!-- One question at a time -->
            <div class="min-h-64">
                <UiRadioCards v-if="step.key === 'language'" v-model="form.language" name="language" :legend="step.title" hide-legend :options="languageOptions" stacked />

                <div v-else-if="step.key === 'project'" class="flex flex-col gap-3">
                    <p class="text-sm text-slate-600 dark:text-slate-400">Each type sets up its folders. You can pick another type for any project.</p>
                    <UiRadioCards v-model="form.project_type" name="project_type" :legend="step.title" hide-legend :options="projectOptions" stacked />
                </div>

                <div v-else-if="step.key === 'workflow'" class="flex flex-col gap-3">
                    <p v-if="workflowOptions.length === 1" class="text-sm text-slate-600 dark:text-slate-400">{{ blueprint.name }} is built on {{ workflowNames[workflowOptions[0].value] }}, so that's its workflow.</p>
                    <UiRadioCards v-model="form.workflow" name="workflow" :legend="step.title" hide-legend :options="workflowOptions" stacked />
                </div>

                <div v-else-if="step.key === 'editors'" class="flex flex-col gap-3">
                    <p class="text-sm text-slate-600 dark:text-slate-400">Select every editor you use. Each one gets the file it opens a project with.</p>
                    <UiCheckboxCards v-model="form.editors" :legend="step.title" hide-legend :options="editorOptions" />
                </div>

                <UiRadioCards v-else-if="step.key === 'git'" v-model="gitChoice" name="git" :legend="step.title" hide-legend :options="gitOptions" stacked />

                <div v-else-if="step.key === 'agents'" class="flex flex-col gap-6">
                    <UiRadioCards v-model="aiChoice" name="ai" :legend="step.title" hide-legend :options="aiOptions" stacked />
                    <section v-if="form.ai" class="flex flex-col gap-3">
                        <h2 :class="heading">Which agents?</h2>
                        <p class="text-sm text-slate-600 dark:text-slate-400">Each one gets the files it reads. Agents that read AGENTS.md need nothing more.</p>
                        <UiCheckboxCards v-model="form.agents" legend="Which agents?" hide-legend :options="agentOptions" />
                    </section>
                </div>

                <div v-else class="flex flex-col gap-8">
                    <dl class="divide-y divide-slate-200 rounded-lg border border-slate-200 dark:divide-slate-800 dark:border-slate-700">
                        <div v-for="a in answers" :key="a.key" class="flex items-center justify-between gap-4 px-4 py-2.5 text-sm">
                            <dt class="text-slate-500">{{ a.label }}</dt>
                            <dd class="flex items-center gap-4 text-right text-slate-900 dark:text-white">
                                {{ a.value }}
                                <button type="button" class="text-sky-600 hover:underline dark:text-sky-400" @click="go(a.key)">Change<span class="sr-only"> {{ a.label }}</span></button>
                            </dd>
                        </div>
                    </dl>
                    <section class="flex flex-col gap-3">
                        <h2 :class="heading">What new projects include</h2>
                        <p class="text-sm text-slate-600 dark:text-slate-400">Your other defaults, such as packages and Quarto settings, are included too. Change them anytime in Project defaults.</p>
                        <div class="rounded-lg border border-slate-200 p-4 dark:border-slate-700">
                            <BlueprintPreview v-if="blueprint" :blueprint="blueprint" :language="form.language" :values="previewValues" />
                        </div>
                    </section>
                    <p v-if="Object.keys(form.errors).length" class="text-sm text-red-600">{{ Object.values(form.errors)[0] }}</p>
                </div>
            </div>

            <div class="flex items-center justify-between border-t border-slate-200 pt-6 dark:border-slate-800">
                <UiButton v-if="index > 0" variant="outline" @click="back">Back</UiButton>
                <span v-else />
                <UiButton v-if="step.key !== 'summary'" @click="next">Continue</UiButton>
                <UiButton v-else :disabled="form.processing" @click="save">{{ form.processing ? 'Saving…' : 'Save my defaults' }}</UiButton>
            </div>
        </div>
    </AppLayout>
</template>
