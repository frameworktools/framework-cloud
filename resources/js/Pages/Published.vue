<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import UiButton from '@/Components/UiButton.vue';
import UiCallout from '@/Components/UiCallout.vue';

defineProps({ documents: Array, canPublish: Boolean });

const copied = ref(null);
const copy = async (doc) => {
    await navigator.clipboard.writeText(doc.url);
    copied.value = doc.id;
    setTimeout(() => (copied.value = null), 1500);
};
const destroy = (doc) => {
    if (confirm(`Take down "${doc.title}"? The link stops working for everyone.`)) {
        router.delete(`/published/${doc.id}`, { preserveScroll: true });
    }
};

const size = (bytes) => {
    const units = ['B', 'KB', 'MB', 'GB'];
    let i = 0;
    while (bytes >= 1024 && i < units.length - 1) { bytes /= 1024; i++; }
    return `${bytes.toFixed(i ? 1 : 0)} ${units[i]}`;
};
const date = (value) => new Date(value).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
</script>

<template>
    <Head title="Published" />

    <AppLayout>
        <div class="flex flex-col gap-6">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Published</h1>
                <p class="mt-1 max-w-2xl text-neutral-600 dark:text-neutral-400">
                    Notebooks and reports you've published from your projects. Anyone with a link can view it.
                </p>
            </div>

            <UiCallout v-if="!canPublish" heading="Publishing is part of a paid plan">
                Publishing to Framework Cloud gives each notebook a link you can share, and we host it. Your plan doesn't include it yet.
                You can still publish to your own S3 or R2 bucket from R with a connection, and nothing goes through us.
            </UiCallout>

            <div v-if="documents.length" class="flex flex-col divide-y divide-neutral-200 rounded-lg border border-neutral-200 dark:divide-neutral-800 dark:border-neutral-800">
                <div v-for="doc in documents" :key="doc.id" class="flex flex-wrap items-center justify-between gap-4 p-4">
                    <div class="min-w-0 flex-1">
                        <a :href="doc.url" target="_blank" class="font-medium hover:underline">{{ doc.title }}</a>
                        <div class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-neutral-500 dark:text-neutral-400">
                            <span>{{ doc.project.name }}</span>
                            <span aria-hidden="true">·</span>
                            <span>{{ doc.updated_at !== doc.published_at ? `Updated ${date(doc.updated_at)}` : `Published ${date(doc.published_at)}` }}</span>
                            <span aria-hidden="true">·</span>
                            <span>{{ doc.views }} {{ doc.views === 1 ? 'view' : 'views' }}</span>
                            <span aria-hidden="true">·</span>
                            <span>{{ size(doc.size_bytes) }}</span>
                        </div>
                        <div class="mt-1 truncate font-mono text-xs text-neutral-500">{{ doc.url }}</div>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <UiButton variant="outline" size="sm" @click="copy(doc)">{{ copied === doc.id ? 'Copied' : 'Copy link' }}</UiButton>
                        <a :href="doc.url" target="_blank"><UiButton variant="outline" size="sm">Open</UiButton></a>
                        <UiButton variant="ghost" size="sm" @click="destroy(doc)">Take down</UiButton>
                    </div>
                </div>
            </div>

            <div v-else-if="canPublish" class="flex flex-col gap-3 rounded-lg border border-dashed border-neutral-300 p-8 dark:border-neutral-700">
                <p class="font-medium">Nothing published yet.</p>
                <p class="max-w-xl text-sm text-neutral-600 dark:text-neutral-400">
                    In a project set up from Framework Cloud, render and publish a notebook from R. It shows up here with a link you can share.
                </p>
                <pre class="w-fit rounded-md bg-neutral-100 px-3 py-2 font-mono text-xs text-neutral-800 dark:bg-neutral-900 dark:text-neutral-200">framework::publish_notebook("notebooks/analysis.qmd")</pre>
            </div>
        </div>
    </AppLayout>
</template>
