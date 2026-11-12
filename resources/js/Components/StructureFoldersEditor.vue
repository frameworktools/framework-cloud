<script setup>
import { computed, ref, watch } from 'vue';
import { FolderIcon } from '@heroicons/vue/24/outline';
import UiButton from '@/Components/UiButton.vue';
import UiInput from '@/Components/UiInput.vue';
import UiSelect from '@/Components/UiSelect.vue';
import { ask } from '@/confirm.js';
import { compareFolders, moveFolder, within } from '@/structure-layout.js';

const folders = defineModel('folders', { type: Array, required: true });
const locations = defineModel('locations', { type: Object, required: true });
const files = defineModel('files', { type: Array, required: true });
const selected = ref('');
const name = ref('');
const parent = ref('');
const error = ref('');
const sorted = computed(() => [...folders.value].sort(compareFolders));
const parents = computed(() => [{ value: '', label: 'Project root' }, ...sorted.value.filter((f) => !within(f.path, selected.value)).map((f) => ({ value: f.path, label: f.path }))]);
watch(selected, (path) => {
    name.value = path.split('/').at(-1) ?? '';
    parent.value = path.includes('/') ? path.slice(0, path.lastIndexOf('/')) : '';
    error.value = '';
});
function add(nested = false) {
    const prefix = nested && selected.value ? `${selected.value}/` : '';
    let path = `${prefix}new-folder`;
    let i = 2;
    while (folders.value.some((f) => f.path === path)) path = `${prefix}new-folder-${i++}`;
    folders.value = [...folders.value, { path, original_path: null }];
    selected.value = path;
}
function move() {
    try {
        if (!name.value.trim() || name.value.includes('/')) throw new Error('Enter a folder name without slashes.');
        const to = [parent.value, name.value.trim()].filter(Boolean).join('/');
        const result = moveFolder(folders.value, locations.value, files.value, selected.value, to);
        folders.value = result.folders;
        locations.value = result.locations;
        files.value = result.files;
        selected.value = to;
        error.value = '';
    } catch (e) { error.value = e.message; }
}
async function remove() {
    const path = selected.value;
    if (!await ask(`Remove ${path} and its subfolders from this structure? Starter files inside these folders will also be removed. Existing projects keep their files.`, { action: 'Remove folder' })) return;
    folders.value = folders.value.filter((f) => !within(f.path, path));
    files.value = files.value.filter((f) => !within(f.path, path));
    locations.value = Object.fromEntries(Object.entries(locations.value).map(([key, value]) => [key, value && within(value, path) ? '' : value]));
    selected.value = '';
}
</script>

<template>
    <div class="flex flex-wrap gap-2">
        <UiButton variant="outline" size="sm" @click="add()">Add folder</UiButton>
        <UiButton variant="ghost" size="sm" :disabled="!selected" @click="add(true)">Add subfolder</UiButton>
    </div>
    <div class="grid min-h-80 gap-8 md:grid-cols-2">
        <div class="min-w-0 md:border-r md:border-slate-800/10 md:pr-6 dark:md:border-white/15">
            <p class="mb-3 font-mono text-xs text-slate-500">project-dir/</p>
            <button v-for="folder in sorted" :key="folder.path" type="button" :aria-pressed="selected === folder.path" class="flex w-full items-center gap-2 rounded-md py-2 pr-3 text-left text-sm hover:bg-slate-100 dark:hover:bg-slate-800" :class="selected === folder.path ? 'bg-sky-50 text-sky-800 dark:bg-sky-950 dark:text-sky-300' : 'text-slate-700 dark:text-slate-300'" :style="{ paddingLeft: `${12 + (folder.path.split('/').length - 1) * 20}px` }" @click="selected = folder.path">
                <FolderIcon class="size-4 shrink-0" /><span class="truncate" :title="folder.path">{{ folder.path.split('/').at(-1) }}</span>
            </button>
            <p v-if="!folders.length" class="text-sm text-slate-500">No folders. Add a folder to start.</p>
        </div>
        <div v-if="selected" class="flex min-w-0 flex-col gap-6">
            <UiInput v-model="name" label="Folder name" placeholder="data" @keydown.enter.prevent="move" />
            <UiSelect v-model="parent" label="Parent folder" :options="parents" />
            <p class="text-xs text-slate-500">Renaming or moving a folder updates its subfolders, starter file paths, and Framework references.</p>
            <p v-if="error" role="alert" class="text-sm text-red-600 dark:text-red-400">{{ error }}</p>
            <div class="flex gap-2"><UiButton size="sm" variant="outline" @click="move">Apply changes</UiButton><UiButton size="sm" variant="ghost" @click="remove">Remove folder</UiButton></div>
        </div>
        <p v-else class="text-sm text-slate-500">Select a folder to rename, move, or remove it.</p>
    </div>
</template>
