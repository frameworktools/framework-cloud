<script setup>
import { computed, ref, watch } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import UiButton from '@/Components/UiButton.vue';
import UiInput from '@/Components/UiInput.vue';
import UiRadioCards from '@/Components/UiRadioCards.vue';
import UiSeparator from '@/Components/UiSeparator.vue';
import AuthorFields from '@/Components/AuthorFields.vue';
import BlueprintPreview from '@/Components/BlueprintPreview.vue';
import { languageOptions } from '@/library.js';

const props = defineProps({ blueprints: Array, author: Object, accountEmail: String, language: String, projectsDir: String, blueprintId: Number, again: Boolean, step: Number });
const steps = ['Introduction', 'Your details', 'Project defaults', 'Starting blueprint'];
const step = ref(props.step ?? 0);
const saving = ref(false);
const saveStatus = ref(props.step > 0 ? 'Saved' : '');
const page = usePage();
const form = useForm({ ...props.author, affiliations: props.author.affiliations.map(a => ({ ...a })), language: props.language ?? 'r', projects_dir: props.projectsDir ?? '', blueprint_id: String(props.blueprintId ?? props.blueprints.find(b => b.key === 'project')?.id ?? '') });
watch(() => form.data(), () => { saveStatus.value = 'Unsaved changes'; }, { deep: true });
const available = computed(() => props.blueprints.filter(b => b.languages.includes(form.language)));
const selected = computed(() => available.value.find(b => String(b.id) === form.blueprint_id));
const options = computed(() => available.value.map(b => ({ value: String(b.id), label: b.name, description: b.description })));
watch(() => form.language, () => {
    if (!available.value.some(b => String(b.id) === form.blueprint_id)) form.blueprint_id = String(available.value.find(b => b.key === 'project')?.id ?? available.value[0]?.id ?? '');
});
const advance = async () => {
    if (step.value === 3) {
        form.post('/welcome', { onError: errors => { step.value = Object.keys(errors).some(k => !['blueprint_id', 'language', 'projects_dir'].includes(k)) ? 1 : errors.language || errors.projects_dir ? 2 : 3; } });
        return;
    }
    saving.value = true;
    form.clearErrors();
    try {
        const response = await fetch('/welcome/progress', {
            method: 'PATCH', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': page.props.csrf },
            body: JSON.stringify({ ...form.data(), stage: ['introduction', 'profile', 'language'][step.value] }),
        });
        const data = await response.json();
        if (!response.ok) { form.setError(Object.fromEntries(Object.entries(data.errors ?? { progress: ['Changes could not be saved. Try again.'] }).map(([key, messages]) => [key, messages[0]]))); return; }
        saveStatus.value = 'Saved';
        step.value++;
    } catch { form.setError('progress', 'Changes could not be saved. Try again.'); }
    finally { saving.value = false; }
};
const skip = () => router.post('/welcome/skip');
</script>

