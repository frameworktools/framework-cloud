<script setup>
import { computed } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import SectionLayout from '@/Layouts/SectionLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import UiButton from '@/Components/UiButton.vue';
import UiInput from '@/Components/UiInput.vue';
import UiSelect from '@/Components/UiSelect.vue';
import UiSwitch from '@/Components/UiSwitch.vue';
import UiPanel from '@/Components/UiPanel.vue';

// One form for all of your defaults, shown a section at a time. Moving
// between sections keeps unsaved changes, and Save sends everything.
const props = defineProps({ section: String, revision: Number, blueprints: Array, catalog: Object, values: Object });

const page = usePage();
const saved = computed(() => page.props.flash.status === 'defaults-updated');

const sections = [
    { heading: 'New projects' },
    { key: 'basics', label: 'Blueprint & editor', description: "What a new project starts with when you don't choose a blueprint." },
    { key: 'packages', label: 'Packages', description: "Packages new projects install when you don't choose a blueprint. A blueprint brings its own." },
    { key: 'scaffold', label: 'Scaffold', description: 'What happens when you run scaffold().' },
    { key: 'git', label: 'Git', description: 'Version control for new projects.' },
    { key: 'agents', label: 'Agents', description: 'What new projects give coding agents such as Claude Code and Codex.' },
    { heading: 'Data & connections' },
    { key: 'data', label: 'Sensitive data', description: 'How new projects keep private data apart.' },
    { heading: 'Checks' },
    { key: 'hooks', label: 'Git hooks', description: 'Checks that run before each commit.' },
    { heading: 'Documents' },
    { key: 'notebooks', label: 'Notebooks', description: 'The notebooks new projects use.' },
    { key: 'quarto', label: 'Quarto', description: 'Quarto documents, manuscripts, and slides.' },
];
const items = sections.map((s) => (s.heading ? s : { label: s.label, href: `/settings/${s.key}`, preserveState: true }));
const current = computed(() => sections.find((s) => s.key === props.section));

const blueprintOptions = computed(() => props.blueprints.map((b) => ({ value: b.key, label: b.name })));
const formatOptions = [
    { value: 'quarto', label: 'Quarto' },
    { value: 'rmarkdown', label: 'R Markdown' },
];
const ideOptions = [
    { value: 'positron', label: 'Positron' },
    { value: 'vscode', label: 'VS Code' },
    { value: 'rstudio', label: 'RStudio' },
    { value: 'both', label: 'RStudio + VS Code' },
    { value: 'none', label: 'None' },
];
const hooks = [
    { key: 'data_security', label: 'Check for secrets', description: 'Stops a commit that looks like it holds passwords, keys, or other secrets.' },
    { key: 'check_sensitive_dirs', label: 'Warn about private folders git would commit', description: "Warns when a private data or output folder isn't in .gitignore." },
];

const form = useForm({ ...props.values, tools: { ...props.values.tools }, hooks: { ...props.values.hooks } });

const tool = (key) => props.catalog.tools.find((t) => t.key === key);
const languageNote = (piece) => (piece?.language?.length === 1 ? ` ${piece.language[0] === 'r' ? 'R' : 'Python'} projects only.` : '');
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
</script>

