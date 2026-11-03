<script setup>
import { useForm } from '@inertiajs/vue3';
import ProjectLayout from '@/Layouts/ProjectLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import UiButton from '@/Components/UiButton.vue';
import UiTable from '@/Components/UiTable.vue';
import { inputClass } from '@/ui.js';
import { ArrowTopRightOnSquareIcon, ClockIcon, LockClosedIcon, RectangleStackIcon } from '@heroicons/vue/20/solid';

defineProps({ project: Object, collections: Array });
const form = useForm({ title: '' });
const sharing = { private: 'Only you', unlisted: 'Anyone with the link', public: 'Public' };
const day = value => new Date(value).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
</script>

<template>
    <Head :title="`${project.name} collections`" />
    <ProjectLayout :project="project">
        <div class="space-y-8">
            <PageHeader title="Collections" description="Related artifacts for readers, collaborators, or reviewers. Entries can use the latest version or a fixed version." />

            <form class="flex flex-wrap items-start gap-2" @submit.prevent="form.post(`/projects/${project.id}/collections`)">
                <label class="sr-only" for="new-collection">Collection title</label>
                <input id="new-collection" v-model="form.title" required placeholder="Collection title" :class="[inputClass, 'max-w-sm flex-1 basis-64']">
                <UiButton type="submit" variant="ghost" :disabled="form.processing">Create collection</UiButton>
                <p v-if="form.errors.title" class="w-full text-sm text-red-600 dark:text-red-400">{{ form.errors.title }}</p>
            </form>

            <p v-if="!collections.length" class="text-sm text-slate-500 dark:text-slate-400">New collections are private until you share them.</p>
            <UiTable v-else label="Collections">
                <thead><tr><th>Title</th><th class="numeric max-sm:hidden">Artifacts</th><th class="max-md:hidden">Sharing</th><th class="max-md:hidden">Link expires</th><th class="actions"><span class="sr-only">Actions</span></th></tr></thead>
                <tbody>
                    <tr v-for="collection in collections" :key="collection.id">
                        <td class="strong max-w-96 truncate">
                            <span class="flex items-center gap-2.5">
                                <RectangleStackIcon class="size-4 shrink-0 text-slate-400" aria-hidden="true" />
                                <Link :href="`/collections/${collection.id}/edit`" class="truncate hover:underline hover:underline-offset-4">{{ collection.title }}</Link>
                            </span>
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
                                <UiButton v-if="collection.visibility !== 'private'" :href="collection.url" external variant="ghost" size="sm" :aria-label="`View ${collection.title}`"><ArrowTopRightOnSquareIcon aria-hidden="true" />View</UiButton>
                                <UiButton :href="`/collections/${collection.id}/edit`" variant="ghost" size="sm" :aria-label="`Edit ${collection.title}`">Edit</UiButton>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </UiTable>
        </div>
    </ProjectLayout>
</template>
