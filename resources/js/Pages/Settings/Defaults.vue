<script setup>
import { computed } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import SettingsLayout from '@/Layouts/SettingsLayout.vue';
import UiButton from '@/Components/UiButton.vue';
import UiInput from '@/Components/UiInput.vue';
import UiSelect from '@/Components/UiSelect.vue';
import UiSwitch from '@/Components/UiSwitch.vue';
import UiPanel from '@/Components/UiPanel.vue';

const props = defineProps({ revision: Number, blueprints: Array, values: Object });

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

const form = useForm({ ...props.values });
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
                <UiSelect v-model="form.project_type" label="Default project type" :options="blueprintOptions" :error="form.errors.project_type" />
                <UiSelect v-model="form.notebook_format" label="Notebook format" :options="formatOptions" />
                <UiSelect v-model="form.ide" label="IDE" :options="ideOptions" />
                <UiSwitch v-model="form.use_git" label="Initialize git" />
                <UiSwitch v-model="form.use_renv" label="Use renv" />
                <UiSwitch v-model="form.seed_on_scaffold" label="Set random seed on scaffold()" />
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
