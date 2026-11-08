<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ProjectLayout from '@/Layouts/ProjectLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import UiButton from '@/Components/UiButton.vue';
import UiTable from '@/Components/UiTable.vue';
import { inputClass, selectClass } from '@/ui.js';
import { ClockIcon, LockClosedIcon, RectangleStackIcon } from '@heroicons/vue/20/solid';

const props = defineProps({ projects: { type: Array, default: () => [] }, project: Object, collections: Array });
const form = useForm({ title: '' });
// '' is no project
const selectedProject = ref(props.project?.id ?? '');
const sharing = { private: 'Private', unlisted: 'Anyone with the link', public: 'Public' };
const day = value => new Date(value).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
</script>

<template>
    <Head :title="project ? `${project.name} collections` : 'Collections'" />
    <component :is="project ? ProjectLayout : AppLayout" :project="project">
        <div class="space-y-8">
            <PageHeader title="Collections" description="Related artifacts for readers, collaborators, or reviewers. Entries can use the latest version or a fixed version." />

            <form class="flex flex-wrap items-start gap-2" @submit.prevent="form.post(selectedProject ? `/projects/${selectedProject}/collections` : '/collections')">
                <label v-if="!project" class="w-full sm:w-56"><span class="sr-only">Project for new collection</span><select v-model="selectedProject" :class="selectClass"><option value="">No project</option><option v-for="choice in projects" :key="choice.id" :value="choice.id">{{ choice.name }}</option></select></label>
                <label class="sr-only" for="new-collection">Collection title</label>
                <input id="new-collection" v-model="form.title" required placeholder="Collection title" :class="[inputClass, 'max-w-sm flex-1 basis-64']">
                <UiButton type="submit" :disabled="form.processing">Create collection</UiButton>
                <p v-if="form.errors.title" class="w-full text-sm text-red-600 dark:text-red-400">{{ form.errors.title }}</p>
            </form>

            <p v-if="!collections.length" class="text-sm text-slate-500 dark:text-slate-400">New collections are private until you share them.</p>
            <UiTable v-else label="Collections" class="research-results">
                <thead><tr><th>Title</th><th class="numeric max-sm:hidden">Artifacts</th><th class="max-md:hidden">Sharing</th><th class="max-md:hidden">Link expires</th><th class="actions"><span class="sr-only">Actions</span></th></tr></thead>
                <tbody>
                    <tr v-for="collection in collections" :key="collection.id">
                        <td class="strong max-w-96 truncate">
                            <span class="flex items-center gap-2.5">
                                <RectangleStackIcon class="size-4 shrink-0 text-slate-400" aria-hidden="true" />
                                <Link :href="`/collections/${collection.id}/edit`" class="truncate hover:underline hover:underline-offset-4">{{ collection.title }}</Link>
                            </span>
                            <Link v-if="!project && !collection.project.is_home" :href="`/projects/${collection.project.slug}`" class="mt-1 block truncate text-xs font-normal text-slate-500 dark:text-slate-400 hover:underline">{{ collection.project.name }}</Link>
                            <span v-else-if="!project" class="mt-1 block truncate text-xs font-normal text-slate-500 dark:text-slate-400">No project</span>
                        </td>
                        <td class="numeric max-sm:hidden">{{ collection.items_count }}</td>
                        <td class="max-md:hidden"><span class="inline-flex items-center gap-1.5">{{ sharing[collection.visibility] }}<LockClosedIcon v-if="collection.protected" class="size-3.5" aria-label="Password protected" /></span></td>
                        <td class="max-md:hidden">
                            <span v-if="collection.expires_at && collection.visibility !== 'private'" class="inline-flex items-center gap-1.5" :class="{ 'text-amber-700 dark:text-amber-400': collection.expired }">
                                <ClockIcon class="size-3.5" aria-hidden="true" />{{ collection.expired ? 'Expired' : '' }} {{ day(collection.expires_at) }}
                            </span>
                            <span v-else>Never</span>
                        </td>
                        <td class="actions">
                            <div class="flex justify-end gap-1">
                                <UiButton v-if="collection.visibility !== 'private'" :href="collection.url" external variant="ghost" size="sm" :aria-label="`View ${collection.title}`">View</UiButton>
                                <UiButton :href="`/collections/${collection.id}/edit`" variant="ghost" size="sm" :aria-label="`Edit ${collection.title}`">Edit</UiButton>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </UiTable>
        </div>
    </component>
</template>

<style scoped>
@media (max-width: 639px) {
    .research-results :deep(table) { width: 100%; table-layout: fixed; }
    .research-results :deep(th:last-child) { width: 8rem; }
}
</style>
