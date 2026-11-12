<script>
// The choice sections of the blueprint and project editors, in tab order.
// Basics and Language belong to each page. Notebook format, Environment
// reproducibility, and Git are drawn by the page; this editor draws the rest
// for its section prop.
export const SECTIONS = [
    { key: 'notebooks', label: 'Notebook format' },
    { key: 'quarto', label: 'Quarto' },
    { key: 'jupyter', label: 'Jupyter' },
    { key: 'structure', label: 'Project structure' },
    { key: 'environment', label: 'Reproducibility' },
    { key: 'packages', label: 'Packages' },
    { key: 'scaffold', label: 'Scaffold' },
    { key: 'editor', label: 'Editor support' },
    { key: 'ai', label: 'AI Agents' },
    { key: 'skills', label: 'AI Skills' },
    { key: 'git', label: 'Git' },
    { key: 'connections', label: 'Connections' },
    { key: 'publishing', label: 'Publishing' },
];

// The sections the page draws itself, with fields this editor doesn't hold
export const PAGE_SECTIONS = ['notebooks', 'environment', 'git'];

// The section holding a field with a validation error, such as quarto.html.theme, or null
export const errorSection = (field) => ({
    workflow: 'notebooks', workflows: 'notebooks', notebook_format: 'notebooks',
    quarto: 'quarto',
    structure: 'structure',
    tools: 'environment',
    package_sets: 'packages', package_overrides: 'packages', package_auto_attach: 'packages',
    scaffold: 'scaffold',
    ide: 'editor',
    agents: 'ai',
    skills: 'skills', skill_sets: 'skills', excluded_skills: 'skills',
    settings: 'git',
    publish_destination: 'publishing',
    connections: 'connections',
})[String(field ?? '').split('.')[0]] ?? null;
</script>

<script setup>
import SetChoices from '@/Components/SetChoices.vue';
import { applyContextSkills } from '@/context-skills.js';
import PackagePicker from '@/Components/PackagePicker.vue';
import SkillPicker from '@/Components/SkillPicker.vue';
import { computed, ref, watch } from 'vue';
import UiCheckboxCards from '@/Components/UiCheckboxCards.vue';
import { agentOptions, pick } from '@/agents.js';
import UiPanel from '@/Components/UiPanel.vue';
import UiSeparator from '@/Components/UiSeparator.vue';
import UiSwitch from '@/Components/UiSwitch.vue';
import LayerSummary from '@/Components/LayerSummary.vue';
import ConnectionsEditor from '@/Components/ConnectionsEditor.vue';
import UiInput from '@/Components/UiInput.vue';
import UiSelect from '@/Components/UiSelect.vue';
import UiToggle from '@/Components/UiToggle.vue';
import SettingGroup from '@/Components/SettingGroup.vue';
import SettingRow from '@/Components/SettingRow.vue';
import ThemeSwatch from '@/Components/ThemeSwatch.vue';
import { typeFits } from '@/library.js';
import { editorOptions, editorsOf, highlightLabel, htmlThemeColors, ideOf, slideThemeColors, themeLabel } from '@/project-settings.js';

// The pieces of a project, chosen one by one: a structure, tools, package
// sets, skills, and settings. Edits the given form's fields in place.
const props = defineProps({
    form: Object, // structure, language, tools, package_sets, skill_sets, skills, settings
    catalog: Object,
    languages: { type: Array, default: () => ['r', 'python', 'stata'] },
    required: { type: Object, default: () => ({}) }, // tool key → why it can't be turned off
    // Agent file choices, shared by blueprint and project forms.
    agentPicker: Boolean,
    // Sections on the page, split by separators, rather than cards
    flush: Boolean,
    section: { type: String, default: null },
    // Settings sections shown when no section is given: scaffold, quarto,
    // jupyter, editor, connections, and publishing. Each edits its own form field
    // (scaffold, quarto, tools.jupyter, ide, connections, publish_destination).
    extras: { type: Array, default: () => [] },
    // The choices the Scaffold and Quarto sections offer, from ProjectDefaults
    ggplotThemes: { type: Array, default: () => [] },
    quartoChoices: { type: Object, default: () => ({}) },
});

