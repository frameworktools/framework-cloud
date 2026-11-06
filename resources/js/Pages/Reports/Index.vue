<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ProjectLayout from '@/Layouts/ProjectLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import UiButton from '@/Components/UiButton.vue';
import UiTable from '@/Components/UiTable.vue';
import { inputClass, selectClass } from '@/ui.js';
import { DocumentTextIcon, LockClosedIcon } from '@heroicons/vue/20/solid';

const props = defineProps({ projects: { type: Array, default: () => [] }, project: Object, reports: Array, canPublish: { type: Boolean, default: true } });
const form = useForm({ title: '' });
const selectedProject = ref(props.project?.id ?? props.projects[0]?.id ?? '');
const sharing = { private: 'Private', unlisted: 'Anyone with the link', public: 'Public' };
const date = value => value ? new Date(value).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' }) : '—';
</script>

<template>
    <Head :title="project ? `${project.name} reports` : 'Reports'" />
    <component :is="project ? ProjectLayout : AppLayout" :project="project">
        <div class="space-y-8">
            <PageHeader title="Reports" description="Reports with text, figures, tables, and models. Published versions preserve their selected artifact versions." />

            <form v-if="project || projects.length" class="flex flex-wrap items-start gap-2" @submit.prevent="form.post(`/projects/${selectedProject}/reports`)">
                <label v-if="!project" class="w-full sm:w-56"><span class="sr-only">Project for new report</span><select v-model="selectedProject" :class="selectClass" required><option v-for="choice in projects" :key="choice.id" :value="choice.id">{{ choice.name }}</option></select></label>
                <label class="sr-only" for="new-report">Report title</label>
                <input id="new-report" v-model="form.title" required placeholder="Report title" :class="[inputClass, 'max-w-sm flex-1 basis-64']">
                <UiButton type="submit" :disabled="form.processing">Create report</UiButton>
                <p v-if="form.errors.title" class="w-full text-sm text-red-600 dark:text-red-400">{{ form.errors.title }}</p>
            </form>
            <UiButton v-else href="/projects/create">Create a project</UiButton>

            <p v-if="!reports.length" class="text-sm text-slate-500 dark:text-slate-400">New reports start as private drafts.</p>
            <UiTable v-else label="Reports" class="research-results">
                <thead><tr><th>Title</th><th class="max-md:hidden">Sharing</th><th class="max-sm:hidden">Published</th><th class="actions"><span class="sr-only">Actions</span></th></tr></thead>
                <tbody>
                    <tr v-for="report in reports" :key="report.id">
                        <td class="strong max-w-96 truncate">
                            <span class="flex items-center gap-2.5">
                                <DocumentTextIcon class="size-4 shrink-0 text-slate-400" aria-hidden="true" />
                                <Link :href="`/reports/${report.id}/edit`" class="truncate hover:underline hover:underline-offset-4">{{ report.title }}</Link>
                            </span>
                            <Link v-if="!project" :href="`/projects/${report.project.slug}`" class="mt-1 block truncate text-xs font-normal text-slate-500 hover:underline">{{ report.project.name }}</Link>
                        </td>
                        <td class="max-md:hidden"><span class="inline-flex items-center gap-1.5">{{ sharing[report.visibility] }}<LockClosedIcon v-if="report.password_protected" class="size-3.5" aria-label="Password protected" /></span></td>
                        <td class="max-sm:hidden">{{ report.versions?.length ? `Version ${report.versions[0].number}, ${date(report.versions[0].created_at)}` : 'Draft' }}</td>
                        <td class="actions">
                            <div class="flex justify-end gap-1">
                                <UiButton v-if="report.versions?.length" :href="report.url" external variant="ghost" size="sm" :aria-label="`View ${report.title}`">View</UiButton>
                                <UiButton :href="`/reports/${report.id}/edit`" variant="ghost" size="sm" :aria-label="`Edit ${report.title}`">Edit</UiButton>
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
