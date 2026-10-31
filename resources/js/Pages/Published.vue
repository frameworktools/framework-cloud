<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import UiButton from '@/Components/UiButton.vue';
import UiCallout from '@/Components/UiCallout.vue';
import UiCard from '@/Components/UiCard.vue';
import UiBadge from '@/Components/UiBadge.vue';
import CodeChip from '@/Components/CodeChip.vue';
import EmptyState from '@/Components/EmptyState.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { ArrowTopRightOnSquareIcon, ChevronRightIcon, DocumentTextIcon, GlobeAltIcon } from '@heroicons/vue/24/outline';

defineProps({ documents: Array, canPublish: Boolean, handle: String });

const copied = ref(null);
const copy = async (key, url) => {
    await navigator.clipboard.writeText(url);
    copied.value = key;
    setTimeout(() => (copied.value = null), 1500);
};
const open = ref(null);
const toggleVersions = (doc) => (open.value = open.value === doc.id ? null : doc.id);
const setReadable = (doc, readable) => router.patch(`/published/${doc.id}`, { readable }, { preserveScroll: true });
const destroyVersion = (doc, version) => {
    const last = doc.versions.length === 1;
    if (confirm(last
        ? `Delete version ${version.number}? It is the only version, so "${doc.title}" is taken down.`
        : `Delete version ${version.number} of "${doc.title}"? Its links stop working.`)) {
        router.delete(`/published/${doc.id}/versions/${version.number}`, { preserveScroll: true });
    }
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
        <div class="flex flex-col gap-8">
            <PageHeader title="Published">
                <template #description>
                    Notebooks and reports you've published from your projects. Anyone with a link can view them.
                    Each publish adds a version, and earlier versions stay available until you delete them.
                </template>
            </PageHeader>

            <UiCallout v-if="!canPublish" heading="Publishing is part of a paid plan">
                Publishing to Framework Cloud gives each notebook a link you can share, and we host it. Your plan doesn't include it yet.
                You can still publish to your own S3 or R2 bucket from R with a connection, and nothing goes through us.
            </UiCallout>

            <div v-if="documents.length" class="flex flex-col gap-3">
                <UiCard v-for="doc in documents" :key="doc.id" hover>
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div class="flex min-w-0 flex-1 items-start gap-4">
                            <div class="hidden size-10 shrink-0 items-center justify-center rounded-lg sm:flex bg-sky-50 text-sky-600 dark:bg-sky-400/10 dark:text-sky-400">
                                <DocumentTextIcon class="size-5" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <a :href="doc.url" target="_blank" class="text-base font-semibold text-slate-900 hover:underline dark:text-white">{{ doc.title }}</a>
                                <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-slate-500 dark:text-slate-400">
                                    <UiBadge>{{ doc.project.name }}</UiBadge>
                                    <span>{{ doc.updated_at !== doc.published_at ? `Updated ${date(doc.updated_at)}` : `Published ${date(doc.published_at)}` }}</span>
                                    <span aria-hidden="true">·</span>
                                    <span>{{ doc.views }} {{ doc.views === 1 ? 'view' : 'views' }}</span>
                                    <span aria-hidden="true">·</span>
                                    <span>{{ size(doc.size_bytes) }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <a :href="doc.url" target="_blank"><UiButton variant="outline" size="sm"><ArrowTopRightOnSquareIcon class="size-3.5" />Open</UiButton></a>
                            <UiButton variant="ghost" size="sm" @click="destroy(doc)">Take down</UiButton>
                        </div>
                    </div>

                    <dl class="mt-4 flex flex-col divide-y divide-slate-200 rounded-md bg-slate-50 text-sm sm:ml-14 dark:divide-slate-800 dark:bg-slate-800/50">
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 px-3 py-2">
                            <dt class="w-full shrink-0 sm:w-28 font-medium text-slate-600 dark:text-slate-400">Private link</dt>
                            <dd class="min-w-0 flex-1 truncate font-mono text-xs text-slate-800 dark:text-slate-200">{{ doc.private_url }}</dd>
                            <UiButton variant="ghost" size="sm" @click="copy(`p${doc.id}`, doc.private_url)">{{ copied === `p${doc.id}` ? 'Copied' : 'Copy' }}</UiButton>
                        </div>
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 px-3 py-2">
                            <dt class="w-full shrink-0 sm:w-28 font-medium text-slate-600 dark:text-slate-400">Readable link</dt>
                            <dd v-if="doc.readable_url" class="min-w-0 flex-1 truncate font-mono text-xs text-slate-800 dark:text-slate-200">{{ doc.readable_url }}</dd>
                            <dd v-else-if="doc.readable && !handle" class="min-w-0 flex-1 text-slate-500">
                                On, but you need a handle. <Link href="/settings/account" class="text-sky-600 hover:underline dark:text-sky-400">Set one</Link>.
                            </dd>
                            <dd v-else class="min-w-0 flex-1 text-slate-500">Off. Only the private link works.</dd>
                            <UiButton v-if="doc.readable_url" variant="ghost" size="sm" @click="copy(`r${doc.id}`, doc.readable_url)">{{ copied === `r${doc.id}` ? 'Copied' : 'Copy' }}</UiButton>
                            <UiButton variant="outline" size="sm" @click="setReadable(doc, !doc.readable)">{{ doc.readable ? 'Turn off' : 'Turn on' }}</UiButton>
                        </div>
                    </dl>

                    <div class="mt-3 sm:ml-14">
                        <button
                            type="button"
                            class="inline-flex items-center gap-1 text-xs text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200"
                            :aria-expanded="open === doc.id" @click="toggleVersions(doc)"
                        >
                            <ChevronRightIcon class="size-3 transition-transform" :class="open === doc.id ? 'rotate-90' : ''" />
                            {{ doc.versions.length }} {{ doc.versions.length === 1 ? 'version' : 'versions' }}
                        </button>
                        <ol v-if="open === doc.id" class="mt-2 divide-y divide-slate-200 rounded-md border border-slate-200 text-sm dark:divide-slate-800 dark:border-slate-800">
                            <li v-for="(version, i) in doc.versions" :key="version.number" class="flex items-center justify-between gap-3 px-3 py-2">
                                <a :href="version.url" target="_blank" class="font-medium hover:underline">
                                    Version {{ version.number }}<span v-if="i === 0" class="font-normal text-slate-500"> (current)</span>
                                </a>
                                <span class="flex-1 text-slate-500">{{ date(version.created_at) }} · {{ size(version.size_bytes) }}</span>
                                <UiButton variant="ghost" size="sm" @click="destroyVersion(doc, version)">Delete</UiButton>
                            </li>
                        </ol>
                    </div>
                </UiCard>
            </div>

            <EmptyState v-else-if="canPublish" :icon="GlobeAltIcon" title="Nothing published yet.">
                In a project set up from Framework Cloud, render and publish a notebook from R. It shows up here with a private link.
                Give it a slug to also get a readable link.
                <template #actions>
                    <CodeChip>framework::publish("notebooks/analysis.qmd", slug = "analysis")</CodeChip>
                </template>
            </EmptyState>
        </div>
    </AppLayout>
</template>
