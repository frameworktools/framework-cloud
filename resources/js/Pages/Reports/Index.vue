<script setup>
import { useForm } from '@inertiajs/vue3';
import ProjectLayout from '@/Layouts/ProjectLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import UiButton from '@/Components/UiButton.vue';
import UiTable from '@/Components/UiTable.vue';
import { inputClass } from '@/ui.js';
import { ArrowTopRightOnSquareIcon, DocumentTextIcon, LockClosedIcon } from '@heroicons/vue/20/solid';

defineProps({ project: Object, reports: Array, canPublish: { type: Boolean, default: true } });
const form = useForm({ title: '' });
const sharing = { private: 'Only you', unlisted: 'Anyone with the link', public: 'Public' };
const date = value => value ? new Date(value).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' }) : '—';
</script>

<template>
    <Head :title="`${project.name} reports`" />
    <ProjectLayout :project="project">
        <div class="space-y-8">
            <PageHeader title="Reports" description="Reports with text, figures, tables, and models. Published versions preserve their selected artifact versions." />

            <form class="flex flex-wrap items-start gap-2" @submit.prevent="form.post(`/projects/${project.id}/reports`)">
                <label class="sr-only" for="new-report">Report title</label>
                <input id="new-report" v-model="form.title" required placeholder="Report title" :class="[inputClass, 'max-w-sm flex-1 basis-64']">
                <UiButton type="submit" variant="ghost" :disabled="form.processing">Create report</UiButton>
                <p v-if="form.errors.title" class="w-full text-sm text-red-600 dark:text-red-400">{{ form.errors.title }}</p>
            </form>

            <p v-if="!reports.length" class="text-sm text-slate-500 dark:text-slate-400">New reports start as private drafts.</p>
            <UiTable v-else label="Reports">
                <thead><tr><th>Title</th><th class="max-md:hidden">Sharing</th><th class="max-sm:hidden">Published</th><th class="actions"><span class="sr-only">Actions</span></th></tr></thead>
                <tbody>
                    <tr v-for="report in reports" :key="report.id">
                        <td class="strong max-w-96 truncate">
                            <span class="flex items-center gap-2.5">
                                <DocumentTextIcon class="size-4 shrink-0 text-slate-400" aria-hidden="true" />
                                <Link :href="`/reports/${report.id}/edit`" class="truncate hover:underline hover:underline-offset-4">{{ report.title }}</Link>
                            </span>
                        </td>
                        <td class="max-md:hidden"><span class="inline-flex items-center gap-1.5">{{ sharing[report.visibility] }}<LockClosedIcon v-if="report.password_protected" class="size-3.5" aria-label="Password protected" /></span></td>
                        <td class="max-sm:hidden">{{ report.versions?.length ? `Version ${report.versions[0].number}, ${date(report.versions[0].created_at)}` : 'Draft' }}</td>
                        <td class="actions">
                            <div class="flex justify-end gap-1">
                                <UiButton v-if="report.versions?.length" :href="report.url" external variant="ghost" size="sm" :aria-label="`View ${report.title}`"><ArrowTopRightOnSquareIcon aria-hidden="true" />View</UiButton>
                                <UiButton :href="`/reports/${report.id}/edit`" variant="ghost" size="sm" :aria-label="`Edit ${report.title}`">Edit</UiButton>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </UiTable>
        </div>
    </ProjectLayout>
</template>
