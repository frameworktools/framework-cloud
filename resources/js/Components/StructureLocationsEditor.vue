<script setup>
import { computed, ref } from 'vue';
import { FolderIcon, CheckIcon } from '@heroicons/vue/24/outline';
import { compareFolders } from '@/structure-layout.js';
import UiButton from '@/Components/UiButton.vue';

const props = defineProps({ folders: { type: Array, required: true }, errors: { type: Object, default: () => ({}) } });
const locations = defineModel({ type: Object, required: true });
const selected = ref('functions');
const types = [
    { key: 'functions', label: 'Functions', description: 'Shared project functions.', fallback: 'functions/' },
    { key: 'cache', label: 'Cache', description: 'Cached results. Created when caching is used.', fallback: 'outputs/cache/' },
];
const current = computed(() => types.find((type) => type.key === selected.value));
const sorted = computed(() => [...props.folders].sort(compareFolders));
const assign = (path) => { locations.value = { ...locations.value, [selected.value]: path }; };
const pathLabel = (key) => locations.value[key] === '.' ? 'Project root' : locations.value[key] || 'Framework default';
</script>

<template>
    <div class="grid gap-8 md:grid-cols-[minmax(12rem,1fr)_minmax(0,2fr)]">
        <div class="min-w-0 md:border-r md:border-slate-800/10 md:pr-6 dark:md:border-white/15">
            <h3 class="mb-3 text-sm font-medium text-slate-900 dark:text-white">Location type</h3>
            <button v-for="type in types" :key="type.key" type="button" :aria-pressed="selected === type.key" class="mb-1 flex w-full flex-col gap-1 rounded-md px-3 py-3 text-left text-sm hover:bg-slate-100 dark:hover:bg-slate-800" :class="selected === type.key ? 'bg-sky-50 text-sky-800 dark:bg-sky-950 dark:text-sky-300' : 'text-slate-700 dark:text-slate-300'" @click="selected = type.key">
                <span class="font-medium">{{ type.label }}</span><span class="break-all text-xs opacity-75">{{ pathLabel(type.key) }}</span>
            </button>
        </div>
        <div class="min-w-0">
            <h3 class="text-sm font-medium text-slate-900 dark:text-white">{{ current.label }} folder</h3>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ current.description }} Select a folder to assign it.</p>
            <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">Default: {{ current.fallback }}</p>
            <div class="mt-4 flex flex-col gap-1">
                <button v-for="folder in [{ path: '.', label: 'Project root' }, ...sorted]" :key="folder.path" type="button" :aria-pressed="locations[selected] === folder.path" class="flex w-full items-center gap-2 rounded-md py-2 pr-3 text-left text-sm hover:bg-slate-100 dark:hover:bg-slate-800" :class="locations[selected] === folder.path ? 'bg-sky-50 text-sky-800 dark:bg-sky-950 dark:text-sky-300' : 'text-slate-700 dark:text-slate-300'" :style="{ paddingLeft: `${12 + (folder.path === '.' ? 0 : folder.path.split('/').length) * 20}px` }" @click="assign(folder.path)">
                    <FolderIcon class="size-4 shrink-0" /><span class="truncate" :title="folder.path">{{ folder.label ?? folder.path.split('/').at(-1) }}</span><CheckIcon v-if="locations[selected] === folder.path" class="ml-auto size-4 shrink-0" />
                </button>
            </div>
            <p v-if="!folders.length" class="mt-3 text-sm text-slate-500">Add folders in the Folders tab.</p>
            <UiButton variant="ghost" size="sm" class="mt-4" :disabled="!locations[selected]" @click="assign('')">Use Framework default</UiButton>
            <p v-if="errors[`framework_locations.${selected}`]" role="alert" class="mt-3 text-sm text-red-600 dark:text-red-400">{{ errors[`framework_locations.${selected}`] }}</p>
        </div>
    </div>
</template>
