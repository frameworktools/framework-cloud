<script setup>
import { computed, ref, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import SectionLayout from '@/Layouts/SectionLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import DefaultBlueprintList from '@/Components/DefaultBlueprintList.vue';
import UiButton from '@/Components/UiButton.vue';
import UiCheckboxCards from '@/Components/UiCheckboxCards.vue';
import UiInput from '@/Components/UiInput.vue';
import PackagesEditor from '@/Components/PackagesEditor.vue';
import UiRadioCards from '@/Components/UiRadioCards.vue';
import UiSelect from '@/Components/UiSelect.vue';
import UiSwitch from '@/Components/UiSwitch.vue';
import UiToggle from '@/Components/UiToggle.vue';
import SettingGroup from '@/Components/SettingGroup.vue';
import SettingRow from '@/Components/SettingRow.vue';
import ThemeSwatch from '@/Components/ThemeSwatch.vue';
import { agentOptions, fromChoices, pick, toChoices } from '@/agents.js';

// One form for all of your defaults, shown a page at a time. Moving
// between pages keeps unsaved changes, and Save sends everything.
const props = defineProps({ section: String, blueprints: Array, catalog: Object, presets: Array, ggplotThemes: Array, quartoChoices: Object, values: Object });

const page = usePage();
const saved = computed(() => page.props.flash.status === 'defaults-updated');

const sections = [
    { heading: 'New projects' },
    { key: 'language', label: 'Language' },
    { key: 'publishing', label: 'Publishing' },
    { key: 'blueprint', label: 'Blueprint' },
    { key: 'editor', label: 'Editor support' },
    { key: 'packages', label: 'Packages' },
    { key: 'scaffold', label: 'Scaffold' },
    { key: 'git', label: 'Git' },
    { key: 'hooks', label: 'Data safety hooks' },
    { key: 'agents', label: 'Agents' },
    { heading: 'Notebooks' },
    { key: 'notebooks', label: 'Notebook workflow' },
    { key: 'quarto', label: 'Quarto' },
    { key: 'jupyter', label: 'Jupyter' },
];
const items = sections.map((s) => (s.heading ? s : { label: s.label, href: `/settings/${s.key}`, preserveState: true }));
const current = computed(() => sections.find((s) => s.key === props.section));

const languageNames = { r: 'R', python: 'Python', stata: 'Stata' };
const languageOptions = [
    { value: 'r', label: 'R' },
    { value: 'python', label: 'Python' },
    { value: 'stata', label: 'Stata' },
];
// How each language's new projects write notebooks: the workflow. R Markdown
// is R's Quarto workflow writing .Rmd notebooks, saved as Quarto plus the
// R Markdown preference. Stata uses scripts.
const workflowNames = { quarto: 'Quarto', rmarkdown: 'R Markdown', jupyter: 'Jupyter', scripts: 'Scripts only' };
const workflowNotes = {
    quarto: 'Write notebooks as Quarto documents (.qmd).',
    rmarkdown: 'Write notebooks as R Markdown documents (.Rmd).',
    jupyter: 'Write notebooks in Jupyter (.ipynb). Quarto still renders them.',
    scripts: 'Write your analysis as scripts, without notebooks.',
};
const workflowOptionsFor = (lang) => (lang === 'r' ? ['quarto', 'rmarkdown', 'jupyter', 'scripts'] : ['quarto', 'jupyter', 'scripts'])
    .map((w) => ({ value: w, label: workflowNames[w], description: workflowNotes[w] }));
// A language's workflow as the pages show it, from what's saved
const workflowOf = (values, lang) => (lang === 'r' && values.workflows.r === 'quarto' && values.rmarkdown ? 'rmarkdown' : values.workflows[lang]);
// Editor files: RStudio's .Rproj and VS Code and Positron's .code-workspace,
// saved as the one value R and Python read (positron means vscode)
const editorOptions = [
    { value: 'rstudio', label: 'RStudio', description: 'Adds a .Rproj file to R projects.' },
    { value: 'vscode', label: 'Positron / VS Code', description: 'Adds a .code-workspace file.' },
];
const hooks = [
    { key: 'data_security', label: 'Secret detection', description: 'Stops a commit that looks like it contains passwords, keys, or other secrets.' },
    { key: 'check_sensitive_dirs', label: 'Private folder warnings', description: "Warns when a private folder isn't in .gitignore. The commit still goes ahead." },
    { key: 'check_notebook_outputs', label: 'Notebook output checks', description: 'Stops a commit of a Jupyter notebook that still has outputs, which can contain data.' },
];

const form = useForm({
    publish_destination: props.values.publish_destination ?? '',
    ...props.values,
    tools: { ...props.values.tools },
    hooks: { ...props.values.hooks },
    packages: { r: [...(props.values.packages?.r ?? [])], python: [...(props.values.packages?.python ?? [])] },
    quarto: { html: { ...props.values.quarto.html }, revealjs: { ...props.values.quarto.revealjs } },
    workflows: { ...props.values.workflows },
});
const defaultBlueprint = computed(() => props.blueprints.find((b) => b.key === form.project_type));
// R's workflow with R Markdown as its own choice, saved as Quarto plus the preference
const rWorkflow = computed({
    get: () => workflowOf(form, 'r'),
    set: (w) => {
        form.workflows = { ...form.workflows, r: w === 'rmarkdown' ? 'quarto' : w };
        form.rmarkdown = w === 'rmarkdown';
    },
});

// The Blueprint page previews a language and workflow without changing your
// defaults: they start from what's saved, and live outside the form
const previewLanguage = ref(props.values.language ?? 'r');
const previewWorkflow = ref(workflowOf(props.values, previewLanguage.value));
watch(previewLanguage, (lang) => (previewWorkflow.value = workflowOf(form, lang)));
const previewDefaults = computed(() => ({
    ...form.data(),
    workflows: { ...form.workflows, [previewLanguage.value]: previewWorkflow.value === 'rmarkdown' ? 'quarto' : previewWorkflow.value },
    rmarkdown: previewLanguage.value === 'r' ? previewWorkflow.value === 'rmarkdown' : form.rmarkdown,
}));
// Quarto's names for themes and highlighting, shown the way the GUI showed them
const titled = (value) => value.charAt(0).toUpperCase() + value.slice(1);
const themeLabel = titled;
// Each theme's background, text, and accent, roughly, for a small preview
const htmlThemeColors = {
    default: ['#ffffff', '#212529', '#0d6efd'], cosmo: ['#ffffff', '#373a3c', '#2780e3'], cerulean: ['#ffffff', '#495057', '#2fa4e7'],
    flatly: ['#ffffff', '#212529', '#2c3e50'], journal: ['#ffffff', '#222222', '#eb6864'], lumen: ['#ffffff', '#222222', '#158cba'],
    minty: ['#ffffff', '#888888', '#78c2ad'], pulse: ['#ffffff', '#444444', '#593196'], sandstone: ['#ffffff', '#3e3f3a', '#325d88'],
    simplex: ['#fcfcfc', '#212529', '#d9230f'], sketchy: ['#ffffff', '#212529', '#333333'], slate: ['#272b30', '#aaaaaa', '#3a3f44'],
    solar: ['#002b36', '#839496', '#b58900'], spacelab: ['#ffffff', '#777777', '#446e9b'], superhero: ['#0f2537', '#ebebeb', '#df6919'],
    united: ['#ffffff', '#333333', '#e95420'], yeti: ['#ffffff', '#222222', '#008cba'],
};
const slideThemeColors = {
    default: ['#ffffff', '#222222', '#2a76dd'], beige: ['#f7f2d3', '#333333', '#8b743d'], black: ['#191919', '#ffffff', '#42affa'],
    blood: ['#222222', '#eeeeee', '#aa2233'], league: ['#2b2b2b', '#eeeeee', '#13daec'], moon: ['#002b36', '#93a1a1', '#268bd2'],
    night: ['#111111', '#eeeeee', '#e7ad52'], serif: ['#f0f1eb', '#000000', '#51483d'], simple: ['#ffffff', '#000000', '#00008b'],
    sky: ['#add9e4', '#333333', '#3b759e'], solarized: ['#fdf6e3', '#657b83', '#268bd2'], white: ['#ffffff', '#222222', '#2a76dd'],
};
const highlightLabel = (value) => (value === 'github' ? 'GitHub' : value === 'breezedark' ? 'Breeze Dark' : titled(value));
const rowNumber = 'w-20 rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-900 focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100';
const rowSelect = 'w-44 rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-900 focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100';

// The Quarto page's tabs. The choice stays while you move between pages.
const quartoTabs = [{ key: 'html', label: 'HTML documents' }, { key: 'slides', label: 'Slides' }, { key: 'setup', label: 'Project setup' }];
const quartoTab = ref('html');
const quartoTabEls = [];
const quartoTabKey = (event, i) => {
    const next = { ArrowRight: i + 1, ArrowLeft: i - 1, Home: 0, End: quartoTabs.length - 1 }[event.key];
    if (next === undefined) return;
    event.preventDefault();
    const index = (next + quartoTabs.length) % quartoTabs.length;
    quartoTab.value = quartoTabs[index].key;
    quartoTabEls[index]?.focus();
};
const packageLanguage = ref(props.values.language === 'python' ? 'python' : 'r');

// The same agent choices as guided setup, saved as setup on or off, the
// named agents, and other agents; None keeps the rest for when it's back on
const agentChoices = computed({
    get: () => toChoices(form.ai_enabled, form.ai_agents, form.ai_other),
    set: (picked) => {
        const next = fromChoices(pick(toChoices(form.ai_enabled, form.ai_agents, form.ai_other), picked));
        form.ai_enabled = next.enabled;
        if (next.enabled) {
            form.ai_agents = next.agents;
            form.ai_other = next.other;
        }
    },
});

const editors = computed({
    get: () => ({ rstudio: ['rstudio'], vscode: ['vscode'], positron: ['vscode'], both: ['rstudio', 'vscode'] })[form.ide] ?? [],
    set: (list) => {
        const rstudio = list.includes('rstudio');
        const vscode = list.includes('vscode');
        form.ide = rstudio && vscode ? 'both' : rstudio ? 'rstudio' : vscode ? 'vscode' : 'none';
    },
});

const tool = (key) => props.catalog.tools.find((t) => t.key === key);
const only = (piece) => (piece?.language?.length === 1 ? `${languageNames[piece.language[0]]} only.` : '');
// Your default language decides what these pages point out: a piece for
// another language stays as it is, dimmed, since other projects may use it
const fits = (piece) => !piece?.language?.length || piece.language.includes(form.language);
const notUsed = computed(() => `Not used in ${languageNames[form.language]} projects.`);
const describe = (piece, text = piece?.description) => [text, fits(piece) ? only(piece) : notUsed.value].filter(Boolean).join(' ');
// Settings by the languages that read them, described and dimmed the way pieces are
const rOnly = { language: ['r'] };
const rAndPython = { language: ['r', 'python'] };
const dim = (piece) => (fits(piece) ? '' : 'opacity-60');
const setTool = (key, on) => {
    form.tools = { ...form.tools, [key]: on };
    // Turning a tool on turns on what it needs; turning one off turns off what needs it
    if (on) tool(key)?.requires.forEach((k) => (form.tools[k] = true));
    else props.catalog.tools.filter((t) => t.requires.includes(key)).forEach((t) => (form.tools[t.key] = false));
};
const toggle = (list, key, on) => {
    const next = form[list].filter((k) => k !== key);
    form[list] = on ? [...next, key] : next;
};
// Rows left without a name are dropped rather than refused
const save = () => form
    .transform((data) => ({ ...data, packages: Object.fromEntries(Object.entries(data.packages).map(([lang, rows]) => [lang, rows.filter((row) => row.name?.trim())])) }))
    .patch('/settings', { preserveScroll: true });

const link = 'text-sky-600 hover:underline dark:text-sky-400';
const heading = 'text-sm font-semibold text-slate-900 dark:text-white';
const note = 'text-sm text-slate-600 dark:text-slate-400';
</script>

<template>
    <Head :title="`${current.title ?? current.label} · Default settings`" />

    <SectionLayout heading="Default settings" :items="items" narrow :dirty="form.isDirty">
        <PageHeader v-if="section === 'blueprint'" :title="current.label">
            <template #actions>
                <Link href="/welcome" class="text-sm text-slate-500 hover:text-slate-900 hover:underline dark:hover:text-white">Run guided setup</Link>
                <Link href="/blueprints" class="ml-3 text-sm text-slate-500 hover:text-slate-900 hover:underline dark:hover:text-white">Edit blueprints</Link>
                <a href="/docs/projects-and-blueprints#default-precedence" class="ml-3 text-sm text-slate-500 hover:text-slate-900 hover:underline dark:hover:text-white">Documentation</a>
            </template>
        </PageHeader>
        <PageHeader v-else :title="current.label" />

        <form id="settings-form" class="mt-8 flex max-w-3xl flex-col gap-8" @submit.prevent="save">
            <UiRadioCards v-if="section === 'language'" v-model="form.language" name="language" legend="Language" hide-legend :options="languageOptions" stacked />

            <section v-else-if="section === 'publishing'" class="max-w-xl space-y-5">
                <UiSelect v-model="form.publish_destination" label="Publishing destination" :options="[{value:'',label:'Automatic'}, {value:'cloud',label:'Framework Cloud'}, {value:'github',label:'GitHub Pages'}]" :error="form.errors.publish_destination" />
                <p class="text-sm text-slate-500">New projects use this destination. Automatic uses a configured bucket, then Framework Cloud. Named folder and object storage destinations can be set in each project's settings.</p>
                <p class="text-sm text-slate-500">GitHub Pages is public. Private repositories do not make Pages private.</p>
            </section>
            <template v-else-if="section === 'blueprint'">
                <DefaultBlueprintList v-model="form.project_type" :blueprints="blueprints" :defaults="previewDefaults" :language="previewLanguage">
                    <!-- What the preview shows; your defaults stay as they are -->
                    <div class="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm">
                        <label class="flex items-center gap-2 text-slate-600 dark:text-slate-400">
                            Preview language
                            <select v-model="previewLanguage" :class="rowSelect" class="w-32">
                                <option v-for="lang in ['r', 'python', 'stata']" :key="lang" :value="lang">{{ languageNames[lang] }}</option>
                            </select>
                        </label>
                        <label v-if="previewLanguage !== 'stata'" class="flex items-center gap-2 text-slate-600 dark:text-slate-400">
                            Preview workflow
                            <select v-model="previewWorkflow" :class="rowSelect" class="w-40">
                                <option v-for="o in workflowOptionsFor(previewLanguage)" :key="o.value" :value="o.value">{{ o.label }}</option>
                            </select>
                        </label>
                        <span class="text-slate-500">
                            Defaults: <Link href="/settings/language" preserve-state :class="link">language</Link> ·
                            <Link href="/settings/notebooks" preserve-state :class="link">notebook workflow</Link>
                        </span>
                        <!-- Jupyter beyond the workflow comes from the Jupyter defaults, shown as it resolves -->
                        <span v-if="previewLanguage !== 'stata'" class="text-slate-500">
                            Jupyter: {{ previewWorkflow === 'jupyter' ? 'included by the workflow' : form.tools.jupyter ? 'added by your Jupyter default' : 'not added' }}
                            · <Link href="/settings/jupyter" preserve-state :class="link">Jupyter defaults</Link>
                        </span>
                    </div>
                </DefaultBlueprintList>
                <p v-if="form.errors.project_type" class="text-sm text-red-600">{{ form.errors.project_type }}</p>
            </template>

            <template v-else-if="section === 'editor'">
                <p v-if="form.language === 'stata'" class="text-sm text-slate-600 dark:text-slate-400">Stata projects don't get editor files. These apply to your R and Python projects.</p>
                <UiCheckboxCards v-model="editors" legend="Editor support" hide-legend :options="editorOptions" />
            </template>

            <template v-else-if="section === 'packages'">
                <p v-if="form.language === 'stata'" class="text-sm text-slate-600 dark:text-slate-400">Stata projects don't use packages. These apply to your R and Python projects.</p>
                <!-- One list per language; the page starts on your default language -->
                <div class="flex w-fit rounded-md border border-slate-300 p-0.5 text-sm dark:border-slate-700" role="tablist" aria-label="Package language">
                    <button
                        v-for="lang in ['r', 'python']" :key="lang" type="button" role="tab" :aria-selected="packageLanguage === lang"
                        class="rounded px-3 py-1 transition"
                        :class="packageLanguage === lang ? 'bg-slate-200 font-medium text-slate-900 dark:bg-slate-700 dark:text-white' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'"
                        @click="packageLanguage = lang"
                    >{{ languageNames[lang] }}<span class="ml-1.5 text-xs text-slate-500">{{ form.packages[lang].length }}</span></button>
                </div>

                <section class="flex flex-col gap-4">
                    <PackagesEditor
                        :key="packageLanguage" v-model="form.packages[packageLanguage]" :language="packageLanguage"
                        :presets="presets.filter((p) => p.language.includes(packageLanguage) && p.packages.length)"
                    />
                    <p v-if="Object.keys(form.errors).some((k) => k.startsWith('packages.'))" class="text-sm text-red-600">Check the package names: letters, numbers, dots, dashes, and underscores. GitHub packages are owner/repo.</p>
                </section>

                <section class="flex max-w-xl flex-col gap-4 border-t border-slate-200 pt-8 dark:border-slate-800">
                    <h2 :class="heading">Package versions</h2>
                    <UiSwitch v-if="packageLanguage === 'r'" v-model="form.use_renv" label="renv" description="Records the R package versions the project uses in renv.lock." />
                    <UiSwitch v-else-if="tool('uv')" :model-value="!!form.tools.uv" :label="tool('uv').name" description="Manages the Python environment in pyproject.toml and uv.lock." @update:model-value="(on) => setTool('uv', on)" />
                </section>
            </template>

            <section v-else-if="section === 'scaffold'" class="flex max-w-xl flex-col gap-5">
                <UiSwitch
                    v-model="form.seed_on_scaffold" :class="dim(rAndPython)" label="Set a random seed"
                    :description="describe(rAndPython, 'Random results repeat from run to run.')"
                />
                <UiInput v-if="form.seed_on_scaffold" v-model="form.seed" class="-mt-2 max-w-40" :class="dim(rAndPython)" label="Seed" :error="form.errors.seed" />
                <UiSwitch
                    v-model="form.set_theme_on_scaffold" :class="dim(rOnly)" label="Set a ggplot2 theme"
                    :description="describe(rOnly, 'Every plot starts from the same theme.')"
                />
                <UiSelect v-if="form.set_theme_on_scaffold" v-model="form.ggplot_theme" class="-mt-2 max-w-48" :class="dim(rOnly)" label="Theme" :options="ggplotThemes.map((t) => ({ value: t, label: t }))" :error="form.errors.ggplot_theme" />
                <UiSwitch
                    v-model="form.source_all_functions" :class="dim(rAndPython)" label="Load your functions"
                    :description="describe(rAndPython, 'Code in functions/ is ready in every notebook and script.')"
                />
                <UiSwitch v-if="tool('targets')" :class="dim(tool('targets'))" :model-value="!!form.tools.targets" :label="tool('targets').name" :description="describe(tool('targets'))" @update:model-value="(on) => setTool('targets', on)" />
            </section>

            <section v-else-if="section === 'git'" class="flex max-w-xl flex-col gap-5">
                <UiSwitch v-model="form.use_git" label="Git initialization" description="Makes each new project a git repository." />
                <p :class="note"><Link href="/settings/hooks" preserve-state :class="link">Data safety hooks</Link> can check each commit for secrets and private folders.</p>
            </section>

            <template v-else-if="section === 'agents'">
                <section class="flex flex-col gap-3">
                    <h2 :class="heading">Coding agents</h2>
                    <UiCheckboxCards v-model="agentChoices" legend="Coding agents" hide-legend :options="agentOptions" />
                    <details class="max-w-md text-sm text-slate-600 dark:text-slate-400">
                        <summary class="cursor-pointer text-sky-700 hover:underline dark:text-sky-400">Agent files</summary>
                        <ul class="mt-2 flex list-disc flex-col gap-1 pl-5">
                            <li>Every choice except None adds AGENTS.md, the project's instructions, and the skills below.</li>
                            <li>Claude Code: a CLAUDE.md that imports AGENTS.md, with skills in .claude/skills.</li>
                            <li>Codex: skills in .agents/skills.</li>
                            <li>GitHub Copilot: .github/copilot-instructions.md, with skills in .agents/skills.</li>
                        </ul>
                    </details>
                </section>
                <!-- Kept as they are with None, for when an agent is chosen again -->
                <fieldset :disabled="!form.ai_enabled" class="flex flex-col gap-4" :class="form.ai_enabled ? '' : 'opacity-60'">
                    <h2 :class="heading">Skills</h2>
                    <p v-if="!form.ai_enabled" class="text-xs text-slate-500">With None, projects get no skills. These are kept for when you choose an agent.</p>
                    <div class="max-w-xl">
                        <UiSwitch v-model="form.ai_skills" :disabled="!form.ai_enabled" label="Framework skills" description="Instructions for working in a Framework project, which agents load when they need them." />
                    </div>
                    <UiCheckboxCards
                        v-model="form.skill_sets" legend="Skill sets" hide-legend
                        :options="catalog.skill_sets.map((set) => ({ value: set.key, label: set.name, description: describe(set), dim: !fits(set) }))"
                    />
                    <p class="text-sm">
                        <Link href="/skill-sets" :class="link">Manage skill sets</Link> ·
                        <Link href="/skills" :class="link">Manage skills</Link>
                    </p>
                </fieldset>
            </template>

            <section v-else-if="section === 'hooks'" class="flex max-w-xl flex-col gap-5">
                <p :class="note">
                    R and Python projects. They run on each commit, so they need Git<template v-if="!form.use_git">, which is <Link href="/settings/git" preserve-state :class="link">off in your defaults</Link></template>.
                </p>
                <UiSwitch
                    v-for="hook in hooks" :key="hook.key"
                    :model-value="!!form.hooks[hook.key]" :label="hook.label" :description="hook.description"
                    @update:model-value="(on) => (form.hooks = { ...form.hooks, [hook.key]: on })"
                />
                <p :class="note">
                    The project’s blueprint defines its private folders. Every project, sensitive or not,
                    also warns before it uploads or publishes a file whose path contains "private".
                </p>
            </section>

            <template v-else-if="section === 'notebooks'">
                <section class="flex flex-col gap-3">
                    <h2 :class="heading">R</h2>
                    <UiRadioCards v-model="rWorkflow" name="workflow_r" legend="R workflow" hide-legend :options="workflowOptionsFor('r')" stacked />
                </section>
                <section class="flex flex-col gap-3">
                    <h2 :class="heading">Python</h2>
                    <UiRadioCards v-model="form.workflows.python" name="workflow_python" legend="Python workflow" hide-legend :options="workflowOptionsFor('python')" stacked />
                </section>
                <section class="flex flex-col gap-1">
                    <h2 :class="heading">Stata</h2>
                    <p :class="note">Scripts only, with master.do running the do-files in order.</p>
                </section>
                <p :class="note">A project can hold notebooks in other formats too. See <a href="/docs/projects-and-blueprints#notebook-workflows" :class="link">notebook creation and rendering</a>.</p>
            </template>

            <section v-else-if="section === 'jupyter'" class="flex max-w-xl flex-col gap-5">
                <UiSwitch
                    v-if="tool('jupyter')" :model-value="!!form.tools.jupyter" label="Add Jupyter to projects using another workflow"
                    description="Included automatically with the Jupyter workflow."
                    @update:model-value="(on) => setTool('jupyter', on)"
                />
                <p :class="note">
                    Notebook outputs can contain data. <Link href="/settings/hooks" preserve-state :class="link">Notebook output checks</Link> stop a commit until they're cleared.
                    <a href="/docs/projects-and-blueprints#notebook-workflows" :class="link">More about Jupyter in Framework</a>
                </p>
            </section>

            <template v-else-if="section === 'quarto'">
                <p v-if="form.language === 'stata'" :class="note">Stata projects don't use Quarto. These apply to your R and Python projects.</p>
                <div class="flex max-w-2xl flex-col gap-6">
                    <!-- Tabs: arrow keys, Home, and End move between them; every panel stays in the form -->
                    <div role="tablist" aria-label="Quarto settings" class="flex flex-wrap gap-x-1 border-b border-slate-200 dark:border-slate-700">
                        <button
                            v-for="(tab, i) in quartoTabs" :id="`quarto-tab-${tab.key}`" :key="tab.key" :ref="(el) => (quartoTabEls[i] = el)"
                            type="button" role="tab" :aria-selected="quartoTab === tab.key" :aria-controls="`quarto-panel-${tab.key}`" :tabindex="quartoTab === tab.key ? 0 : -1"
                            class="-mb-px shrink-0 rounded-t border-b-2 px-3 py-2 text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-sky-500"
                            :class="quartoTab === tab.key ? 'border-sky-600 text-sky-700 dark:border-sky-400 dark:text-sky-300' : 'border-transparent text-slate-600 hover:border-slate-300 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white'"
                            @click="quartoTab = tab.key" @keydown="quartoTabKey($event, i)"
                        >{{ tab.label }}</button>
                    </div>

                    <div v-show="quartoTab === 'html'" id="quarto-panel-html" role="tabpanel" aria-labelledby="quarto-tab-html" tabindex="0" class="flex flex-col gap-6 focus-visible:outline-none">
                        <p :class="note">Notebooks and documents rendered to HTML. These settings go into each new project's _quarto.yml.</p>
                        <SettingGroup title="Appearance">
                            <SettingRow v-slot="{ id, describedby }" label="Theme" description="Colors and fonts for the page." more="Quarto's HTML themes come from Bootswatch." :example="{ href: 'https://bootswatch.com/', label: 'See the themes' }">
                                <span class="flex items-center gap-2">
                                    <ThemeSwatch :colors="htmlThemeColors[form.quarto.html.theme]" />
                                    <select :id="id" v-model="form.quarto.html.theme" :aria-describedby="describedby" :class="rowSelect">
                                        <option v-for="t in quartoChoices['html.theme']" :key="t" :value="t">{{ themeLabel(t) }}</option>
                                    </select>
                                </span>
                            </SettingRow>
                            <SettingRow v-slot="{ id, describedby }" label="Syntax highlighting" description="Colors for code." more="The styles come from Pandoc, which Quarto uses to highlight code." :example="{ href: 'https://www.garrickadenbuie.com/blog/pandoc-syntax-highlighting-examples/', label: 'See each style' }">
                                <select :id="id" v-model="form.quarto.html.highlight_style" :aria-describedby="describedby" :class="rowSelect">
                                    <option v-for="t in quartoChoices.highlight_style" :key="t" :value="t">{{ highlightLabel(t) }}</option>
                                </select>
                            </SettingRow>
                        </SettingGroup>
                        <SettingGroup title="Contents">
                            <SettingRow v-slot="{ id, labelledby, describedby }" label="Table of contents" description="A table of contents beside the document.">
                                <UiToggle :id="id" v-model="form.quarto.html.toc" :aria-labelledby="labelledby" :aria-describedby="describedby" />
                            </SettingRow>
                            <SettingRow v-if="form.quarto.html.toc" v-slot="{ id, describedby }" label="Depth" description="Number of heading levels.">
                                <input :id="id" v-model.number="form.quarto.html.toc_depth" type="number" min="1" max="6" :aria-describedby="describedby" :class="rowNumber">
                            </SettingRow>
                        </SettingGroup>
                        <SettingGroup title="Code">
                            <SettingRow v-slot="{ id, labelledby, describedby }" label="Code folding" description="Readers can collapse and expand code blocks.">
                                <UiToggle :id="id" v-model="form.quarto.html.code_fold" :aria-labelledby="labelledby" :aria-describedby="describedby" />
                            </SettingRow>
                            <SettingRow v-slot="{ id, labelledby, describedby }" label="Code tools" description="A menu to show, hide, or download the code.">
                                <UiToggle :id="id" v-model="form.quarto.html.code_tools" :aria-labelledby="labelledby" :aria-describedby="describedby" />
                            </SettingRow>
                        </SettingGroup>
                        <SettingGroup title="File portability">
                            <SettingRow more="Includes images, styles, and scripts in one HTML file. Increases file size. When disabled, the supporting files folder must accompany the HTML." v-slot="{ id, labelledby, describedby }" label="Embed resources" description="Each HTML file opens on its own.">
                                <UiToggle :id="id" v-model="form.quarto.html.embed_resources" :aria-labelledby="labelledby" :aria-describedby="describedby" />
                            </SettingRow>
                        </SettingGroup>
                    </div>

                    <div v-show="quartoTab === 'slides'" id="quarto-panel-slides" role="tabpanel" aria-labelledby="quarto-tab-slides" tabindex="0" class="flex flex-col gap-6 focus-visible:outline-none">
                        <p :class="note">Presentations rendered with RevealJS. These settings go into each new project's _quarto.yml.</p>
                        <SettingGroup title="Appearance">
                            <SettingRow v-slot="{ id, describedby }" label="Theme" description="Colors and fonts for the slides." more="Quarto's slide themes are the reveal.js themes." :example="{ href: 'https://revealjs.com/themes/', label: 'See the themes' }">
                                <span class="flex items-center gap-2">
                                    <ThemeSwatch :colors="slideThemeColors[form.quarto.revealjs.theme]" />
                                    <select :id="id" v-model="form.quarto.revealjs.theme" :aria-describedby="describedby" :class="rowSelect">
                                        <option v-for="t in quartoChoices['revealjs.theme']" :key="t" :value="t">{{ themeLabel(t) }}</option>
                                    </select>
                                </span>
                            </SettingRow>
                            <SettingRow v-slot="{ id, describedby }" label="Syntax highlighting" description="Colors for code." more="The styles come from Pandoc, which Quarto uses to highlight code." :example="{ href: 'https://www.garrickadenbuie.com/blog/pandoc-syntax-highlighting-examples/', label: 'See each style' }">
                                <select :id="id" v-model="form.quarto.revealjs.highlight_style" :aria-describedby="describedby" :class="rowSelect">
                                    <option v-for="t in quartoChoices.highlight_style" :key="t" :value="t">{{ highlightLabel(t) }}</option>
                                </select>
                            </SettingRow>
                            <SettingRow v-slot="{ id, labelledby, describedby }" label="Center vertically" description="Slide content sits in the middle of the slide.">
                                <UiToggle :id="id" v-model="form.quarto.revealjs.center" :aria-labelledby="labelledby" :aria-describedby="describedby" />
                            </SettingRow>
                        </SettingGroup>
                        <SettingGroup title="Navigation">
                            <SettingRow v-slot="{ id, labelledby, describedby }" label="Slide numbers" description="Each slide shows its number in the corner.">
                                <UiToggle :id="id" v-model="form.quarto.revealjs.slide_number" :aria-labelledby="labelledby" :aria-describedby="describedby" />
                            </SettingRow>
                            <SettingRow v-slot="{ id, labelledby, describedby }" label="Navigation controls" description="Arrows for moving between slides.">
                                <UiToggle :id="id" v-model="form.quarto.revealjs.controls" :aria-labelledby="labelledby" :aria-describedby="describedby" />
                            </SettingRow>
                            <SettingRow v-slot="{ id, labelledby, describedby }" label="Progress bar" description="Presentation progress along the bottom of each slide.">
                                <UiToggle :id="id" v-model="form.quarto.revealjs.progress" :aria-labelledby="labelledby" :aria-describedby="describedby" />
                            </SettingRow>
                        </SettingGroup>
                        <SettingGroup title="Playback">
                            <SettingRow v-slot="{ id, labelledby, describedby }" label="Incremental bullets" description="List items appear one at a time.">
                                <UiToggle :id="id" v-model="form.quarto.revealjs.incremental" :aria-labelledby="labelledby" :aria-describedby="describedby" />
                            </SettingRow>
                            <SettingRow v-slot="{ id, describedby }" label="Slide transition" description="Animation between slides.">
                                <select :id="id" v-model="form.quarto.revealjs.transition" :aria-describedby="describedby" :class="rowSelect">
                                    <option v-for="t in quartoChoices.transition" :key="t" :value="t">{{ themeLabel(t) }}</option>
                                </select>
                            </SettingRow>
                            <SettingRow v-slot="{ id, describedby }" label="Background transition" description="Animation between slide backgrounds.">
                                <select :id="id" v-model="form.quarto.revealjs.background_transition" :aria-describedby="describedby" :class="rowSelect">
                                    <option v-for="t in quartoChoices.transition" :key="t" :value="t">{{ themeLabel(t) }}</option>
                                </select>
                            </SettingRow>
                        </SettingGroup>
                    </div>

                    <div v-show="quartoTab === 'setup'" id="quarto-panel-setup" role="tabpanel" aria-labelledby="quarto-tab-setup" tabindex="0" class="flex flex-col gap-6 focus-visible:outline-none">
                        <SettingGroup title="Project files">
                            <SettingRow v-if="tool('quarto')" v-slot="{ id, labelledby, describedby }" label="Quarto for script projects" description="Included automatically with the Quarto, R Markdown, and Jupyter workflows.">
                                <UiToggle :id="id" :model-value="!!form.tools.quarto" :aria-labelledby="labelledby" :aria-describedby="describedby" @update:model-value="(on) => setTool('quarto', on)" />
                            </SettingRow>
                        </SettingGroup>
                        <SettingGroup title="Starter documents" description="Added to every new project, along with Quarto.">
                            <SettingRow
                                v-for="key in ['quarto_manuscript', 'quarto_slides'].filter((k) => tool(k))" :key="key" v-slot="{ id, labelledby, describedby }"
                                :label="tool(key).name" :description="tool(key).description"
                            >
                                <UiToggle :id="id" :model-value="!!form.tools[key]" :aria-labelledby="labelledby" :aria-describedby="describedby" @update:model-value="(on) => setTool(key, on)" />
                            </SettingRow>
                        </SettingGroup>
                        <!-- Render folders belong to the project structure; shown here for the default blueprint -->
                        <SettingGroup title="Render folders" :description="`Set by the project structure of your default blueprint, ${defaultBlueprint?.name}.`">
                            <ul v-if="defaultBlueprint?.render_dirs.length" class="divide-y divide-slate-200 font-mono text-sm dark:divide-slate-800">
                                <li v-for="dir in defaultBlueprint.render_dirs" :key="dir.from" class="flex flex-wrap items-center gap-x-3 px-4 py-2.5">
                                    <span class="text-slate-900 dark:text-white">{{ dir.from }}/</span>
                                    <span class="text-slate-400" aria-hidden="true">→</span><span class="sr-only">renders to</span>
                                    <span class="text-slate-600 dark:text-slate-300">{{ dir.to }}/</span>
                                </li>
                            </ul>
                            <p v-else class="px-4 py-3 text-sm text-slate-600 dark:text-slate-400">This structure has no render folders, so documents render next to their source.</p>
                        </SettingGroup>
                        <p class="-mt-4 text-sm"><Link href="/structures" :class="link">Edit project structures</Link></p>
                    </div>
                </div>
            </template>
        </form>

        <template #actions>
            <UiButton save-shortcut type="submit" form="settings-form" class="w-full" :disabled="form.processing">
                {{ form.processing ? 'Saving…' : 'Save changes' }}
            </UiButton>
            <p v-if="saved && !form.isDirty" class="text-center text-xs text-slate-500">Saved.</p>
        </template>
    </SectionLayout>
</template>
