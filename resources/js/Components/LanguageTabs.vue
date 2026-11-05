<script setup>
import { languageNames } from '@/library.js';

// Environment tabs over a catalog
defineProps({ languages: { type: Array, default: () => ['r', 'python', 'stata'] } });
const model = defineModel({ type: String });
const tab = (on) => on
    ? 'border-sky-600 text-sky-700 dark:border-sky-400 dark:text-sky-300'
    : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-800 dark:text-slate-400 dark:hover:border-slate-600 dark:hover:text-slate-200';
</script>

<template>
    <div role="tablist" aria-label="Language" class="flex gap-6 border-b border-slate-200 dark:border-slate-800">
        <button
            v-for="l in languages" :key="l" type="button" role="tab" :aria-selected="model === l"
            class="-mb-px border-b-2 px-1 pb-2.5 text-sm font-medium transition" :class="tab(model === l)"
            @click="model = l"
        >
            {{ languageNames[l] }}
        </button>
    </div>
</template>