const f = props.form;
// Quarto, Jupyter, Git, and sensitive structures select their skills, which stay removable
watch(() => JSON.stringify([f.language, f.notebook_format, f.workflow, f.settings?.git, f.structure, f.agents?.includes('none'), f.settings?.ai]),
    () => applyContextSkills(f, props.catalog), { immediate: true });
const visible = section => !props.section || props.section === section;
// The settings sections appear only when asked for, so forms without their fields keep working
const extra = section => (props.section ? props.section === section : props.extras.includes(section));

const editors = computed({ get: () => editorsOf(f.ide), set: (list) => (f.ide = ideOf(list)) });
// RStudio's .Rproj is for R projects
const editorChoices = computed(() => editorOptions.filter((o) => f.language === 'r' || o.value !== 'rstudio'));
const quartoTabs = [{ key: 'html', label: 'HTML documents' }, { key: 'slides', label: 'Slides' }];
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
const rowNumber = 'w-20 rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-900 focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100';
const rowSelect = 'w-44 rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-900 focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100';
const jupyterTool = computed(() => props.catalog.tools.find((t) => t.key === 'jupyter'));
// None stands alone; skills stay as they are while it's selected
const agentModel = computed({ get: () => f.agents, set: (picked) => (f.agents = pick(f.agents, picked)) });
const agentsOn = computed(() => (props.agentPicker ? !f.agents.includes('none') : f.settings.ai));
// Tools that can't be turned off here, with why, such as a workflow that needs them
const required = (tool) => props.required?.[tool.key] ?? null;
const fits = (piece) => !piece.language || piece.language.includes(f.language);

const structures = computed(() => props.catalog.structures.filter((s) => typeFits(s.type, f.language)));
const tools = computed(() => (f.language === 'stata' ? [] : props.catalog.tools.filter(tool => tool.key !== 'targets' && fits(tool))));
const packageSets = computed(() => props.catalog.package_sets.filter(fits));
// Sets stack: a package in more than one chosen set installs once
const stacked = computed(() => {
    const chosen = packageSets.value.filter((set) => props.form.package_sets.includes(set.key));
    const names = chosen.flatMap((set) => set.packages ?? []).map((name) => name.toLowerCase());
    const unique = new Set(names).size;
    return { sets: chosen.length, packages: unique, shared: names.length - unique };
});
const skillSets = computed(() => props.catalog.skill_sets.filter(fits));
const structure = computed(() => props.catalog.structures.find((s) => s.key === f.structure));

const toolOn = (tool) => !!f.tools[tool.key];
const setTool = (tool, on) => {
    f.tools = { ...f.tools, [tool.key]: on };
    // Turning a tool on turns on what it needs; turning one off turns off what needs it
    if (on) tool.requires.forEach((key) => (f.tools[key] = true));
    else props.catalog.tools.filter((t) => t.requires.includes(tool.key)).forEach((t) => (f.tools[t.key] = false));
};
const missing = (tool) => tool.requires.filter((key) => !f.tools[key]).map((key) => props.catalog.tools.find((t) => t.key === key)?.name ?? key);

const toggle = (list, key, on) => {
    const next = f[list].filter((k) => k !== key);
    f[list] = on ? [...next, key] : next;
};

const choiceClass = (on) => on
    ? 'border-sky-500 bg-sky-50 ring-1 ring-sky-500 dark:bg-sky-950/40'
    : 'border-slate-200 bg-white hover:border-slate-400 dark:border-slate-700 dark:bg-slate-900 dark:hover:border-slate-500';
</script>

