<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import UiButton from '@/Components/UiButton.vue';
defineProps({ language: String, blueprint: Object });
const names = { r: 'R', python: 'Python', stata: 'Stata' };
</script>
<template>
    <Head title="Setup complete" />
    <AppLayout>
        <div class="mx-auto max-w-2xl px-6 py-12">
            <PageHeader title="Setup complete" />
            <p class="mt-6 text-sm leading-6 text-slate-600 dark:text-slate-400">{{ blueprint.name }} is your default blueprint for {{ names[language] }}. New projects start with its choices. Your author details are saved in Personal data.</p>
            <div class="mt-8 flex flex-wrap gap-3"><UiButton href="/projects/create">Create a project</UiButton><UiButton variant="ghost" href="/docs/getting-started">Connect {{ names[language] }}</UiButton></div>
            <div class="mt-6"><UiButton variant="ghost" :href="`/blueprints/${blueprint.id}/edit`">Edit blueprint</UiButton><UiButton variant="ghost" href="/dashboard">Open dashboard</UiButton></div>
        </div>
    </AppLayout>
</template>