<template>
    <Head :title="`${current.label} · Project defaults`" />

    <SectionLayout heading="Project defaults" :items="items">
        <PageHeader :title="current.label" :description="current.description" />
        <p class="mt-2 text-xs text-slate-500">Your defaults are saved to your account and synced to every computer where you've run cloud_login() in R or Python. Revision {{ revision }}.</p>

        <form id="settings-form" class="mt-8 flex flex-col gap-8" @submit.prevent="save">
            <UiPanel v-if="section === 'basics'" title="New projects">
                <UiSelect v-model="form.project_type" label="Default blueprint" :options="blueprintOptions" :error="form.errors.project_type" />
                <UiSelect v-model="form.ide" label="Editor" :options="ideOptions" />
            </UiPanel>

            <template v-else-if="section === 'packages'">
                <UiPanel title="Package sets">
                    <UiSwitch
                        v-for="set in catalog.package_sets" :key="set.key"
                        :model-value="form.package_sets.includes(set.key)" :label="set.name" :description="set.description + languageNote(set)"
                        @update:model-value="(on) => toggle('package_sets', set.key, on)"
                    />
                    <p class="text-sm text-slate-500"><Link href="/package-sets" class="text-sky-600 hover:underline dark:text-sky-400">See all package sets</Link></p>
                </UiPanel>
                <UiPanel title="Environments">
                    <UiSwitch v-model="form.use_renv" label="renv" description="Records the project's R packages in a lockfile. R projects only." />
                    <UiSwitch v-if="tool('uv')" :model-value="!!form.tools.uv" :label="tool('uv').name" :description="tool('uv').description + languageNote(tool('uv'))" @update:model-value="(on) => setTool('uv', on)" />
                </UiPanel>
            </template>

            <UiPanel v-else-if="section === 'scaffold'" title="When scaffold() runs">
                <UiSwitch v-model="form.seed_on_scaffold" label="Set a random seed" description="Makes random results the same each run." />
                <UiSwitch v-if="tool('targets')" :model-value="!!form.tools.targets" :label="tool('targets').name" :description="tool('targets').description + languageNote(tool('targets'))" @update:model-value="(on) => setTool('targets', on)" />
            </UiPanel>

            <UiPanel v-else-if="section === 'git'" title="Git">
                <UiSwitch v-model="form.use_git" label="Set up git" description="Makes each new project a git repository." />
            </UiPanel>

            <template v-else-if="section === 'agents'">
                <UiPanel title="Agents">
                    <UiSwitch v-model="form.ai_enabled" label="Set new projects up for agents" description="Adds a context file that tells agents how the project works. Turn off for no agent files at all." />
                    <UiInput v-model="form.ai_canonical_file" label="Context file" :error="form.errors.ai_canonical_file" />
                    <UiSwitch v-model="form.ai_skills" label="Install Framework skills" />
                </UiPanel>
                <UiPanel title="Skill sets" description="Added to new projects that don't start from a blueprint.">
                    <UiSwitch
                        v-for="set in catalog.skill_sets" :key="set.key"
                        :model-value="form.skill_sets.includes(set.key)" :label="set.name" :description="set.description + languageNote(set)"
                        @update:model-value="(on) => toggle('skill_sets', set.key, on)"
                    />
                    <p class="text-sm text-slate-500">
                        <Link href="/skill-sets" class="text-sky-600 hover:underline dark:text-sky-400">Manage skill sets</Link> ·
                        <Link href="/skills" class="text-sky-600 hover:underline dark:text-sky-400">Manage skills</Link>
                    </p>
                </UiPanel>
            </template>

            <UiPanel v-else-if="section === 'data'" title="Sensitive data">
                <UiSwitch
                    v-model="form.sensitive" label="Treat new projects as sensitive"
                    description="Splits data and outputs into private and public folders and keeps private files out of git, even under a blueprint that doesn't. Sensitive projects on GitHub must be private repositories."
                />
                <p class="text-sm text-slate-600 dark:text-slate-400">
                    Every project warns before it uploads or publishes a file whose path contains "private", whether or not it is sensitive.
                    <Link href="/settings/hooks" preserve-state class="text-sky-600 hover:underline dark:text-sky-400">Git hooks</Link> can also check commits for secrets and for private folders git would commit.
                </p>
            </UiPanel>

            <UiPanel v-else-if="section === 'hooks'" title="Before each commit" description="For R projects for now. They need git, so they're skipped when git is off.">
                <UiSwitch
                    v-for="hook in hooks" :key="hook.key"
                    :model-value="!!form.hooks[hook.key]" :label="hook.label" :description="hook.description"
                    @update:model-value="(on) => (form.hooks = { ...form.hooks, [hook.key]: on })"
                />
                <p class="text-sm text-slate-600 dark:text-slate-400">
                    Which folders count as private comes from <Link href="/settings/data" preserve-state class="text-sky-600 hover:underline dark:text-sky-400">Sensitive data</Link>.
                </p>
            </UiPanel>

            <UiPanel v-else-if="section === 'notebooks'" title="Notebooks">
                <UiSelect v-model="form.notebook_format" label="Notebook format" :options="formatOptions" />
                <UiSwitch v-if="tool('jupyter')" :model-value="!!form.tools.jupyter" :label="tool('jupyter').name" :description="tool('jupyter').description" @update:model-value="(on) => setTool('jupyter', on)" />
            </UiPanel>

            <UiPanel v-else-if="section === 'quarto'" title="Quarto" description="Used when you don't choose a blueprint. A blueprint brings its own.">
                <template v-for="key in ['quarto', 'quarto_manuscript', 'quarto_slides']" :key="key">
                    <UiSwitch v-if="tool(key)" :model-value="!!form.tools[key]" :label="tool(key).name" :description="tool(key).description" @update:model-value="(on) => setTool(key, on)" />
                </template>
            </UiPanel>
        </form>

        <template #actions>
            <UiButton save-shortcut type="submit" form="settings-form" class="w-full" :disabled="form.processing">
                {{ form.processing ? 'Saving…' : 'Save changes' }}
            </UiButton>
            <p v-if="form.isDirty" class="text-center text-xs text-amber-600">Unsaved changes across Project defaults</p>
            <p v-else-if="saved" class="text-center text-xs text-slate-500">Saved.</p>
        </template>
    </SectionLayout>
</template>
