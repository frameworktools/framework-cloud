<script setup>
import { computed, ref, watch, nextTick } from 'vue';
import UiModal from '@/Components/UiModal.vue';
import { MagnifyingGlassIcon } from '@heroicons/vue/20/solid';
import { artifactKind } from '@/report-artifacts.js';

// Choose an artifact to place in the report. Emits the artifact's id.
const props = defineProps({ artifacts: { type: Array, default: () => [] } });
const emit = defineEmits(['choose']);
const show = defineModel({ type: Boolean, default: false });
const query = ref('');
const search = ref(null);

const results = computed(() => {
    const words = query.value.trim().toLowerCase().split(/\s+/).filter(Boolean);
    return props.artifacts.filter(artifact => {
        const haystack = `${artifact.title || ''} ${artifact.slug} ${artifactKind(artifact.kind).label}`.toLowerCase();
        return words.every(word => haystack.includes(word));
    });
});
watch(show, open => { if (open) { query.value = ''; nextTick(() => search.value?.focus()); } });
function choose(artifact) { emit('choose', artifact.id); show.value = false; }
</script>

<template>
    <UiModal v-model="show" title="Add an artifact">
        <p class="-mt-1 text-sm text-slate-600 dark:text-slate-400">Figures, tables, models, and documents published to this project.</p>
        <label class="relative block">
            <span class="sr-only">Search artifacts</span>
            <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" aria-hidden="true" />
            <input ref="search" v-model="query" type="search" placeholder="Search by name or kind" autocomplete="off"
                class="block w-full rounded-lg border border-slate-300 bg-white py-2 pl-9 pr-3 text-sm text-slate-900 focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100"
                @keydown.enter.prevent="results[0] && choose(results[0])">
        </label>
        <ul class="-mx-2 max-h-96 overflow-y-auto" role="list">
            <li v-for="artifact in results" :key="artifact.id">
                <button type="button" class="flex w-full items-center gap-3 rounded-lg px-2 py-2 text-left hover:bg-slate-100 focus-visible:bg-slate-100 focus-visible:outline-none dark:hover:bg-slate-800 dark:focus-visible:bg-slate-800" @click="choose(artifact)">
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-sky-50 text-sky-700 dark:bg-sky-950 dark:text-sky-300">
                        <component :is="artifactKind(artifact.kind).icon" class="size-5" aria-hidden="true" />
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-medium text-slate-900 dark:text-slate-100">{{ artifact.title || artifact.slug }}</span>
                        <span class="block truncate text-xs text-slate-500 dark:text-slate-400">
                            {{ artifactKind(artifact.kind).label }} · {{ artifact.versions.length === 1 ? '1 version' : `${artifact.versions.length} versions` }} · {{ artifact.visibility }}
                        </span>
                    </span>
                </button>
            </li>
        </ul>
        <p v-if="!results.length" class="py-6 text-center text-sm text-slate-500 dark:text-slate-400">{{ artifacts.length ? 'No artifacts match your search.' : 'Publish an artifact to this project to add it here.' }}</p>
    </UiModal>
</template>
