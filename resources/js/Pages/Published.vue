<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import UiButton from '@/Components/UiButton.vue';
import UiCallout from '@/Components/UiCallout.vue';

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
        <div class="flex flex-col gap-6">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Published</h1>
                <p class="mt-1 max-w-2xl text-slate-600 dark:text-slate-400">
                    Notebooks and reports you've published from your projects. Anyone with a link can view them.
                    Each publish adds a version, and earlier versions stay available until you delete them.
                </p>
            </div>

            <UiCallout v-if="!canPublish" heading="Publishing is part of a paid plan">
                Publishing to Framework Cloud gives each notebook a link you can share, and we host it. Your plan doesn't include it yet.
                You can still publish to your own S3 or R2 bucket from R with a connection, and nothing goes through us.
            </UiCallout>

            <div v-if="documents.length" class="flex flex-col divide-y divide-slate-200 rounded-lg border border-slate-200 dark:divide-slate-800 dark:border-slate-800">
                <div v-for="doc in documents" :key="doc.id" class="p-4">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div class="min-w-0 flex-1">
                            <a :href="doc.url" target="_blank" class="font-medium hover:underline">{{ doc.title }}</a>
                            <div class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-slate-500 dark:text-slate-400">
                                <span>{{ doc.project.name }}</span>
                                <span aria-hidden="true">·</span>
                                <span>{{ doc.updated_at !== doc.published_at ? `Updated ${date(doc.updated_at)}` : `Published ${date(doc.published_at)}` }}</span>
                                <span aria-hidden="true">·</span>
                                <button type="button" class="hover:text-slate-900 hover:underline dark:hover:text-slate-100" :aria-expanded="open === doc.id" @click="toggleVersions(doc)">
                                    {{ doc.versions.length }} {{ doc.versions.length === 1 ? 'version' : 'versions' }}
                                </button>
                                <span aria-hidden="true">·</span>
                                <span>{{ doc.views }} {{ doc.views === 1 ? 'view' : 'views' }}</span>
                                <span aria-hidden="true">·</span>
                                <span>{{ size(doc.size_bytes) }}</span>
                            </div>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <a :href="doc.url" target="_blank"><UiButton variant="outline" size="sm">Open</UiButton></a>
                            <UiButton variant="ghost" size="sm" @click="destroy(doc)">Take down</UiButton>
                        </div>
                    </div>

                    <dl class="mt-3 flex flex-col gap-2 text-sm">
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                            <dt class="w-24 shrink-0 text-slate-500">Private link</dt>
                            <dd class="min-w-0 flex-1 truncate font-mono text-xs">{{ doc.private_url }}</dd>
                            <UiButton variant="ghost" size="sm" @click="copy(`p${doc.id}`, doc.private_url)">{{ copied === `p${doc.id}` ? 'Copied' : 'Copy' }}</UiButton>
                        </div>
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                            <dt class="w-24 shrink-0 text-slate-500">Readable link</dt>
                            <dd v-if="doc.readable_url" class="min-w-0 flex-1 truncate font-mono text-xs">{{ doc.readable_url }}</dd>
                            <dd v-else-if="doc.readable && !handle" class="min-w-0 flex-1 text-slate-500">
                                On, but you need a handle. <Link href="/settings/account" class="text-sky-600 hover:underline dark:text-sky-400">Set one</Link>.
                            </dd>
                            <dd v-else class="min-w-0 flex-1 text-slate-500">Off. Only the private link works.</dd>
                            <UiButton v-if="doc.readable_url" variant="ghost" size="sm" @click="copy(`r${doc.id}`, doc.readable_url)">{{ copied === `r${doc.id}` ? 'Copied' : 'Copy' }}</UiButton>
                            <UiButton variant="outline" size="sm" @click="setReadable(doc, !doc.readable)">{{ doc.readable ? 'Turn off' : 'Turn on' }}</UiButton>
                        </div>
                    </dl>

                    <ol v-if="open === doc.id" class="mt-3 divide-y divide-slate-200 rounded-md border border-slate-200 text-sm dark:divide-slate-800 dark:border-slate-800">
                        <li v-for="(version, i) in doc.versions" :key="version.number" class="flex items-center justify-between gap-3 px-3 py-2">
                            <a :href="version.url" target="_blank" class="hover:underline">
                                Version {{ version.number }}<span v-if="i === 0" class="text-slate-500"> (current)</span>
                            </a>
                            <span class="flex-1 text-slate-500">{{ date(version.created_at) }} · {{ size(version.size_bytes) }}</span>
                            <UiButton variant="ghost" size="sm" @click="destroyVersion(doc, version)">Delete</UiButton>
                        </li>
                    </ol>
                </div>
            </div>

            <div v-else-if="canPublish" class="flex flex-col gap-3 rounded-lg border border-dashed border-slate-300 p-8 dark:border-slate-700">
                <p class="font-medium">Nothing published yet.</p>
                <p class="max-w-xl text-sm text-slate-600 dark:text-slate-400">
                    In a project set up from Framework Cloud, render and publish a notebook from R. It shows up here with a private link.
                    Give it a slug to also get a readable link.
                </p>
                <pre class="w-fit rounded-md bg-slate-100 px-3 py-2 font-mono text-xs text-slate-800 dark:bg-slate-900 dark:text-slate-200">framework::publish("notebooks/analysis.qmd", slug = "analysis")</pre>
            </div>
        </div>
    </AppLayout>
</template>
