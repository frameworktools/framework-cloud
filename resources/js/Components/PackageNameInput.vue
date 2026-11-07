<script setup>
import { ref, watch } from 'vue';

/**
 * A package name with suggestions, as in the Framework GUI: CRAN and
 * Bioconductor match by name, PyPI by exact name. Arrow keys move through
 * the suggestions, Enter picks one, and Escape closes them.
 */
defineOptions({ inheritAttrs: false });

const props = defineProps({
    source: { type: String, required: true }, // cran | bioc | pypi
    placeholder: String,
    id: String,
});
const model = defineModel({ type: String, default: '' });

const results = ref([]);
const highlight = ref(-1);
const searching = ref(false);
let timer = null;
let typed = false;

watch(model, (value) => {
    clearTimeout(timer);
    // Only look up what the person typed, not a name a suggestion filled in
    if (!typed || (value ?? '').length < 2) {
        results.value = [];
        return;
    }
    timer = setTimeout(() => search(value), 300);
});

const search = async (query) => {
    searching.value = true;
    try {
        const response = await fetch(`/packages/search?source=${props.source}&q=${encodeURIComponent(query)}`, { headers: { Accept: 'application/json' } });
        results.value = response.ok ? (await response.json()).packages : [];
        highlight.value = results.value.length ? 0 : -1;
    } catch {
        results.value = [];
    } finally {
        searching.value = false;
    }
};

const pick = (pkg) => {
    typed = false;
    model.value = pkg.name;
    results.value = [];
};
const onInput = (event) => {
    typed = true;
    model.value = event.target.value;
};
const onKeydown = (event) => {
    if (!results.value.length) return;
    if (event.key === 'ArrowDown') {
        event.preventDefault();
        highlight.value = Math.min(highlight.value + 1, results.value.length - 1);
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        highlight.value = Math.max(highlight.value - 1, 0);
    } else if (event.key === 'Enter' && highlight.value >= 0) {
        event.preventDefault();
        pick(results.value[highlight.value]);
    } else if (event.key === 'Escape') {
        results.value = [];
    }
};
// A suggestion's mousedown runs first, so closing on blur doesn't eat the click
const onBlur = () => setTimeout(() => (results.value = []), 150);
const sourceNames = { cran: 'CRAN', bioc: 'Bioconductor', pypi: 'PyPI' };
</script>

<template>
    <div class="relative">
        <input
            v-bind="$attrs" :id="id" :value="model" type="text" autocomplete="off" spellcheck="false" :placeholder="placeholder"
            role="combobox" :aria-expanded="results.length > 0" aria-autocomplete="list"
            class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 font-mono text-sm text-slate-900 placeholder-slate-400 focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100"
            @input="onInput" @keydown="onKeydown" @blur="onBlur"
        >
        <span v-if="searching" class="absolute right-3 top-2.5 text-xs text-slate-400">…</span>
        <ul v-if="results.length" role="listbox" class="absolute z-20 mt-1 max-h-64 w-full overflow-y-auto rounded-md border border-slate-200 bg-white shadow-lg dark:border-slate-700 dark:bg-slate-900">
            <li
                v-for="(pkg, i) in results" :key="pkg.name" role="option" :aria-selected="i === highlight"
                class="flex cursor-pointer items-center justify-between gap-3 border-b border-slate-100 px-3 py-2 last:border-b-0 dark:border-slate-800"
                :class="i === highlight ? 'bg-sky-50 dark:bg-sky-950/40' : 'hover:bg-slate-50 dark:hover:bg-slate-800'"
                @mousedown.prevent="pick(pkg)" @mouseenter="highlight = i"
            >
                <span class="min-w-0">
                    <span class="block font-mono text-sm text-slate-900 dark:text-white">{{ pkg.name }}</span>
                    <span v-if="pkg.title" class="block truncate text-xs text-slate-500 dark:text-slate-400">{{ pkg.title }}</span>
                </span>
                <span class="shrink-0 text-xs text-slate-400">{{ sourceNames[pkg.source] }} · v{{ pkg.version }}</span>
            </li>
        </ul>
    </div>
</template>