<template>
    <UiPanel v-if="visible('structure')" :flush="flush" id="structure" title="Project structure" description="The folders and core files the project starts with." class="scroll-mt-6">
        <div class="grid gap-2 sm:grid-cols-2">
            <button
                v-for="s in structures" :key="s.key" type="button"
                class="rounded-lg border p-3 text-left transition-colors" :class="choiceClass(form.structure === s.key)"
                @click="form.structure = s.key"
            >
                <div class="text-sm font-medium">{{ s.name }}</div>
                <div class="mt-0.5 text-xs text-slate-600 dark:text-slate-400">{{ s.description }}</div>
                <div v-if="!s.system" class="mt-1 font-mono text-xs text-slate-400">{{ s.key }}</div>
            </button>
        </div>
        <slot name="structure-preview">
            <div v-if="structure" :class="flush ? '' : 'rounded-md bg-white p-3 dark:bg-slate-900'"><LayerSummary :sections="structure.summary" /></div>
        </slot>
        <p v-if="form.errors.structure" class="text-xs text-red-600 dark:text-red-400">{{ form.errors.structure }}</p>
    </UiPanel>

    <slot v-if="visible('supports')" name="after-structure" />

    <UiSeparator v-if="flush && !section" />
    <UiPanel v-if="visible('tools')" :flush="flush" id="tools" title="Tools" class="scroll-mt-6" :description="form.language === 'stata' ? 'Stata projects have no tools.' : 'Optional tools. Each adds its own files, folders, and packages.'">
        <div v-if="tools.length" class="flex flex-col divide-y divide-slate-800/10 dark:divide-white/15" :class="flush ? '' : 'rounded-lg border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900'">
            <div v-for="tool in tools" :key="tool.key" :class="flush ? 'py-4' : 'p-3'">
                <UiSwitch
                    :model-value="toolOn(tool) || !!required(tool)" :label="tool.name" :disabled="!!required(tool)"
                    :description="required(tool) ? `${required(tool)}, so it stays on.` : tool.description + (tool.requires.length ? ` Needs ${tool.requires.map((k) => catalog.tools.find((t) => t.key === k)?.name ?? k).join(' and ')}.` : '')"
                    @update:model-value="(on) => setTool(tool, on)"
                />
                <p v-if="toolOn(tool) && missing(tool).length" class="mt-1 text-xs text-amber-600">Turn on {{ missing(tool).join(' and ') }} too.</p>
            </div>
        </div>
        <p v-if="form.errors.tools" class="text-xs text-red-600 dark:text-red-400">{{ form.errors.tools }}</p>
    </UiPanel>

    <UiSeparator v-if="flush && !section" />
    <UiPanel v-if="visible('packages')" :flush="flush" id="packages" title="Packages" class="scroll-mt-6">
        <SetChoices v-model="form.package_sets" :sets="catalog.package_sets" :tags="catalog.package_set_tags" :language="form.language" label="Package sets" />
        <UiSeparator />
        <PackagePicker v-if="form.language !== 'stata'" v-model="form.package_overrides" v-model:auto-attach="form.package_auto_attach" :selected-sets="form.package_sets" :packages="catalog.packages" :sets="catalog.package_sets" :categories="catalog.package_categories" :language="form.language" />
        <p v-if="form.language === 'stata'" class="text-sm text-slate-500 dark:text-slate-400">Stata package names are recorded in .framework/packages/. Install them manually.</p>
        <p v-if="stacked.sets > 1" class="text-sm text-slate-700 dark:text-slate-300">
            {{ stacked.packages }} packages from {{ stacked.sets }} sets<template v-if="stacked.shared">, with {{ stacked.shared }} in more than one set</template>.
        </p>
        <p class="text-sm text-slate-500 dark:text-slate-400"><Link href="/package-sets/create" class="text-sky-700 hover:underline dark:text-sky-400">Make a package set</Link></p>
        <p v-if="form.errors.package_sets || form.errors.package_overrides || form.errors.package_auto_attach" class="text-xs text-red-600 dark:text-red-400">{{ form.errors.package_sets || form.errors.package_overrides || form.errors.package_auto_attach }}</p>
    </UiPanel>

    <UiSeparator v-if="flush && !section" />
    <UiPanel v-if="visible('ai')" :flush="flush" id="ai" title="AI Agents" class="scroll-mt-6" description="Choose the agent instruction files for this project.">
        <UiCheckboxCards v-if="agentPicker" v-model="agentModel" legend="AI Agents" hide-legend :options="agentOptions" />
        <UiSwitch v-else v-model="form.settings.ai" label="Agent files" description="Adds AGENTS.md and the selected skills." />
        <p v-if="agentPicker" class="text-sm text-slate-500 dark:text-slate-400">Every choice except None adds AGENTS.md. Claude Code adds CLAUDE.md. Codex uses .agents/skills. GitHub Copilot adds .github/copilot-instructions.md.</p>
    </UiPanel>

    <UiSeparator v-if="flush && !section" />
    <UiPanel v-if="visible('skills')" :flush="flush" id="skills" title="AI Skills" class="scroll-mt-6" description="Choose skill sets, then refine the individual skills.">
        <template v-if="agentsOn || agentPicker">
            <p v-if="!agentsOn" class="text-xs text-slate-500 dark:text-slate-400">With None, the project gets no skills. These are kept if you choose an agent again.</p>
            <fieldset :disabled="!agentsOn" class="flex flex-col gap-3" :class="agentsOn ? '' : 'opacity-60'">
                <SetChoices v-model="form.skill_sets" :sets="catalog.skill_sets" :tags="catalog.categories" :skills="catalog.skills" kind="skill" :language="form.language" label="Skill sets" />
                <UiSeparator class="my-3" />
                <SkillPicker v-model="form.skills" :skills="catalog.skills" :sets="catalog.skill_sets" v-model:selected-sets="form.skill_sets" v-model:excluded-skills="form.excluded_skills" :categories="catalog.categories" :language="form.language" />
            </fieldset>
        </template>
        <p v-else class="text-sm text-slate-500 dark:text-slate-400">Enable agent files in AI Agents to include skills.</p>
        <p v-if="form.errors.skills || form.errors.skill_sets || form.errors.excluded_skills" class="text-xs text-red-600 dark:text-red-400">{{ form.errors.skills || form.errors.skill_sets || form.errors.excluded_skills }}</p>
    </UiPanel>

    <UiSeparator v-if="flush && !section" />
    <UiPanel v-if="visible('settings')" :flush="flush" id="settings" title="Settings" class="scroll-mt-6">
        <UiSwitch
            v-model="form.settings.sensitive" label="Sensitive data"
            :description="structure?.sensitive ? 'This structure already keeps private and public folders apart.' : 'Splits data and outputs into private and public folders, keeps private files out of git, and warns before anything private is published.'"
        />
        <UiSwitch v-model="form.settings.git" label="Use git" />
    </UiPanel>
    <UiSeparator v-if="flush && !section && extras.includes('scaffold')" />
    <UiPanel v-if="extra('scaffold') && form.scaffold" :flush="flush" id="scaffold" title="Scaffold" class="scroll-mt-6" description="What scaffold() does at the start of every notebook and script.">
        <p v-if="form.language === 'stata'" class="text-sm text-slate-500 dark:text-slate-400">Stata projects don't run scaffold().</p>
        <div v-else class="flex max-w-xl flex-col gap-5">
            <UiSwitch v-model="form.scaffold.seed_on_scaffold" label="Set a random seed" description="Random results repeat from run to run." />
            <UiInput v-if="form.scaffold.seed_on_scaffold" v-model="form.scaffold.seed" class="-mt-2 max-w-40" label="Seed" placeholder="123" :error="form.errors['scaffold.seed']" />
            <template v-if="form.language === 'r'">
                <UiSwitch v-model="form.scaffold.set_theme_on_scaffold" label="Set a ggplot2 theme" description="Every plot starts from the same theme." />
                <UiSelect v-if="form.scaffold.set_theme_on_scaffold" v-model="form.scaffold.ggplot_theme" class="-mt-2 max-w-48" label="Theme" :options="ggplotThemes.map((t) => ({ value: t, label: t }))" :error="form.errors['scaffold.ggplot_theme']" />
            </template>
            <UiSwitch v-model="form.scaffold.source_all_functions" label="Load your functions" description="Code in functions/ is ready in every notebook and script." />
        </div>
    </UiPanel>

    <UiSeparator v-if="flush && !section && extras.includes('quarto')" />
    <UiPanel v-if="extra('quarto') && form.quarto" :flush="flush" id="quarto" title="Quarto" class="scroll-mt-6" description="Settings for each new project's _quarto.yml.">
        <p v-if="form.language === 'stata'" class="text-sm text-slate-500 dark:text-slate-400">Stata projects don't use Quarto.</p>
        <div v-else class="flex max-w-2xl flex-col gap-6">
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
                <p class="text-sm text-slate-500 dark:text-slate-400">Notebooks and documents rendered to HTML.</p>
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
                    <SettingRow more="Includes images, styles, and scripts in one HTML file. Increases file size. When disabled, the supporting files folder must accompany the HTML." v-slot="{ id, labelledby, describedby }" label="Embed resources" description="Each HTML file opens on its own. Recommended.">
                        <UiToggle :id="id" v-model="form.quarto.html.embed_resources" :aria-labelledby="labelledby" :aria-describedby="describedby" />
                    </SettingRow>
                </SettingGroup>
            </div>

            <div v-show="quartoTab === 'slides'" id="quarto-panel-slides" role="tabpanel" aria-labelledby="quarto-tab-slides" tabindex="0" class="flex flex-col gap-6 focus-visible:outline-none">
                <p class="text-sm text-slate-500 dark:text-slate-400">Presentations rendered with RevealJS.</p>
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
        </div>
        <p v-if="Object.keys(form.errors).some((k) => k.startsWith('quarto'))" class="text-xs text-red-600 dark:text-red-400">Check the Quarto settings: one of them isn't a choice Quarto offers.</p>
    </UiPanel>

    <UiSeparator v-if="flush && !section && extras.includes('jupyter')" />
    <UiPanel v-if="extra('jupyter')" :flush="flush" id="jupyter" title="Jupyter" class="scroll-mt-6">
        <p v-if="form.language === 'stata' || !jupyterTool" class="text-sm text-slate-500 dark:text-slate-400">Stata projects don't use Jupyter.</p>
        <div v-else class="flex max-w-xl flex-col gap-5">
            <UiSwitch
                :model-value="toolOn(jupyterTool) || !!required(jupyterTool)" :disabled="!!required(jupyterTool)"
                label="Add Jupyter to projects using another notebook format"
                :description="required(jupyterTool) ? `${required(jupyterTool)}, so it stays on.` : `Adds ${form.language === 'r' ? 'IRkernel' : 'ipykernel'} so the project can hold .ipynb notebooks.`"
                @update:model-value="(on) => setTool(jupyterTool, on)"
            />
            <p class="text-sm text-slate-500 dark:text-slate-400">Notebook outputs can contain data. The Git tab's notebook output checks stop a commit until they're cleared.</p>
        </div>
    </UiPanel>

    <UiSeparator v-if="flush && !section && extras.includes('editor')" />
    <UiPanel v-if="extra('editor') && 'ide' in form" :flush="flush" id="editor" title="Editor support" class="scroll-mt-6" description="Editor files for each new project.">
        <p v-if="form.language === 'stata'" class="text-sm text-slate-500 dark:text-slate-400">Stata projects don't get editor files.</p>
        <UiCheckboxCards v-else v-model="editors" legend="Editor support" hide-legend :options="editorChoices" />
        <p v-if="form.errors.ide" class="text-xs text-red-600 dark:text-red-400">{{ form.errors.ide }}</p>
    </UiPanel>

    <UiSeparator v-if="flush && !section && extras.includes('connections')" />
    <UiPanel v-if="extra('connections') && form.connections" :flush="flush" id="connections" title="Connections" class="scroll-mt-6" description="Databases and storage buckets the project's code opens by name.">
        <ConnectionsEditor :form="form" />
    </UiPanel>

    <UiSeparator v-if="flush && !section && extras.includes('publishing')" />
    <UiPanel v-if="extra('publishing') && 'publish_destination' in form" :flush="flush" id="publishing" title="Publishing" class="scroll-mt-6">
        <div class="flex max-w-xl flex-col gap-5">
            <UiSelect v-model="form.publish_destination" label="Publishing destination" :options="[{ value: '', label: 'Automatic' }, { value: 'cloud', label: 'Framework Cloud' }, { value: 'github', label: 'GitHub Pages' }]" :error="form.errors.publish_destination" />
            <p class="text-sm text-slate-500 dark:text-slate-400">Automatic uses a configured bucket, then Framework Cloud. Named folder and object storage destinations can be set in each project's settings.</p>
            <p class="text-sm text-slate-500 dark:text-slate-400">A GitHub Pages site is public, even when its repository is private. Only GitHub Enterprise Cloud can limit a site to people with access to the repository.</p>
        </div>
    </UiPanel>
</template>
