<script setup>
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { AdjustmentsHorizontalIcon, CubeIcon, FolderIcon, PencilSquareIcon, SparklesIcon, WrenchScrewdriverIcon } from '@heroicons/vue/24/outline';
import SectionLayout from '@/Layouts/SectionLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import UiPanel from '@/Components/UiPanel.vue';
import UiButton from '@/Components/UiButton.vue';
import UiInput from '@/Components/UiInput.vue';
import UiSelect from '@/Components/UiSelect.vue';
import UiTextarea from '@/Components/UiTextarea.vue';
import ChoicesEditor from '@/Components/ChoicesEditor.vue';
import { visibilityOptions } from '@/library.js';

const props = defineProps({
    blueprint: Object, // null when creating
    start: Object, // choices to begin from: the version being edited, or ?from=
    catalog: Object,
});

const editing = computed(() => !!props.blueprint);
const quarto = props.catalog.tools.some((t) => t.key === 'quarto');

const form = useForm({
    name: props.blueprint?.name ?? '',
    description: props.blueprint?.description ?? '',
    visibility: props.blueprint?.visibility ?? 'private',
    language: props.start?.language ?? props.blueprint?.language ?? 'r',
    structure: props.start?.structure ?? 'framework/project',
    tools: { ...(props.start?.tools ?? (quarto ? { quarto: true } : {})) },
    package_sets: [...(props.start?.package_sets ?? [])],
    skill_sets: [...(props.start?.skill_sets ?? ['framework/framework'])],
    skills: [...(props.start?.skills ?? [])],
    settings: { sensitive: false, ai: true, git: true, ...(props.start?.settings ?? {}) },
    extra_yaml: props.start?.extra_yaml ?? '',
    notes: '',
});
const showAdvanced = ref(!!props.start?.extra_yaml);

const save = () => (editing.value ? form.put(`/blueprints/${props.blueprint.id}`) : form.post('/blueprints'));

const items = [
    { label: 'Project structure', anchor: 'structure', icon: FolderIcon },
    { label: 'Tools', anchor: 'tools', icon: WrenchScrewdriverIcon },
    { label: 'Packages', anchor: 'packages', icon: CubeIcon },
    { label: 'AI assistants', anchor: 'ai', icon: SparklesIcon },
    { label: 'Settings', anchor: 'settings', icon: AdjustmentsHorizontalIcon },
    { label: 'Name and share', anchor: 'name', icon: PencilSquareIcon },
];
</script>

<template>
    <Head :title="editing ? `Edit ${blueprint.name}` : 'New blueprint'" />

    <SectionLayout heading="Blueprint" :items="items">
        <PageHeader :title="editing ? `Edit ${blueprint.name}` : 'New blueprint'">
            <template #description>
                A blueprint is a saved set of choices for new projects: a project structure, tools, packages, skills, and settings.
                Saving makes a new version; projects keep the version they were made from.
            </template>
        </PageHeader>

        <form id="blueprint-form" class="mt-8 flex flex-col gap-8" @submit.prevent="save">
            <ChoicesEditor :form="form" :catalog="catalog" />

            <UiPanel id="name" title="Name and share it" class="scroll-mt-6">
                <UiInput v-model="form.name" label="Name" placeholder="e.g. R01 secondary analysis" required :error="form.errors.name" />
                <UiTextarea v-model="form.description" label="Description" :rows="2" :error="form.errors.description" />
                <UiSelect v-model="form.visibility" label="Who can see it" :options="visibilityOptions" :error="form.errors.visibility" />
                <UiInput v-if="editing" v-model="form.notes" label="What changed (optional)" placeholder="Shown in the version history" :error="form.errors.notes" />

                <div>
                    <button type="button" class="text-sm text-slate-600 hover:underline dark:text-slate-400" @click="showAdvanced = !showAdvanced">
                        {{ showAdvanced ? 'Hide' : 'Show' }} extra settings
                    </button>
                </div>
                <UiTextarea
                    v-if="showAdvanced" v-model="form.extra_yaml" code :rows="8"
                    label="Extra settings (YAML)"
                    description="Anything else for settings.yml, such as scaffold options or connections. Applied on top of the pieces above."
                    :error="form.errors.extra_yaml"
                />
            </UiPanel>
        </form>

        <template #actions>
            <UiButton save-shortcut type="submit" form="blueprint-form" class="w-full" :disabled="form.processing || !form.structure">
                {{ editing ? 'Save new version' : 'Create blueprint' }}
            </UiButton>
            <Link :href="editing ? `/blueprints/${blueprint.id}` : '/blueprints'" class="block"><UiButton variant="ghost" class="w-full">Cancel</UiButton></Link>
        </template>
    </SectionLayout>
</template>
