<script setup>
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import UiButton from '@/Components/UiButton.vue';
import UiInput from '@/Components/UiInput.vue';
import UiPanel from '@/Components/UiPanel.vue';

const props = defineProps({ projects: { type: Array, default: () => [] }, projectId: [Number, String] });
const emit = defineEmits(['published']);
const form = useForm({ project_id: props.projectId ?? props.projects[0]?.id ?? '', file: null, slug: '', title: '', description: '', visibility: 'unlisted', password: '', entrypoint: 'index.html' });
const bundle = ref(false);
const fileInput = ref(null);
const selectedProject = computed(() => props.projectId ?? form.project_id);
const submit = () => form.transform((data) => {
    const payload = { visibility: data.visibility };
    for (const key of ['slug', 'title', 'description', 'password']) if (data[key]) payload[key] = data[key];
    payload[bundle.value ? 'bundle' : 'file'] = data.file;
    if (bundle.value) payload.entrypoint = data.entrypoint;
    return payload;
}).post(`/projects/${selectedProject.value}/artifacts`, {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => { form.reset('file', 'slug', 'title', 'description', 'password'); if (fileInput.value) fileInput.value.value = ''; emit('published'); },
});
const fieldClass = 'w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100';
</script>

<template>
    <UiPanel title="Artifact upload" description="Figures, tables, models, reports, books, and data files. Reusing an artifact name adds a version at the same link.">
        <form class="flex flex-col gap-4" @submit.prevent="submit">
            <label v-if="!projectId" class="flex flex-col gap-1.5 text-sm font-medium">
                Project
                <select v-model="form.project_id" :class="fieldClass" required>
                    <option disabled value="">Choose a project</option>
                    <option v-for="project in projects" :key="project.id" :value="project.id">{{ project.name }}</option>
                </select>
            </label>
            <label class="flex flex-col gap-1.5 text-sm font-medium">
                File
                <input ref="fileInput" type="file" :class="fieldClass" required @change="form.file = $event.target.files[0] ?? null">
                <span class="text-xs font-normal text-slate-500">Upload files intended for sharing. CSV and TSV files get sortable tables and column summaries. Excel, Parquet, Stata, SPSS, and RDS files are available for download. For rendered books or sites, ZIP the output folder’s contents.</span>
            </label>
            <label class="flex items-center gap-2 text-sm"><input v-model="bundle" type="checkbox">HTML ZIP bundle</label>
            <UiInput v-if="bundle" v-model="form.entrypoint" label="ZIP entry page" placeholder="index.html" required />
            <div class="grid gap-4 sm:grid-cols-2">
                <UiInput v-model="form.title" label="Title" placeholder="Main findings" />
                <UiInput v-model="form.slug" label="Artifact name" placeholder="main-findings" description="Leave blank to use the filename. Reuse this name to add a version." />
            </div>
            <UiInput v-model="form.description" label="Description" placeholder="Artifact description" />
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="flex flex-col gap-1.5 text-sm font-medium">Sharing
                    <select v-model="form.visibility" :class="fieldClass">
                        <option value="private">Private, only you</option><option value="unlisted">Anyone with the link</option>
                        <option value="public">Public, with a readable link</option>
                    </select>
                </label>
                <UiInput v-model="form.password" type="password" label="Password (optional)" autocomplete="new-password" description="At least 8 characters. Readers need it to open the artifact and its files." />
            </div>
            <p class="text-xs text-slate-500">A new version keeps the current password when this field is blank.</p>
            <ul v-if="Object.keys(form.errors).length" role="alert" class="text-sm text-red-600 dark:text-red-400">
                <li v-for="(error, key) in form.errors" :key="key">{{ error }}</li>
            </ul>
            <progress v-if="form.progress" :value="form.progress.percentage" max="100" class="w-full" aria-label="Upload progress" />
            <div><UiButton type="submit" :disabled="form.processing || !selectedProject">{{ form.processing ? 'Publishing…' : 'Publish artifact' }}</UiButton></div>
        </form>
    </UiPanel>
</template>
