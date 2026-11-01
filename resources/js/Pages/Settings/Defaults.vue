<script setup>
import { computed } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import SectionLayout from '@/Layouts/SectionLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import DefaultBlueprintList from '@/Components/DefaultBlueprintList.vue';
import UiButton from '@/Components/UiButton.vue';
import UiCheckbox from '@/Components/UiCheckbox.vue';
import UiCheckboxCards from '@/Components/UiCheckboxCards.vue';
import UiInput from '@/Components/UiInput.vue';
import UiRadioCards from '@/Components/UiRadioCards.vue';
import UiSwitch from '@/Components/UiSwitch.vue';

// One form for all of your defaults, shown a page at a time. Moving
// between pages keeps unsaved changes, and Save sends everything.
const props = defineProps({ section: String, blueprints: Array, catalog: Object, values: Object });

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
    { key: 'agents', label: 'Agents' },
    { heading: 'Data safety' },
    { key: 'data', label: 'Sensitive data' },
    { key: 'hooks', label: 'Git hooks' },
    { heading: 'Documents' },
    { key: 'notebooks', label: 'Notebooks' },
    { key: 'quarto', label: 'Quarto' },
];
const items = sections.map((s) => (s.heading ? s : { label: s.label, href: `/settings/${s.key}`, preserveState: true }));
const current = computed(() => sections.find((s) => s.key === props.section));

const languageNames = { r: 'R', python: 'Python', stata: 'Stata' };
const languageOptions = [
    { value: 'r', label: 'R', description: 'Use the R statistical computing environment.' },
    { value: 'python', label: 'Python', description: 'Use the Python programming language.' },
    { value: 'stata', label: 'Stata', description: 'Scaffolding only.' },
];
const formatOptions = computed(() => [
    { value: 'quarto', label: 'Quarto' },
    { value: 'rmarkdown', label: 'R Markdown', description: form.language === 'r' ? null : `Not used in ${languageNames[form.language]} projects.` },
]);
// Editor files: RStudio's .Rproj and VS Code and Positron's .code-workspace,
// saved as the one value R and Python read (positron means vscode)
const editorOptions = [
    { value: 'rstudio', label: 'RStudio', description: 'Adds an .Rproj file.' },
    { value: 'vscode', label: 'VS Code and Positron', description: 'Adds a .code-workspace file.' },
];const hooks = [
    { key: 'data_security', label: 'Check for secrets', description: 'Blocks a commit that looks like it holds passwords, keys, or other secrets.' },
    { key: 'check_sensitive_dirs', label: 'Check private folders', description: "Warns when a private data or output folder isn't in .gitignore. The commit goes ahead." },
];

const form = useForm({ ...props.values, tools: { ...props.values.tools }, hooks: { ...props.values.hooks } });

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
const save = () => form.patch('/settings', { preserveScroll: true });

const link = 'text-sky-600 hover:underline dark:text-sky-400';
const heading = 'text-sm font-semibold text-slate-900 dark:text-white';
const note = 'text-sm text-slate-600 dark:text-slate-400';
</script>

