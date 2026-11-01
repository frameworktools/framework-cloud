<script setup>
import { computed, ref } from 'vue';
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

// One form for all of your defaults, shown a page at a time. Moving
// between pages keeps unsaved changes, and Save sends everything.
const props = defineProps({ section: String, blueprints: Array, catalog: Object, presets: Array, ggplotThemes: Array, quartoChoices: Object, values: Object });

const page = usePage();
const saved = computed(() => page.props.flash.status === 'defaults-updated');

const sections = [
    { heading: 'New projects' },
    { key: 'language', label: 'Language' },
    { key: 'blueprint', label: 'Blueprint' },
    { key: 'editor', label: 'Editor support' },
    { key: 'packages', label: 'Packages' },
    { key: 'scaffold', label: 'Scaffold' },
    { key: 'git', label: 'Git' },
    { key: 'hooks', label: 'Data safety hooks' },
    { key: 'agents', label: 'Agents' },
    { heading: 'Notebooks' },
    { key: 'notebooks', label: 'Default format', title: 'Default notebook format' },
    { key: 'quarto', label: 'Quarto' },
    { key: 'jupyter', label: 'Jupyter' },
];
const items = sections.map((s) => (s.heading ? s : { label: s.label, href: `/settings/${s.key}`, preserveState: true }));
const current = computed(() => sections.find((s) => s.key === props.section));

const languageNames = { r: 'R', python: 'Python', stata: 'Stata' };
const languageOptions = [
    { value: 'r', label: 'R', description: 'Use the R statistical computing environment.' },
    { value: 'python', label: 'Python', description: 'Use the Python programming language.' },
    { value: 'stata', label: 'Stata', description: 'Scaffolding only: folders, master.do, settings.do with path globals, starter do-files, Git, and agent files. Framework doesn\'t install or manage Stata packages.' },
];
// The notebook new projects make by default. Jupyter needs its kernel, so choosing it turns the Jupyter tool on.
const formatOptions = computed(() => [
    { value: 'quarto', label: 'Quarto', description: 'Quarto notebooks (.qmd) that render to HTML, PDF, and Word.' },
    { value: 'jupyter', label: 'Jupyter', description: 'Jupyter notebooks (.ipynb), with the kernel for the project\'s language.' },
    { value: 'rmarkdown', label: 'R Markdown', description: form.language === 'r' || form.language === 'stata' ? 'R Markdown notebooks (.Rmd). R only.' : `R Markdown notebooks (.Rmd). Not used in ${languageNames[form.language]} projects.` },
]);
const notebookFormat = computed({
    get: () => form.notebook_format,
    set: (format) => {
        form.notebook_format = format;
        if (format === 'jupyter') setTool('jupyter', true);
    },
});
// Editor files: RStudio's .Rproj and VS Code and Positron's .code-workspace,
// saved as the one value R and Python read (positron means vscode)
const editorOptions = [
    { value: 'rstudio', label: 'RStudio', description: 'Adds a .Rproj file to R projects.' },
    { value: 'vscode', label: 'Positron / VS Code', description: 'Adds a .code-workspace file.' },
];const hooks = [
    { key: 'data_security', label: 'Check for secrets', description: 'Blocks a commit that looks like it holds passwords, keys, or other secrets.' },
    { key: 'check_sensitive_dirs', label: 'Check private folders', description: "Warns when a private data or output folder isn't in .gitignore. The commit goes ahead." },
    { key: 'check_notebook_outputs', label: 'Check notebook outputs', description: 'Blocks a commit of a Jupyter notebook that still has outputs, since outputs can hold data. Clear them first.' },
];

const form = useForm({
    ...props.values,
    tools: { ...props.values.tools },
    hooks: { ...props.values.hooks },
    packages: { r: [...(props.values.packages?.r ?? [])], python: [...(props.values.packages?.python ?? [])] },
    quarto: { html: { ...props.values.quarto.html }, revealjs: { ...props.values.quarto.revealjs } },
});
const defaultBlueprint = computed(() => props.blueprints.find((b) => b.key === form.project_type));
// Quarto's names for themes and highlighting, shown the way the GUI showed them
const titled = (value) => value.charAt(0).toUpperCase() + value.slice(1);
const themeLabel = titled;
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

