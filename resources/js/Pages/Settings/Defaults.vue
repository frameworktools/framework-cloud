<script setup>
import { computed } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import SettingsLayout from '@/Layouts/SettingsLayout.vue';
import UiButton from '@/Components/UiButton.vue';
import UiInput from '@/Components/UiInput.vue';
import UiSelect from '@/Components/UiSelect.vue';
import UiSwitch from '@/Components/UiSwitch.vue';
import UiPanel from '@/Components/UiPanel.vue';

const props = defineProps({ revision: Number, blueprints: Array, catalog: Object, values: Object });

const page = usePage();
const saved = computed(() => page.props.flash.status === 'defaults-updated');

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

const form = useForm({ ...props.values, tools: { ...props.values.tools } });
const rTools = computed(() => props.catalog.tools.filter((t) => t.key !== 'renv' && (!t.language || t.language.includes('r'))));
const toggle = (list, key, on) => {
    const next = form[list].filter((k) => k !== key);
    form[list] = on ? [...next, key] : next;
};
const save = () => form.patch('/settings/defaults');
</script>

<template>
    <Head title="Framework defaults" />

    <SettingsLayout
        heading="Framework defaults"
        :subheading="`Synced to every computer where you've run cloud_login() in R or Python. Revision ${revision}.`"
    >
        <form id="settings-form" class="flex flex-col gap-8" @submit.prevent="save">
            <UiPanel title="New projects" description="What a new project starts with when you don't choose otherwise.">
                <UiSelect v-model="form.project_type" label="Default blueprint" :options="blueprintOptions" :error="form.errors.project_type" />
                <UiSelect v-model="form.notebook_format" label="Notebook format" :options="formatOptions" />
                <UiSelect v-model="form.ide" label="IDE" :options="ideOptions" />
                <UiSwitch v-model="form.use_git" label="Initialize git" />
                <UiSwitch v-model="form.use_renv" label="Use renv" />
                <UiSwitch v-model="form.seed_on_scaffold" label="Set random seed on scaffold()" />
            </UiPanel>

            <UiPanel title="Preferred pieces" description="Added to new projects that don't start from a chosen blueprint. A chosen blueprint brings its own.">
                <div class="flex flex-col gap-2">
                    <div class="text-sm font-medium text-slate-900 dark:text-white">Tools</div>
                    <UiSwitch v-for="tool in rTools" :key="tool.key" :model-value="!!form.tools[tool.key]" :label="tool.name" :description="tool.description" @update:model-value="(on) => (form.tools = { ...form.tools, [tool.key]: on })" />
                </div>
                <div v-if="catalog.package_sets.length" class="flex flex-col gap-2">
                    <div class="text-sm font-medium text-slate-900 dark:text-white">Package sets</div>
                    <UiSwitch v-for="set in catalog.package_sets" :key="set.key" :model-value="form.package_sets.includes(set.key)" :label="set.name" :description="set.description" @update:model-value="(on) => toggle('package_sets', set.key, on)" />
                </div>
                <div v-if="catalog.skill_sets.length" class="flex flex-col gap-2">
                    <div class="text-sm font-medium text-slate-900 dark:text-white">Skill sets</div>
                    <UiSwitch v-for="set in catalog.skill_sets" :key="set.key" :model-value="form.skill_sets.includes(set.key)" :label="set.name" :description="set.description" @update:model-value="(on) => toggle('skill_sets', set.key, on)" />
                </div>
                <UiSwitch v-model="form.sensitive" label="Sensitive data" description="Start every new project with private and public folders, even under a blueprint that doesn't set it." />
            </UiPanel>

            <UiPanel title="AI assistants" description="Context files and skills that new projects get for AI coding assistants.">
                <UiSwitch v-model="form.ai_enabled" label="Generate AI context files" />
                <UiInput v-model="form.ai_canonical_file" label="Canonical context file" :error="form.errors.ai_canonical_file" />
                <UiSwitch v-model="form.ai_skills" label="Install Framework skills" />
            </UiPanel>
        </form>

        <template #actions>
            <UiButton save-shortcut type="submit" form="settings-form" class="w-full" :disabled="form.processing">
                {{ form.processing ? 'Saving…' : 'Save changes' }}
            </UiButton>
            <p v-if="saved" class="text-center text-xs text-slate-500">Saved.</p>
        </template>
    </SettingsLayout>
</template>
