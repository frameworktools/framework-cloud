<script setup>
import RowMenu from '@/Components/RowMenu.vue';
import ShareDialog from '@/Components/ShareDialog.vue';
import { ask } from '@/confirm.js';
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import ArtifactUpload from '@/Components/ArtifactUpload.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import UiButton from '@/Components/UiButton.vue';
import UiTable from '@/Components/UiTable.vue';
import EmptyState from '@/Components/EmptyState.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { artifactKind } from '@/report-artifacts.js';
import { LockClosedIcon, MagnifyingGlassIcon } from '@heroicons/vue/20/solid';
import { GlobeAltIcon } from '@heroicons/vue/24/outline';
import { inputClass, selectClass } from '@/ui.js';

const props = defineProps({ documents: Array, projects: { type: Array, default: () => [] }, canPublish: Boolean, handle: String, storage: Object });
const uploading = ref(false);
const query = ref('');
const project = ref('');
const shown = computed(() => {
    const words = query.value.trim().toLowerCase().split(/\s+/).filter(Boolean);
    return props.documents.filter(doc => (!project.value || doc.project.id === Number(project.value) || (doc.linked_projects ?? []).some(p => p.id === Number(project.value)))
        && words.every(word => `${doc.title} ${doc.slug} ${artifactKind(doc.kind).label} ${doc.project.name} ${(doc.linked_projects ?? []).map(p => p.name).join(' ')}`.toLowerCase().includes(word)));
});
const date = (value) => new Date(value).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });

const shareTarget = ref(null);
const removeArtifact = async (doc) => {
    if (await ask(`Delete ${doc.title}? It goes to the Trash, where you can restore it.`)) {
        router.delete(`/published/${doc.id}`, { preserveScroll: true });
    }
};
const menuFor = (doc) => [
    { label: 'Edit', href: `/artifacts/${doc.id}` },
    { label: 'Share', onSelect: () => { shareTarget.value = { type: 'artifact', id: doc.id, title: doc.title, settings: `/artifacts/${doc.id}?tab=sharing` }; } },
    { divider: true },
    { label: 'Delete', danger: true, onSelect: () => removeArtifact(doc) },
];
</script>

<template>
    <Head title="Artifacts" />

    <AppLayout>
        <div class="flex flex-col gap-8">
            <PageHeader title="Artifacts" description="Published artifacts across projects. Each has a reader page and version history.">
                <template #actions><UiButton v-if="canPublish" :variant="uploading ? 'ghost' : 'primary'" @click="uploading = !uploading">{{ uploading ? 'Close' : 'Publish an artifact' }}</UiButton></template>
            </PageHeader>

            <p v-if="storage?.limit" class="text-xs text-slate-500 dark:text-slate-400">Artifact storage: <span class="font-mono">{{ (storage.used / 1048576).toFixed(1) }} MB</span> of <span class="font-mono">{{ (storage.limit / 1073741824).toFixed(1) }} GB</span>. Versions share this limit.</p>

            <ArtifactUpload v-if="canPublish && uploading" :projects="projects" @published="uploading = false" />

            <template v-if="documents.length">
                <div class="flex flex-wrap gap-3">
                    <label class="relative min-w-0 flex-1 basis-64 sm:max-w-sm">
                        <span class="sr-only">Search</span>
                        <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                        <input v-model="query" type="search" placeholder="Search by name, type, or project" :class="[inputClass, 'pl-9']">
                    </label>
                    <label v-if="projects.length > 1" class="w-full sm:w-56"><span class="sr-only">Project</span>
                        <select v-model="project" :class="selectClass"><option value="">All projects</option><option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option></select>
                    </label>
                </div>

                <UiTable label="Published artifacts" class="artifact-results">
                    <thead>
                        <tr><th>Name</th><th class="max-sm:hidden">Type</th><th class="max-lg:hidden">Location</th><th class="max-2xl:hidden">Sharing</th><th class="numeric max-2xl:hidden">Views</th><th class="max-sm:hidden">Updated</th><th class="actions"><span class="sr-only">Actions</span></th></tr>
                    </thead>
                    <tbody>
                        <tr v-for="doc in shown" :key="doc.id">
                            <td class="strong max-w-72 truncate">
                                <span class="flex items-center gap-2.5">
                                    <component :is="artifactKind(doc.kind).icon" class="size-4 shrink-0 text-slate-400" aria-hidden="true" />
                                    <Link :href="`/artifacts/${doc.id}`" class="truncate py-0.5 hover:underline hover:underline-offset-4">{{ doc.title }}</Link>
                                    <span v-if="doc.merged_into_id" class="rounded-md bg-slate-800/5 px-1.5 text-xs font-normal text-slate-500 dark:text-slate-400 dark:bg-white/10">Merged</span>
                                </span>
                                <span class="mt-1 block truncate text-xs font-normal text-slate-500 dark:text-slate-400">
                                    <Link v-if="!doc.project.is_home" :href="`/projects/${doc.project.slug}/published`" class="inline-block py-1 hover:underline">{{ doc.project.name }}</Link>
                                    <template v-else-if="!doc.linked_projects?.length">No project</template>
                                    <template v-for="(linked, index) in doc.linked_projects ?? []" :key="linked.id"><template v-if="index || !doc.project.is_home">, </template><Link :href="`/projects/${linked.slug}/published`" class="inline-block py-1 hover:underline">{{ linked.name }}</Link></template>
                                </span>
                            </td>
                            <td class="max-sm:hidden">{{ artifactKind(doc.kind).label }}</td><td class="max-lg:hidden">{{ doc.location ?? 'Framework Cloud' }}</td>
                            <td class="max-2xl:hidden"><span class="inline-flex items-center gap-1.5">{{ doc.visibility === 'private' ? 'Private' : doc.visibility === 'public' ? 'Public' : 'Anyone with the link' }}<LockClosedIcon v-if="doc.password_protected" class="size-3.5" aria-label="Password protected" /></span></td>
                            <td class="numeric max-2xl:hidden">{{ !doc.driver || doc.driver === 'cloud' ? doc.views.toLocaleString() : '—' }}</td>
                            <td class="max-sm:hidden">{{ date(doc.updated_at) }}</td>
                            <td class="actions">
                                <div class="flex justify-end gap-1">
                                    <UiButton v-if="!doc.driver || doc.driver === 'cloud' || doc.external_url" :href="doc.url" external variant="ghost" size="sm" :aria-label="`View ${doc.title}`">View</UiButton>
                                    <UiButton :href="`/artifacts/${doc.id}`" variant="ghost" size="sm">Edit</UiButton>
                                    <RowMenu :items="menuFor(doc)" :label="`Actions for ${doc.title}`" />
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </UiTable>
                <ShareDialog :target="shareTarget" @close="shareTarget = null" />
                <p v-if="!shown.length" class="text-sm text-slate-500 dark:text-slate-400">Nothing matches that search.</p>
            </template>

            <EmptyState v-else-if="canPublish" :icon="GlobeAltIcon" title="No published artifacts">
                Publish a file from your browser, R, Python, or the CLI. Start with a single figure, a data file, or a complete rendered report.
                <template #actions>
                    <UiButton v-if="!projects.length" href="/projects/create">Create a project</UiButton>
                </template>
            </EmptyState>
        </div>
    </AppLayout>
</template>

<style scoped>
@media (max-width: 639px) {
    .artifact-results :deep(table) { width: 100%; table-layout: fixed; }
    .artifact-results :deep(th:last-child) { width: 8rem; }
}
</style>
