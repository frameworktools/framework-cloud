<script setup>
import { computed } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import SettingsLayout from '@/Layouts/SettingsLayout.vue';
import UiButton from '@/Components/UiButton.vue';
import UiInput from '@/Components/UiInput.vue';
import UiSelect from '@/Components/UiSelect.vue';
import UiSwitch from '@/Components/UiSwitch.vue';

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
        :subheading="`Synced to every machine where you've run framework::cloud_login(). Revision ${revision}.`"
    >
        <form class="flex flex-col gap-8" @submit.prevent="save">
            <fieldset class="flex flex-col gap-4">
                <legend class="mb-2 text-sm font-semibold">Author</legend>
                <UiInput v-model="form.author_name" label="Name" :error="form.errors.author_name" />
                <UiInput v-model="form.author_email" label="Email" type="email" :error="form.errors.author_email" />
                <UiInput v-model="form.author_affiliation" label="Affiliation" :error="form.errors.author_affiliation" />
            </fieldset>

            <fieldset class="flex flex-col gap-4">
                <legend class="mb-2 text-sm font-semibold">New projects</legend>
                <UiSelect v-model="form.project_type" label="Default project type" :options="blueprintOptions" :error="form.errors.project_type" />
                <UiSelect v-model="form.notebook_format" label="Notebook format" :options="formatOptions" />
                <UiSelect v-model="form.ide" label="IDE" :options="ideOptions" />
                <UiSwitch v-model="form.use_git" label="Initialize git" />
                <UiSwitch v-model="form.use_renv" label="Use renv" />
                <UiSwitch v-model="form.seed_on_scaffold" label="Set random seed on scaffold()" />
            </fieldset>

            <fieldset class="flex flex-col gap-4">
                <legend class="mb-2 text-sm font-semibold">AI</legend>
                <UiSwitch v-model="form.ai_enabled" label="Generate AI context files" />
                <UiInput v-model="form.ai_canonical_file" label="Canonical context file" :error="form.errors.ai_canonical_file" />
                <UiSwitch v-model="form.ai_skills" label="Install Framework skills" />
            </fieldset>

            <div class="flex items-center gap-3">
                <UiButton type="submit" :disabled="form.processing">Save</UiButton>
                <span v-if="saved" class="text-sm text-neutral-500">Saved.</span>
            </div>
        </form>
    </SettingsLayout>
</template>
