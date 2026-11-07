<script setup>
import { computed } from 'vue';
import UiCheckboxCards from '@/Components/UiCheckboxCards.vue';
import { agentOptions, pick } from '@/agents.js';
import UiPanel from '@/Components/UiPanel.vue';
import UiSwitch from '@/Components/UiSwitch.vue';
import UiSelect from '@/Components/UiSelect.vue';
import LayerSummary from '@/Components/LayerSummary.vue';
import { languageNames, typeFits } from '@/library.js';

// The pieces of a project, chosen one by one: a structure, tools, package
// sets, skills, and settings. Edits the given form's fields in place.
const props = defineProps({
    form: Object, // structure, language, tools, package_sets, skill_sets, skills, settings
    catalog: Object,
    languages: { type: Array, default: () => ['r', 'python', 'stata'] },
    required: { type: Object, default: () => ({}) }, // tool key → why it can't be turned off
    // A project's agents, with the shared selector (form.agents); a blueprint only turns setup on or off
    agentPicker: Boolean,
});

const f = props.form;
// None stands alone; skills stay as they are while it's selected
const agentModel = computed({ get: () => f.agents, set: (picked) => (f.agents = pick(f.agents, picked)) });
const agentsOn = computed(() => (props.agentPicker ? !f.agents.includes('none') : f.settings.ai));
// Tools that can't be turned off here, with why, such as a workflow that needs them
const required = (tool) => props.required?.[tool.key] ?? null;
const fits = (piece) => !piece.language || piece.language.includes(f.language);

const structures = computed(() => props.catalog.structures.filter((s) => typeFits(s.type, f.language)));
const tools = computed(() => (f.language === 'stata' ? [] : props.catalog.tools.filter(fits)));
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

const skillOptions = computed(() => [
    { value: '', label: 'Add a single skill…' },
    ...props.catalog.skills.filter((s) => !f.skills.includes(s.key)).map((s) => ({ value: s.key, label: s.system ? s.name : `${s.name} (${s.key})` })),
]);
const addSkill = (key) => key && (f.skills = [...f.skills, key]);
const skillName = (key) => props.catalog.skills.find((s) => s.key === key)?.name ?? key;

const languageOptions = computed(() => props.languages.map((value) => ({ value, label: languageNames[value] })));
const choiceClass = (on) => on
    ? 'border-sky-500 bg-sky-50 ring-1 ring-sky-500 dark:bg-sky-950/40'
    : 'border-slate-200 bg-white hover:border-slate-400 dark:border-slate-700 dark:bg-slate-900 dark:hover:border-slate-500';
</script>

