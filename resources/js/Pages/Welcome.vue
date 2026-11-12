<script setup>
import { computed, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import UiButton from '@/Components/UiButton.vue';
import UiRadioCards from '@/Components/UiRadioCards.vue';
import UiSeparator from '@/Components/UiSeparator.vue';
import AuthorFields from '@/Components/AuthorFields.vue';
import { languageOptions } from '@/library.js';

const props = defineProps({ blueprints: Array, author: Object, accountEmail: String, language: String, blueprintId: Number, again: Boolean });
const steps = ['Introduction', 'Your details', 'Default language', 'Starting blueprint'];
const step = ref(0);
const form = useForm({ ...props.author, affiliations: props.author.affiliations.map(a => ({ ...a })), language: props.language ?? 'r', blueprint_id: String(props.blueprintId ?? props.blueprints.find(b => b.key === 'project')?.id ?? '') });
const available = computed(() => props.blueprints.filter(b => b.languages.includes(form.language)));
const options = computed(() => available.value.map(b => ({ value: String(b.id), label: b.name, description: b.description })));
watch(() => form.language, () => {
    if (!available.value.some(b => String(b.id) === form.blueprint_id)) form.blueprint_id = String(available.value.find(b => b.key === 'project')?.id ?? available.value[0]?.id ?? '');
});
const advance = () => {
    if (step.value === 1 && !form.name.trim()) { form.setError('name', 'Enter your name.'); return; }
    if (step.value < 3) { step.value++; return; }
    form.post('/welcome', { onError: errors => { step.value = Object.keys(errors).some(k => !['blueprint_id', 'language'].includes(k)) ? 1 : errors.language ? 2 : 3; } });
};
const skip = () => router.post('/welcome/skip');
</script>

<template>
    <Head title="Getting started" />
    <AppLayout>
        <div class="mx-auto w-full max-w-3xl px-6 py-10 sm:px-8">
            <nav aria-label="Setup progress" class="mb-8 flex flex-wrap gap-x-5 gap-y-2 text-sm">
                <button v-for="(label, index) in steps" :key="label" type="button" :disabled="index > step" :aria-current="index === step ? 'step' : undefined" :class="index === step ? 'font-semibold text-sky-700 dark:text-sky-400' : 'text-slate-500 disabled:opacity-50'" @click="step = index">{{ label }}</button>
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
                    <UiRadioCards v-model="form.language" name="setup-language" legend="Default language" hide-legend :options="languageOptions" stacked />
                    <p v-if="form.errors.language" role="alert" class="text-sm text-red-600">{{ form.errors.language }}</p>
                </template>
                <template v-else>
                    <p class="text-sm text-slate-600 dark:text-slate-400">Choose a starting point for your private blueprint. Next, review its folders, notebook format, packages, AI skills, and other settings.</p>
                    <UiRadioCards v-model="form.blueprint_id" name="setup-blueprint" legend="Starting blueprint" hide-legend :options="options" stacked />
                    <p v-if="form.errors.blueprint_id" role="alert" class="text-sm text-red-600">{{ form.errors.blueprint_id }}</p>
                </template>
                <UiSeparator />
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex gap-2"><UiButton v-if="step > 0" variant="ghost" @click="step--">Back</UiButton><UiButton variant="ghost" @click="skip">Skip setup</UiButton></div>
                    <UiButton type="submit" :disabled="form.processing">{{ step === 3 ? 'Customize blueprint' : 'Continue' }}</UiButton>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
