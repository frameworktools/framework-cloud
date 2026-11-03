<script setup>
import { computed, ref } from 'vue';
import ArtifactUpload from '@/Components/ArtifactUpload.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import UiButton from '@/Components/UiButton.vue';
import UiCallout from '@/Components/UiCallout.vue';
import UiTable from '@/Components/UiTable.vue';
import EmptyState from '@/Components/EmptyState.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { artifactKind } from '@/report-artifacts.js';
import { ArrowTopRightOnSquareIcon, LockClosedIcon, MagnifyingGlassIcon } from '@heroicons/vue/20/solid';
import { GlobeAltIcon } from '@heroicons/vue/24/outline';
import { inputClass, selectClass } from '@/ui.js';

const props = defineProps({ documents: Array, projects: { type: Array, default: () => [] }, canPublish: Boolean, handle: String });
const uploading = ref(false);
const query = ref('');
const project = ref('');
const shown = computed(() => {
    const words = query.value.trim().toLowerCase().split(/\s+/).filter(Boolean);
    return props.documents.filter(doc => (!project.value || doc.project.id === Number(project.value))
        && words.every(word => `${doc.title} ${doc.slug} ${artifactKind(doc.kind).label} ${doc.project.name}`.toLowerCase().includes(word)));
});
const date = (value) => new Date(value).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
</script>

<template>
    <Head title="Artifacts" />

    <AppLayout>
        <div class="flex flex-col gap-8">
            <PageHeader title="Artifacts" description="Published artifacts across projects. Each has a reader page and version history.">
                <template #actions><UiButton v-if="canPublish && projects.length" :variant="uploading ? 'outline' : 'primary'" @click="uploading = !uploading">{{ uploading ? 'Close' : 'Publish an artifact' }}</UiButton></template>
            </PageHeader>

            <UiCallout v-if="!canPublish" heading="Publishing plan">
                Hosted artifacts include shareable reader pages, version histories, and project collections. Your plan doesn't include publishing yet.
                You can still publish to your own storage bucket or GitHub Pages.
            </UiCallout>

            <ArtifactUpload v-if="canPublish && uploading" :projects="projects" />

            <template v-if="documents.length">
                <div class="flex flex-wrap gap-3">
                    <label class="relative min-w-0 flex-1 basis-64 sm:max-w-sm">
                        <span class="sr-only">Search</span>
                        <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                        <input v-model="query" type="search" placeholder="Search by name, kind, or project" :class="[inputClass, 'pl-9']">
                    </label>
                    <label v-if="projects.length > 1" class="w-full sm:w-56"><span class="sr-only">Project</span>
                        <select v-model="project" :class="selectClass"><option value="">All projects</option><option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option></select>
                    </label>
                </div>

                <UiTable label="Published artifacts">
                    <thead>
                        <tr><th>Name</th><th class="max-lg:hidden">Project</th><th class="max-sm:hidden">Kind</th><th class="max-2xl:hidden">Sharing</th><th class="numeric max-2xl:hidden">Views</th><th class="max-sm:hidden">Updated</th><th class="actions"><span class="sr-only">Actions</span></th></tr>
                    </thead>
                    <tbody>
                        <tr v-for="doc in shown" :key="doc.id">
                            <td class="strong max-w-72 truncate">
                                <span class="flex items-center gap-2.5">
                                    <component :is="artifactKind(doc.kind).icon" class="size-4 shrink-0 text-slate-400" aria-hidden="true" />
                                    <Link :href="`/artifacts/${doc.id}`" class="truncate hover:underline hover:underline-offset-4">{{ doc.title }}</Link>
                                    <span v-if="doc.merged_into_id" class="rounded-md bg-slate-800/5 px-1.5 text-xs font-normal text-slate-500 dark:bg-white/10">Merged</span>
                                </span>
                            </td>
                            <td class="max-w-48 truncate max-lg:hidden"><Link :href="`/projects/${doc.project.slug}/published`" class="hover:text-slate-800 hover:underline hover:underline-offset-4 dark:hover:text-white">{{ doc.project.name }}</Link></td>
                            <td class="max-sm:hidden">{{ artifactKind(doc.kind).label }}</td>
                            <td class="max-2xl:hidden"><span class="inline-flex items-center gap-1.5">{{ doc.readable ? 'Public' : 'Anyone with the link' }}<LockClosedIcon v-if="doc.password_protected" class="size-3.5" aria-label="Password protected" /></span></td>
                            <td class="numeric max-2xl:hidden">{{ doc.views.toLocaleString() }}</td>
                            <td class="max-sm:hidden">{{ date(doc.updated_at) }}</td>
                            <td class="actions">
                                <div class="flex justify-end gap-1">
                                    <UiButton :href="doc.url" external variant="ghost" size="sm" square :aria-label="`View ${doc.title}`" title="View"><ArrowTopRightOnSquareIcon aria-hidden="true" /></UiButton>
                                    <UiButton :href="`/artifacts/${doc.id}`" variant="outline" size="sm">Manage</UiButton>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </UiTable>
                <p v-if="!shown.length" class="text-sm text-slate-500">Nothing matches that search.</p>
            </template>

            <EmptyState v-else-if="canPublish" :icon="GlobeAltIcon" title="No published artifacts">
                Publish a file from your browser, R, Python, or the CLI. Start with a single figure, a data file, or a complete rendered report.
                <template #actions>
                    <UiButton v-if="projects.length" @click="uploading = true">Publish your first artifact</UiButton>
                    <UiButton v-else href="/projects/create">Create a project</UiButton>
                </template>
            </EmptyState>
        </div>
    </AppLayout>
</template>