<template>
    <UiPanel id="structure" title="Project structure" description="The folders and core files the project starts with." class="scroll-mt-6">
        <UiSelect v-if="languages.length > 1" v-model="form.language" label="Language" :options="languageOptions" :error="form.errors.language" />
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
            <div v-if="structure" class="rounded-md bg-white p-3 dark:bg-slate-900"><LayerSummary :sections="structure.summary" /></div>
        </slot>
        <p v-if="form.errors.structure" class="text-xs text-red-600 dark:text-red-400">{{ form.errors.structure }}</p>
    </UiPanel>

    <slot name="after-structure" />

    <UiPanel id="tools" title="Tools" class="scroll-mt-6" :description="form.language === 'stata' ? 'Stata projects have no tools.' : 'Optional tools. Each adds its own files, folders, and packages.'">
        <div v-if="tools.length" class="flex flex-col divide-y divide-slate-200 rounded-lg border border-slate-200 bg-white dark:divide-slate-700 dark:border-slate-700 dark:bg-slate-900">
            <div v-for="tool in tools" :key="tool.key" class="p-3">
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

    <UiPanel id="packages" title="Packages" class="scroll-mt-6" description="Package sets the project installs. Sets stack, so turn on as many as you need. A package in more than one set installs once.">
        <div v-if="packageSets.length" class="flex flex-col divide-y divide-slate-200 rounded-lg border border-slate-200 bg-white dark:divide-slate-700 dark:border-slate-700 dark:bg-slate-900">
            <div v-for="set in packageSets" :key="set.key" class="p-3">
                <UiSwitch :model-value="form.package_sets.includes(set.key)" :label="set.name" :description="set.description" @update:model-value="(on) => toggle('package_sets', set.key, on)" />
                <div v-if="!set.system" class="mt-1 font-mono text-xs text-slate-400">{{ set.key }}</div>
            </div>
        </div>
        <p v-else class="text-sm text-slate-500 dark:text-slate-400">No package sets for {{ languageNames[form.language] }} yet.</p>
        <p v-if="stacked.sets > 1" class="text-sm text-slate-700 dark:text-slate-300">
            {{ stacked.packages }} packages from {{ stacked.sets }} sets<template v-if="stacked.shared">, with {{ stacked.shared }} in more than one set</template>.
        </p>
        <p class="text-sm text-slate-500 dark:text-slate-400"><Link href="/package-sets/create" class="text-sky-700 hover:underline dark:text-sky-400">Make a package set</Link></p>
        <p v-if="form.errors.package_sets" class="text-xs text-red-600 dark:text-red-400">{{ form.errors.package_sets }}</p>
    </UiPanel>

    <UiPanel id="ai" title="Agents" class="scroll-mt-6" :description="agentPicker ? null : 'Skills provide project instructions for coding agents. Agent selection is a personal setting.'">
        <UiCheckboxCards v-if="agentPicker" v-model="agentModel" legend="Agents" hide-legend :options="agentOptions" />
        <UiSwitch v-else v-model="form.settings.ai" label="Agent files" description="Adds AGENTS.md and the selected skills." />
        <template v-if="agentsOn || agentPicker">
            <p v-if="!agentsOn" class="text-xs text-slate-500 dark:text-slate-400">With None, the project gets no skills. These are kept if you choose an agent again.</p>
            <fieldset :disabled="!agentsOn" class="flex flex-col gap-3" :class="agentsOn ? '' : 'opacity-60'">
            <div v-if="skillSets.length" class="flex flex-col divide-y divide-slate-200 rounded-lg border border-slate-200 bg-white dark:divide-slate-700 dark:border-slate-700 dark:bg-slate-900">
                <div v-for="set in skillSets" :key="set.key" class="p-3">
                    <UiSwitch :model-value="form.skill_sets.includes(set.key)" :label="set.name" :description="set.description" @update:model-value="(on) => toggle('skill_sets', set.key, on)" />
                </div>
            </div>
            <div v-if="form.skills.length" class="flex flex-col gap-1">
                <div v-for="key in form.skills" :key="key" class="flex items-center justify-between gap-2 rounded-md bg-white px-3 py-2 text-sm dark:bg-slate-900">
                    <span>{{ skillName(key) }}</span>
                    <button type="button" class="text-xs text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100" @click="toggle('skills', key, false)">Remove</button>
                </div>
            </div>
            <UiSelect model-value="" sr-label="Add a skill" :options="skillOptions" @update:model-value="addSkill" />
            </fieldset>
        </template>
        <p v-if="form.errors.skills || form.errors.skill_sets" class="text-xs text-red-600 dark:text-red-400">{{ form.errors.skills || form.errors.skill_sets }}</p>
    </UiPanel>

    <UiPanel id="settings" title="Settings" class="scroll-mt-6">
        <UiSwitch
            v-model="form.settings.sensitive" label="Sensitive data"
            :description="structure?.sensitive ? 'This structure already keeps private and public folders apart.' : 'Splits data and outputs into private and public folders, keeps private files out of git, and warns before anything private is published.'"
        />
        <UiSwitch v-model="form.settings.git" label="Use git" />
    </UiPanel>
</template>
