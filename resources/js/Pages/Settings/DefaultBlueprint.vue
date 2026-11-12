<script setup>
import { computed } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import SettingsLayout from '@/Layouts/SettingsLayout.vue';
import UiButton from '@/Components/UiButton.vue';
import UiInput from '@/Components/UiInput.vue';
import UiLink from '@/Components/UiLink.vue';
import UiRadioCards from '@/Components/UiRadioCards.vue';
import UiSection from '@/Components/UiSection.vue';
import UiSelect from '@/Components/UiSelect.vue';
import UiSeparator from '@/Components/UiSeparator.vue';
import { languageNames } from '@/library.js';

// The default language, the projects folder, and the default blueprint for each language
const props = defineProps({ language: String, projectsDir: String, defaults: Object, blueprints: Array });

const page = usePage();
const saved = computed(() => page.props.flash.status === 'defaults-updated');

const languages = ['r', 'python', 'stata'];
const workflowNames = { quarto: 'Quarto', jupyter: 'Jupyter', scripts: 'Scripts only' };

const form = useForm({ language: props.language, projects_dir: props.projectsDir ?? '', blueprints: { ...props.defaults } });
const save = () => form.patch('/settings/blueprint', { preserveScroll: true });

const languageOptions = languages.map((l) => ({ value: l, label: languageNames[l] }));
// Framework's blueprints first, then your own, each only where it takes the language
const optionsFor = (language) => props.blueprints
    .filter((b) => b.languages.includes(language))
    .map((b) => ({ value: b.id, label: b.system ? b.name : `${b.name} (yours)` }));
const chosen = (language) => props.blueprints.find((b) => b.id === form.blueprints[language]);
const summary = (language) => {
    const b = chosen(language);
    if (!b) return '';
    return [b.description, `Notebook workflow: ${workflowNames[b.workflows[language]] ?? b.workflows[language]}.`].filter(Boolean).join(' ');
};
</script>

<template>
    <Head title="Project defaults · Settings" />

    <SettingsLayout
        heading="Project defaults"
        subheading="Defaults for new projects. A new project uses the default blueprint for its language unless you choose another. The blueprint sets the structure, packages, agents, editor files, and Git."
        :dirty="form.isDirty"
    >
        <form id="settings-form" class="flex max-w-2xl flex-col gap-8" @submit.prevent="save">
            <UiSection title="Default language" description="For projects created without a language, such as from an agent.">
                <UiRadioCards v-model="form.language" name="language" legend="Default language" hide-legend :options="languageOptions" columns="grid-cols-3" />
            </UiSection>

            <UiSeparator />

            <UiSection title="Projects folder" description="Where new projects go on your computer. R, Python, and fw suggest it when you create a project. Leave blank to use the folder you're in.">
                <UiInput
                    v-model="form.projects_dir"
                    label="Path"
                    placeholder="~/code"
                    autocomplete="off"
                    optional
                    :error="form.errors.projects_dir"
                />
            </UiSection>

            <UiSeparator />

            <UiSection title="Blueprints by language" description="Each list shows Framework's blueprints and your own that take the language.">
                <div class="flex flex-col gap-6">
                    <div v-for="language in languages" :key="language" class="flex flex-col gap-2">
                        <UiSelect
                            v-model="form.blueprints[language]"
                            :label="`${languageNames[language]} projects`"
                            :options="optionsFor(language)"
                            :description="summary(language)"
                            :error="form.errors[`blueprints.${language}`]"
                        />
                        <UiLink v-if="chosen(language)" :href="chosen(language).href" variant="subtle" class="self-start text-sm">View {{ chosen(language).name }}</UiLink>
                    </div>
                </div>
            </UiSection>

            <UiSeparator />

            <p class="text-sm text-slate-500 dark:text-slate-400">
                To change anything else about new projects, <UiLink href="/blueprints">create or edit a blueprint</UiLink> and choose it here.
                <UiLink href="/welcome">Guided setup</UiLink> makes one from six questions.
            </p>
        </form>

        <template #actions>
            <UiButton save-shortcut type="submit" form="settings-form" class="w-full" :disabled="form.processing">
                {{ form.processing ? 'Saving…' : 'Save changes' }}
            </UiButton>
            <p v-if="saved && !form.isDirty" class="text-center text-xs text-slate-500 dark:text-slate-400">Saved.</p>
        </template>
    </SettingsLayout>
</template>