// The agents R, Python, and fw set projects up for, and what each one gets
const agentOptions = [
    { value: 'claude', label: 'Claude Code', description: 'Adds a CLAUDE.md that imports AGENTS.md, and skills in .claude/skills.' },
    { value: 'codex', label: 'Codex', description: 'Reads AGENTS.md, and skills in .agents/skills.' },
    { value: 'copilot', label: 'GitHub Copilot', description: 'Adds .github/copilot-instructions.md, and skills in .agents/skills.' },
];

const editors = computed({
    get: () => ({ rstudio: ['rstudio'], vscode: ['vscode'], positron: ['vscode'], both: ['rstudio', 'vscode'] })[form.ide] ?? [],
    set: (list) => {
        const rstudio = list.includes('rstudio');
        const vscode = list.includes('vscode');
        form.ide = rstudio && vscode ? 'both' : rstudio ? 'rstudio' : vscode ? 'vscode' : 'none';
    },
});

const tool = (key) => props.catalog.tools.find((t) => t.key === key);
const only = (piece) => (piece?.language?.length === 1 ? (piece.language[0] === 'r' ? 'R only.' : 'Python only.') : '');
// Your default language decides what these pages point out: a piece for
// another language stays as it is, dimmed, since other projects may use it
const fits = (piece) => (form.language === 'stata' ? false : !piece?.language?.length || piece.language.includes(form.language));
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
    <Head :title="`${current.title ?? current.label} · Project defaults`" />

    <SectionLayout heading="Project defaults" :items="items" narrow :dirty="form.isDirty">
        <PageHeader v-if="section === 'blueprint'" :title="current.label" description="Select a default Blueprint for new projects.">
            <template #actions>
                <Link href="/blueprints" class="text-sm text-slate-500 hover:text-slate-900 hover:underline dark:hover:text-white">Edit blueprints</Link>
                <a href="/docs/projects-and-blueprints#how-defaults-apply" class="ml-3 text-sm text-slate-500 hover:text-slate-900 hover:underline dark:hover:text-white">Documentation</a>
            </template>
        </PageHeader>
        <PageHeader v-else-if="section === 'packages'" :title="current.label" description="Choose default packages for new projects." />
        <PageHeader v-else-if="section === 'editor'" :title="current.label" description="Select the editors/IDEs you typically use." />
        <PageHeader v-else-if="section === 'language'" :title="current.label" description="Choose your default statistical programming environment for new projects." />
        <PageHeader v-else-if="section === 'scaffold'" :title="current.label" description="Configure behaviors triggered by scaffold()." />
        <PageHeader v-else-if="section === 'hooks'" :title="current.label" description="Configure Git hooks for data safety protections." />
        <PageHeader v-else-if="section === 'agents'" :title="current.label" description="Configure project setup for AI agents." />
        <PageHeader v-else-if="section === 'notebooks'" :title="current.title" description="Select the format make_notebook() uses for new notebooks. A project can still hold notebooks in other formats." />
        <PageHeader v-else-if="section === 'quarto'" :title="current.label" description="Configure Quarto for new projects." />
        <PageHeader v-else-if="section === 'jupyter'" :title="current.label" description="Configure Jupyter for new projects." />
        <PageHeader v-else :title="current.label" />

        <form id="settings-form" class="mt-8 flex max-w-3xl flex-col gap-8" @submit.prevent="save">
            <UiRadioCards v-if="section === 'language'" v-model="form.language" name="language" legend="Language" hide-legend :options="languageOptions" stacked />

            <template v-else-if="section === 'blueprint'">
                <DefaultBlueprintList v-model="form.project_type" :blueprints="blueprints" :defaults="form" />
                <p v-if="form.errors.project_type" class="-mt-6 text-sm text-red-600">{{ form.errors.project_type }}</p>
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
                    <h2 :class="heading">Dependency management &amp; reproducibility</h2>
                    <UiSwitch v-if="packageLanguage === 'r'" v-model="form.use_renv" label="renv" description="Records the R package versions the project uses in renv.lock." />
                    <UiSwitch v-else-if="tool('uv')" :model-value="!!form.tools.uv" :label="tool('uv').name" description="Manages the Python environment in pyproject.toml and uv.lock." @update:model-value="(on) => setTool('uv', on)" />
                </section>
            </template>

            <section v-else-if="section === 'scaffold'" class="flex max-w-xl flex-col gap-5">
                <UiSwitch
                    v-model="form.seed_on_scaffold" :class="dim(rAndPython)" label="Set a random seed"
                    :description="describe(rAndPython, 'scaffold() sets a seed, so random results repeat from run to run. In Python it seeds random and NumPy\'s global generator.')"
                />
                <UiInput v-if="form.seed_on_scaffold" v-model="form.seed" class="-mt-2 max-w-40" :class="dim(rAndPython)" label="Seed" :error="form.errors.seed" />
                <UiSwitch
                    v-model="form.set_theme_on_scaffold" :class="dim(rOnly)" label="Set a ggplot2 theme"
                    :description="describe(rOnly, 'scaffold() calls theme_set(), so every plot starts from the same theme.')"
                />
                <UiSelect v-if="form.set_theme_on_scaffold" v-model="form.ggplot_theme" class="-mt-2 max-w-48" :class="dim(rOnly)" label="Theme" :options="ggplotThemes.map((t) => ({ value: t, label: t }))" :error="form.errors.ggplot_theme" />
                <UiSwitch
                    v-model="form.source_all_functions" :class="dim(rAndPython)" label="Load your functions"
                    :description="describe(rAndPython, 'scaffold() makes functions/ ready in every notebook and script. R sources each file in it. Python adds the folder to the import path, so functions/helpers.py imports as helpers.')"
                />
                <UiSwitch v-if="tool('targets')" :class="dim(tool('targets'))" :model-value="!!form.tools.targets" :label="tool('targets').name" :description="describe(tool('targets'))" @update:model-value="(on) => setTool('targets', on)" />
            </section>

            <section v-else-if="section === 'git'" class="flex max-w-xl flex-col gap-5">
                <UiSwitch v-model="form.use_git" label="Set up Git" description="Makes each new project a git repository." />
                <p :class="note"><Link href="/settings/hooks" preserve-state :class="link">Data safety hooks</Link> can check each commit for secrets and private folders.</p>
            </section>

            <template v-else-if="section === 'agents'">
                <section class="flex max-w-xl flex-col gap-5">
                    <UiSwitch v-model="form.ai_enabled" label="Set new projects up for agents" description="Adds a context file that tells coding agents how the project works, and the files each agent you use reads. Off adds no agent files." />
                </section>
                <fieldset :disabled="!form.ai_enabled" class="flex flex-col gap-8" :class="form.ai_enabled ? '' : 'opacity-60'">
                    <p v-if="!form.ai_enabled" class="text-xs text-slate-500">Agent setup is off, so these don't apply. They're kept for when you turn it back on.</p>
                    <section class="flex flex-col gap-3">
                        <div>
                            <h2 :class="heading">Agents you use</h2>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Select every agent you work with. Each one gets the files it reads.</p>
                        </div>
                        <UiCheckboxCards v-model="form.ai_agents" legend="Agents you use" hide-legend :options="agentOptions" />
                        <p class="max-w-md text-xs text-slate-500">The instructions go in AGENTS.md, which most agents read. Claude Code's CLAUDE.md imports it, and other agents that read AGENTS.md need no extra files.</p>
                    </section>
                    <div class="max-w-xl">
                        <UiSwitch v-model="form.ai_skills" :disabled="!form.ai_enabled" label="Install Framework skills" description="Adds instructions for working in a Framework project that agents load when they need them." />
                    </div>
                    <section class="flex flex-col gap-3">
                        <div>
                            <h2 :class="heading">Skill sets</h2>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Used when a new project doesn't start from a blueprint.</p>
                        </div>
                        <UiCheckboxCards
                            v-model="form.skill_sets" legend="Skill sets" hide-legend
                            :options="catalog.skill_sets.map((set) => ({ value: set.key, label: set.name, description: describe(set), dim: !fits(set) }))"
                        />
                        <p class="text-sm">
                            <Link href="/skill-sets" :class="link">Manage skill sets</Link> ·
                            <Link href="/skills" :class="link">Manage skills</Link>
                        </p>
                    </section>
                </fieldset>
            </template>

            <section v-else-if="section === 'hooks'" class="flex max-w-xl flex-col gap-5">
                <p :class="note">For R and Python projects with Git.<template v-if="form.language === 'stata'"> Stata projects don't get hooks.</template></p>
                <UiSwitch
                    v-for="hook in hooks" :key="hook.key"
                    :model-value="!!form.hooks[hook.key]" :label="hook.label" :description="hook.description"
                    @update:model-value="(on) => (form.hooks = { ...form.hooks, [hook.key]: on })"
                />
                <p :class="note">
                    Which folders count as private comes from the project's blueprint. Every project, sensitive or not,
                    also warns before it uploads or publishes a file whose path contains "private".
                </p>
            </section>

            <template v-else-if="section === 'notebooks'">
                <p v-if="form.language === 'stata'" class="text-sm text-slate-600 dark:text-slate-400">Stata projects don't get notebooks. These apply to your R and Python projects.</p>
                <UiRadioCards v-model="notebookFormat" name="notebook_format" legend="Default notebook format" hide-legend :options="formatOptions" stacked />
                <p :class="note">Name another format when you need one, such as make_notebook("analysis.ipynb") in a Quarto project.</p>
                <p v-if="form.errors.notebook_format" class="text-sm text-red-600">{{ form.errors.notebook_format }}</p>
            </template>

            <section v-else-if="section === 'jupyter'" class="flex max-w-xl flex-col gap-5" :class="form.language === 'stata' ? 'opacity-60' : ''">
                <p v-if="form.language === 'stata'" class="text-sm text-slate-600 dark:text-slate-400">Stata projects don't use Jupyter. These apply to your R and Python projects.</p>
                <UiSwitch
                    v-if="tool('jupyter')" :model-value="!!form.tools.jupyter" label="Jupyter"
                    :disabled="form.notebook_format === 'jupyter'"
                    :description="form.notebook_format === 'jupyter'
                        ? 'Jupyter notebooks with the kernel for the project\'s language: IRkernel for R, ipykernel for Python. On while Jupyter is your default notebook format.'
                        : 'Jupyter notebooks with the kernel for the project\'s language: IRkernel for R, ipykernel for Python. Turn it on to use Jupyter alongside your default format.'"
                    @update:model-value="(on) => setTool('jupyter', on)"
                />
                <p :class="note">
                    In Python projects, save_notebook() renders a Jupyter notebook with Quarto, into the
                    <Link href="/settings/quarto" preserve-state :class="link">render folders</Link> with the HTML settings there.
                </p>
                <p :class="note">
                    Notebooks keep their outputs, which can hold data.
                    <Link href="/settings/hooks" preserve-state :class="link">Check notebook outputs</Link> blocks a commit until they're cleared.
                </p>
            </section>

            <template v-else-if="section === 'quarto'">
                <p v-if="form.language === 'stata'" :class="note">Stata projects don't use Quarto. These apply to your R and Python projects.</p>
                <div class="flex max-w-2xl flex-col gap-6">
                    <!-- Tabs: arrow keys, Home, and End move between them; every panel stays in the form -->
                    <div role="tablist" aria-label="Quarto settings" class="flex gap-1 overflow-x-auto border-b border-slate-200 dark:border-slate-700">
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
                            <SettingRow v-slot="{ id, describedby }" label="Theme" description="Colors and fonts for the page." :example="{ href: 'https://bootswatch.com/', label: 'See the themes' }">
                                <select :id="id" v-model="form.quarto.html.theme" :aria-describedby="describedby" :class="rowSelect">
                                    <option v-for="t in quartoChoices['html.theme']" :key="t" :value="t">{{ themeLabel(t) }}</option>
                                </select>
                            </SettingRow>
                            <SettingRow v-slot="{ id, describedby }" label="Syntax highlighting" description="Colors for code." :example="{ href: 'https://www.garrickadenbuie.com/blog/pandoc-syntax-highlighting-examples/', label: 'See the styles' }">
                                <select :id="id" v-model="form.quarto.html.highlight_style" :aria-describedby="describedby" :class="rowSelect">
                                    <option v-for="t in quartoChoices.highlight_style" :key="t" :value="t">{{ highlightLabel(t) }}</option>
                                </select>
                            </SettingRow>
                        </SettingGroup>
                        <SettingGroup title="Contents">
                            <SettingRow v-slot="{ id, labelledby, describedby }" label="Table of contents" description="A table of contents beside the document.">
                                <UiToggle :id="id" v-model="form.quarto.html.toc" :aria-labelledby="labelledby" :aria-describedby="describedby" />
                            </SettingRow>
                            <SettingRow more="Depth 2 lists # and ## headings, and depth 3 adds ###. Quarto takes 1 to 6." v-if="form.quarto.html.toc" v-slot="{ id, describedby }" label="Depth" description="How many heading levels it lists.">
                                <input :id="id" v-model.number="form.quarto.html.toc_depth" type="number" min="1" max="6" :aria-describedby="describedby" :class="rowNumber">
                            </SettingRow>
                        </SettingGroup>
                        <SettingGroup title="Code">
                            <SettingRow v-slot="{ id, labelledby, describedby }" label="Code folding" description="Readers can collapse and expand code blocks.">
                                <UiToggle :id="id" v-model="form.quarto.html.code_fold" :aria-labelledby="labelledby" :aria-describedby="describedby" />
                            </SettingRow>
                            <SettingRow more="Adds a &quot;Code&quot; menu at the top of the page. Readers can show or hide every code block at once and view or download the notebook&#x27;s source." v-slot="{ id, labelledby, describedby }" label="Code tools" description="A menu to show, hide, or download the code.">
                                <UiToggle :id="id" v-model="form.quarto.html.code_tools" :aria-labelledby="labelledby" :aria-describedby="describedby" />
                            </SettingRow>
                        </SettingGroup>
                        <SettingGroup title="File portability">
                            <SettingRow more="The HTML file holds its images, styles, and scripts, so you can email it or upload it as one file. Files are larger. Off, the HTML needs the folder of supporting files rendered beside it." v-slot="{ id, labelledby, describedby }" label="Embed resources" description="Each HTML file opens on its own.">
                                <UiToggle :id="id" v-model="form.quarto.html.embed_resources" :aria-labelledby="labelledby" :aria-describedby="describedby" />
                            </SettingRow>
                        </SettingGroup>
                    </div>

                    <div v-show="quartoTab === 'slides'" id="quarto-panel-slides" role="tabpanel" aria-labelledby="quarto-tab-slides" tabindex="0" class="flex flex-col gap-6 focus-visible:outline-none">
                        <p :class="note">Presentations rendered with RevealJS. These settings go into each new project's _quarto.yml.</p>
                        <SettingGroup title="Appearance">
                            <SettingRow v-slot="{ id, describedby }" label="Theme" description="Colors and fonts for the slides." :example="{ href: 'https://revealjs.com/themes/', label: 'See the themes' }">
                                <select :id="id" v-model="form.quarto.revealjs.theme" :aria-describedby="describedby" :class="rowSelect">
                                    <option v-for="t in quartoChoices['revealjs.theme']" :key="t" :value="t">{{ themeLabel(t) }}</option>
                                </select>
                            </SettingRow>
                            <SettingRow v-slot="{ id, describedby }" label="Syntax highlighting" description="Colors for code." :example="{ href: 'https://www.garrickadenbuie.com/blog/pandoc-syntax-highlighting-examples/', label: 'See the styles' }">
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
                            <SettingRow v-slot="{ id, labelledby, describedby }" label="Progress bar" description="A bar along the bottom that shows how far along the talk is.">
                                <UiToggle :id="id" v-model="form.quarto.revealjs.progress" :aria-labelledby="labelledby" :aria-describedby="describedby" />
                            </SettingRow>
                        </SettingGroup>
                        <SettingGroup title="Playback">
                            <SettingRow v-slot="{ id, labelledby, describedby }" label="Incremental bullets" description="List items appear one at a time.">
                                <UiToggle :id="id" v-model="form.quarto.revealjs.incremental" :aria-labelledby="labelledby" :aria-describedby="describedby" />
                            </SettingRow>
                            <SettingRow v-slot="{ id, describedby }" label="Slide transition" description="How one slide gives way to the next.">
                                <select :id="id" v-model="form.quarto.revealjs.transition" :aria-describedby="describedby" :class="rowSelect">
                                    <option v-for="t in quartoChoices.transition" :key="t" :value="t">{{ themeLabel(t) }}</option>
                                </select>
                            </SettingRow>
                            <SettingRow more="Applies to slides with their own background color or image. With the same background on both slides, only the content transition shows." v-slot="{ id, describedby }" label="Background transition" description="How slide backgrounds change.">
                                <select :id="id" v-model="form.quarto.revealjs.background_transition" :aria-describedby="describedby" :class="rowSelect">
                                    <option v-for="t in quartoChoices.transition" :key="t" :value="t">{{ themeLabel(t) }}</option>
                                </select>
                            </SettingRow>
                        </SettingGroup>
                    </div>

                    <div v-show="quartoTab === 'setup'" id="quarto-panel-setup" role="tabpanel" aria-labelledby="quarto-tab-setup" tabindex="0" class="flex flex-col gap-6 focus-visible:outline-none">
                        <SettingGroup title="Project files" description="Only for new projects that don't start from a blueprint. A blueprint decides for itself, and Framework's blueprints other than Bare include Quarto.">
                            <SettingRow more="Quarto reads _quarto.yml when it renders, so documents in the project share one setup. Your HTML and Slides settings go into these files in any project that has them, including projects from a blueprint." v-if="tool('quarto')" v-slot="{ id, labelledby, describedby }" label="Quarto project files" description="Adds _quarto.yml files to the project.">
                                <UiToggle :id="id" :model-value="!!form.tools.quarto" :aria-labelledby="labelledby" :aria-describedby="describedby" @update:model-value="(on) => setTool('quarto', on)" />
                            </SettingRow>
                        </SettingGroup>
                        <SettingGroup title="Starter documents" description="Only for new projects that don't start from a blueprint. Each one turns Quarto project files on.">
                            <SettingRow
                                v-for="key in ['quarto_manuscript', 'quarto_slides'].filter((k) => tool(k))" :key="key" v-slot="{ id, labelledby, describedby }"
                                :label="tool(key).name" :description="tool(key).description"
                            >
                                <UiToggle :id="id" :model-value="!!form.tools[key]" :aria-labelledby="labelledby" :aria-describedby="describedby" @update:model-value="(on) => setTool(key, on)" />
                            </SettingRow>
                        </SettingGroup>
                        <!-- Render folders belong to the project structure; shown here for the default blueprint -->
                        <SettingGroup title="Render folders" :description="`Where rendered documents go, from the project structure of your default blueprint, ${defaultBlueprint?.name}. In Python projects, Jupyter notebooks saved with save_notebook() render to the same folders.`">
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