<template>
    <Head :title="`${current.label} · Project defaults`" />

    <SectionLayout heading="Project defaults" :items="items" narrow :dirty="form.isDirty">
        <PageHeader v-if="section === 'blueprint'" :title="current.label" description="Select a default Blueprint for new projects.">
            <template #actions>
                <Link href="/blueprints" class="text-sm text-slate-500 hover:text-slate-900 hover:underline dark:hover:text-white">Edit blueprints</Link>
                <a href="/docs/projects-and-blueprints#how-defaults-apply" class="ml-3 text-sm text-slate-500 hover:text-slate-900 hover:underline dark:hover:text-white">Documentation</a>
            </template>
        </PageHeader>
        <PageHeader v-else-if="section === 'editor'" :title="current.label" description="Select the editors/IDEs you typically use." />
        <PageHeader v-else-if="section === 'language'" :title="current.label" description="Choose your default statistical programming environment for new projects." />
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
                <p v-if="form.language === 'stata'" class="text-sm text-slate-600 dark:text-slate-400">Stata projects don't use package sets or environments. These apply to your R and Python projects.</p>
                <section class="flex flex-col gap-4">
                    <div>
                        <h2 :class="heading">Package sets</h2>
                        <p class="mt-1 text-xs text-slate-500">Used when a new project doesn't start from a blueprint.</p>
                    </div>
                    <UiCheckbox
                        v-for="set in catalog.package_sets" :key="set.key" :class="dim(set)"
                        :model-value="form.package_sets.includes(set.key)" :label="set.name" :description="describe(set)"
                        @update:model-value="(on) => toggle('package_sets', set.key, on)"
                    />
                    <p class="text-sm"><Link href="/package-sets" :class="link">Browse package sets</Link></p>
                </section>
                <section class="flex max-w-xl flex-col gap-4 border-t border-slate-200 pt-8 dark:border-slate-800">
                    <h2 :class="heading">Package environments</h2>
                    <UiSwitch v-model="form.use_renv" :class="dim(tool('renv'))" label="renv" :description="describe(tool('renv'), 'Records the R package versions the project uses in renv.lock.')" />
                    <UiSwitch v-if="tool('uv')" :class="dim(tool('uv'))" :model-value="!!form.tools.uv" :label="tool('uv').name" :description="describe(tool('uv'), 'Manages the Python environment in pyproject.toml and uv.lock.')" @update:model-value="(on) => setTool('uv', on)" />
                </section>
            </template>

            <section v-else-if="section === 'scaffold'" class="flex max-w-xl flex-col gap-5">
                <UiSwitch v-model="form.seed_on_scaffold" label="Set a random seed" description="scaffold() sets a seed, so random results repeat from run to run." />
                <UiSwitch v-if="tool('targets')" :class="dim(tool('targets'))" :model-value="!!form.tools.targets" :label="tool('targets').name" :description="describe(tool('targets'))" @update:model-value="(on) => setTool('targets', on)" />
            </section>

            <section v-else-if="section === 'git'" class="flex max-w-xl flex-col gap-5">
                <UiSwitch v-model="form.use_git" label="Set up Git" description="Makes each new project a git repository." />
                <p :class="note"><Link href="/settings/hooks" preserve-state :class="link">Git hooks</Link> can check each commit for secrets and private folders.</p>
            </section>

            <template v-else-if="section === 'agents'">
                <section class="flex max-w-xl flex-col gap-5">
                    <UiSwitch v-model="form.ai_enabled" label="Set new projects up for agents" description="Adds a context file that tells coding agents such as Claude Code and Codex how the project works. Off adds no agent files." />
                </section>
                <fieldset :disabled="!form.ai_enabled" class="flex flex-col gap-6 border-l-2 border-slate-200 pl-5 dark:border-slate-700">
                    <p v-if="!form.ai_enabled" class="text-xs text-slate-500">Agent setup is off, so these don't apply. They're kept for when you turn it back on.</p>
                    <div class="w-full max-w-xs" :class="form.ai_enabled ? '' : 'opacity-50'">
                        <UiInput v-model="form.ai_canonical_file" label="Context file" :error="form.errors.ai_canonical_file" />
                        <p class="mt-1.5 text-xs text-slate-500">The file agents read first, such as AGENTS.md or CLAUDE.md.</p>
                    </div>
                    <div class="max-w-xl">
                        <UiSwitch v-model="form.ai_skills" :disabled="!form.ai_enabled" label="Install Framework skills" description="Adds instructions for working in a Framework project that agents load when they need them." />
                    </div>
                    <section class="flex flex-col gap-4">
                        <div>
                            <h2 :class="heading">Skill sets</h2>
                            <p class="mt-1 text-xs text-slate-500">Used when a new project doesn't start from a blueprint.</p>
                        </div>
                        <UiCheckbox
                            v-for="set in catalog.skill_sets" :key="set.key" :disabled="!form.ai_enabled" :class="form.ai_enabled ? dim(set) : ''"
                            :model-value="form.skill_sets.includes(set.key)" :label="set.name" :description="describe(set)"
                            @update:model-value="(on) => toggle('skill_sets', set.key, on)"
                        />
                        <p class="text-sm">
                            <Link href="/skill-sets" :class="link">Manage skill sets</Link> ·
                            <Link href="/skills" :class="link">Manage skills</Link>
                        </p>
                    </section>
                </fieldset>
            </template>

            <section v-else-if="section === 'data'" class="flex max-w-xl flex-col gap-5">
                <UiSwitch
                    v-model="form.sensitive" label="Treat new projects as sensitive"
                    description="Splits data and outputs into private and public folders and keeps the private ones out of git, even under a blueprint that isn't sensitive. Sensitive projects on GitHub must be private repositories."
                />
                <p :class="note">
                    Every project, sensitive or not, warns before it uploads or publishes a file whose path contains "private".
                    <Link href="/settings/hooks" preserve-state :class="link">Git hooks</Link> can also check commits.
                </p>
            </section>

            <section v-else-if="section === 'hooks'" class="flex max-w-xl flex-col gap-5">
                <p class="text-xs text-slate-500">R projects only; requires Git.</p>
                <UiSwitch
                    v-for="hook in hooks" :key="hook.key"
                    :model-value="!!form.hooks[hook.key]" :label="hook.label" :description="hook.description"
                    @update:model-value="(on) => (form.hooks = { ...form.hooks, [hook.key]: on })"
                />
                <p :class="note">Which folders count as private comes from <Link href="/settings/data" preserve-state :class="link">Sensitive data</Link>.</p>
            </section>

            <template v-else-if="section === 'notebooks'">
                <p v-if="form.language === 'stata'" class="text-sm text-slate-600 dark:text-slate-400">Stata projects don't get notebooks. These apply to your R and Python projects.</p>
                <UiRadioCards v-model="form.notebook_format" name="notebook_format" legend="Notebook format" :options="formatOptions" columns="grid-cols-2 max-w-md" />
                <section v-if="tool('jupyter')" class="max-w-xl border-t border-slate-200 pt-8 dark:border-slate-800">
                    <UiSwitch :model-value="!!form.tools.jupyter" :class="dim(tool('jupyter'))" label="Also add Jupyter" :description="describe(tool('jupyter'), 'Jupyter notebooks with the kernel for the project\'s language: IRkernel for R, ipykernel for Python.')" @update:model-value="(on) => setTool('jupyter', on)" />
                </section>
            </template>

            <section v-else-if="section === 'quarto'" class="flex max-w-xl flex-col gap-5" :class="form.language === 'stata' ? 'opacity-60' : ''">
                <p v-if="form.language === 'stata'" class="text-sm text-slate-600 dark:text-slate-400">Stata projects don't use Quarto. These apply to your R and Python projects.</p>
                <p class="text-xs text-slate-500">Used when a new project doesn't start from a blueprint.</p>
                <UiSwitch v-if="tool('quarto')" :model-value="!!form.tools.quarto" label="Quarto" description="Quarto notebooks and documents." @update:model-value="(on) => setTool('quarto', on)" />
                <div class="flex flex-col gap-5 border-l-2 border-slate-200 pl-5 dark:border-slate-700">
                    <p class="text-xs text-slate-500">These need Quarto. Turning one on turns Quarto on, and turning Quarto off turns them off.</p>
                    <template v-for="key in ['quarto_manuscript', 'quarto_slides']" :key="key">
                        <UiSwitch v-if="tool(key)" :model-value="!!form.tools[key]" :label="tool(key).name" :description="tool(key).description" @update:model-value="(on) => setTool(key, on)" />
                    </template>
                </div>
            </section>
        </form>

        <template #actions>
            <UiButton save-shortcut type="submit" form="settings-form" class="w-full" :disabled="form.processing">
                {{ form.processing ? 'Saving…' : 'Save changes' }}
            </UiButton>
            <p v-if="saved && !form.isDirty" class="text-center text-xs text-slate-500">Saved.</p>
        </template>
    </SectionLayout>
</template>