<template>
    <Head title="Getting started" />
    <AppLayout>
        <div class="mx-auto w-full px-6 py-10 sm:px-8" :class="step === 3 ? 'max-w-7xl' : 'max-w-3xl'">
            <nav aria-label="Setup progress" class="mb-8 flex flex-wrap items-center gap-x-3 gap-y-2 text-sm">
                <template v-for="(label, index) in steps" :key="label">
                    <span v-if="index" aria-hidden="true" class="text-slate-400 dark:text-slate-600">/</span>
                    <button type="button" :disabled="index > step" :aria-current="index === step ? 'step' : undefined" :class="index === step ? 'font-semibold text-sky-700 dark:text-sky-400' : 'text-slate-500 disabled:opacity-50'" @click="step = index">{{ label }}</button>
                </template>
            </nav>
            <PageHeader :title="step === 0 ? 'Welcome to Framework' : steps[step]" />
            <form class="mt-8 flex flex-col gap-6" @submit.prevent="advance">
                <template v-if="step === 0">
                    <p class="text-base leading-7 text-slate-700 dark:text-slate-300">Framework connects the work on your computer with projects and published results in the cloud.</p>
                    <dl class="divide-y divide-slate-800/10 dark:divide-white/15">
                        <div class="py-5 first:pt-0"><dt class="text-base font-medium text-slate-900 dark:text-white">Blueprints</dt><dd class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-400">A blueprint holds your language, folders, notebooks, packages, Git, and AI settings. You’ll start with one and customize it for future projects.</dd></div>
                        <div class="py-5"><dt class="text-base font-medium text-slate-900 dark:text-white">Projects</dt><dd class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-400">Each project starts from a blueprint. Review its choices, then create the files on your computer. Your data and code stay there until you choose to publish.</dd></div>
                        <div class="py-5"><dt class="text-base font-medium text-slate-900 dark:text-white">Published results</dt><dd class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-400">Publish figures, tables, notebooks, and other outputs as artifacts. Combine them into collections and reports. You control their visibility.</dd></div>
                    </dl>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Setup saves your author details and a private default blueprint. You can change both later.</p>
                </template>
                <template v-else-if="step === 1">
                    <p class="text-sm text-slate-500 dark:text-slate-400">Sign-in email: {{ accountEmail }}. These author details are separate from your account login.</p>
                    <AuthorFields :form="form" />
                </template>
                <template v-else-if="step === 2">
                    <p class="text-sm text-slate-600 dark:text-slate-400">Choose the language you use most. Your default blueprint will use this language. You can add blueprints for other languages later.</p>
                    <UiRadioCards v-model="form.language" name="setup-language" legend="Default language" :options="languageOptions" stacked />
                    <p v-if="form.errors.language" role="alert" class="text-sm text-red-600">{{ form.errors.language }}</p>
                    <UiInput
                        v-model="form.projects_dir"
                        label="Projects folder"
                        description="Where new projects go on your computer. R, Python, and fw suggest it when you create a project. Leave blank to use the folder you're in."
                        placeholder="~/code"
                        autocomplete="off"
                        optional
                        :error="form.errors.projects_dir"
                    />
                </template>
                <template v-else>
                    <p class="text-sm text-slate-600 dark:text-slate-400">Choose a starting point for your typical project. You can always make more or edit this one later.</p>
                    <div class="grid min-w-0 items-start gap-8 lg:grid-cols-[minmax(18rem,0.9fr)_minmax(0,1.1fr)]">
                        <div class="min-w-0 [&_fieldset>div]:max-w-none">
                            <UiRadioCards v-model="form.blueprint_id" name="setup-blueprint" legend="Starting blueprint" hide-legend :options="options" stacked />
                            <p v-if="form.errors.blueprint_id" role="alert" class="mt-3 text-sm text-red-600">{{ form.errors.blueprint_id }}</p>
                        </div>
                        <div class="min-w-0 border-t border-slate-800/10 pt-6 lg:border-t-0 lg:border-l lg:pl-8 lg:pt-0 dark:border-white/15">
                            <BlueprintPreview v-if="selected" :key="`${selected.id}:${form.language}`" :blueprint="selected" :language="form.language" :source="{ url: `/blueprints/${selected.id}/preview`, body: { language: form.language } }" compact />
                        </div>
                    </div>
                </template>
                <p v-if="form.errors.progress" role="alert" class="text-sm text-red-600">{{ form.errors.progress }}</p>
                <p v-else-if="saveStatus" role="status" class="text-sm text-slate-500">{{ saving ? 'Saving…' : saveStatus }}</p>
                <UiSeparator />
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex gap-2"><UiButton v-if="step > 0" variant="ghost" @click="step--">Back</UiButton><UiButton variant="ghost" @click="skip">Skip setup</UiButton></div>
                    <UiButton type="submit" :disabled="form.processing || saving">{{ step === 3 ? 'Customize blueprint' : 'Continue' }}</UiButton>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
