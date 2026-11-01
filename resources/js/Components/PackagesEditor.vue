<script setup>
import { computed } from 'vue';
import { ChevronRightIcon, InformationCircleIcon, XMarkIcon } from '@heroicons/vue/20/solid';
import PackageNameInput from '@/Components/PackageNameInput.vue';
import UiButton from '@/Components/UiButton.vue';
import UiSwitch from '@/Components/UiSwitch.vue';

/**
 * One language's default packages, as the Framework GUI's packages editor
 * had them: each package with its name, where it comes from, and for R
 * whether scaffold() attaches it, one row each in a single list. Package
 * sets load their packages into the list; the list is what's saved.
 */
const props = defineProps({
    language: { type: String, required: true }, // r | python
    presets: { type: Array, default: () => [] }, // package sets that fit this language
});
const rows = defineModel({ type: Array, default: () => [] });

const r = computed(() => props.language === 'r');
const sources = computed(() => (r.value
    ? [{ value: 'cran', label: 'CRAN' }, { value: 'github', label: 'GitHub' }, { value: 'bioc', label: 'Bioconductor' }]
    : [{ value: 'pypi', label: 'PyPI' }, { value: 'github', label: 'GitHub' }]));

// New packages are installed but not attached until the person turns it on
const blank = () => ({ name: '', source: r.value ? 'cran' : 'pypi', auto_attach: false, version: null, repo: null });
const update = (i, patch) => {
    rows.value = rows.value.map((row, j) => (j === i ? { ...row, ...patch } : row));
};
const remove = (i) => {
    rows.value = rows.value.filter((_, j) => j !== i);
};
const add = () => {
    rows.value = [...rows.value, blank()];
};
// A GitHub package is written owner/repo; its name is the repository's
const setRepo = (i, value) => update(i, { repo: value || null, name: (value ?? '').split('/')[1] ?? '' });
const setSource = (i, source) => update(i, source === 'github' ? { source } : { source, repo: null });

// A set adds the packages the list doesn't have yet
const missing = (preset) => preset.packages.filter((p) => !rows.value.some((row) => row.name.toLowerCase() === p.name.toLowerCase()));
const load = (preset) => {
    rows.value = [...rows.value.filter((row) => row.name), ...missing(preset).map((p) => ({ ...blank(), ...p }))];
};

const field = 'w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100';
</script>

<template>
    <div class="flex flex-col gap-4">
        <!-- Package sets: each opens to its packages, and adds the ones the list lacks -->
        <details v-if="presets.length" class="group rounded-lg border border-slate-200 dark:border-slate-700">
            <summary class="flex cursor-pointer list-none items-center gap-2 px-4 py-3 text-sm font-medium text-slate-800 dark:text-slate-200 [&::-webkit-details-marker]:hidden">
                <ChevronRightIcon class="size-4 text-slate-400 transition-transform group-open:rotate-90" />
                Load a package set
            </summary>
            <div class="divide-y divide-slate-200 border-t border-slate-200 dark:divide-slate-800 dark:border-slate-700">
                <div v-for="preset in presets" :key="preset.key" class="flex items-start justify-between gap-4 px-4 py-3">
                    <div class="min-w-0">
                        <div class="text-sm font-medium text-slate-900 dark:text-white">{{ preset.name }}</div>
                        <div class="mt-0.5 font-mono text-xs text-slate-500">{{ preset.packages.map((p) => p.name).join(', ') }}</div>
                    </div>
                    <UiButton size="sm" variant="outline" class="shrink-0" :disabled="!missing(preset).length" @click="load(preset)">
                        {{ missing(preset).length ? `Add ${missing(preset).length}` : 'All added' }}
                    </UiButton>
                </div>
                <p class="px-4 py-3 text-sm"><Link href="/package-sets" class="text-sky-600 hover:underline dark:text-sky-400">Manage package sets</Link></p>
            </div>
        </details>

        <div class="divide-y divide-slate-200 rounded-lg border border-slate-200 dark:divide-slate-800 dark:border-slate-700">
            <!-- Column labels, for the rows below -->
            <div class="hidden gap-3 px-4 py-2 text-xs font-medium text-slate-500 sm:grid" :class="r ? 'grid-cols-[1fr_9rem_7rem_2rem]' : 'grid-cols-[1fr_9rem_7rem_2rem]'">
                <span>Package</span><span>Source</span>
                <span v-if="r" class="flex items-center gap-1">
                    Auto-attach
                    <!-- A popover on hover or focus -->
                    <span class="group/info relative">
                        <button type="button" class="flex rounded-full text-slate-400 hover:text-slate-600 focus-visible:outline-2 focus-visible:outline-sky-500 dark:hover:text-slate-300" aria-label="About auto-attach" aria-describedby="auto-attach-help">
                            <InformationCircleIcon class="size-4" />
                        </button>
                        <span
                            id="auto-attach-help" role="tooltip"
                            class="pointer-events-none invisible absolute left-1/2 top-6 z-30 w-64 -translate-x-1/2 rounded-md bg-slate-900 px-3 py-2 text-xs font-normal leading-relaxed text-white opacity-0 shadow-lg transition group-focus-within/info:visible group-focus-within/info:opacity-100 group-hover/info:visible group-hover/info:opacity-100 dark:bg-slate-700"
                        >When scaffold() runs, it installs any missing packages and loads the ones switched on here with library(), so they're ready in every notebook and script.</span>
                    </span>
                </span>
                <span v-else>Version</span><span class="sr-only">Remove</span>
            </div>
            <div v-for="(row, i) in rows" :key="i" class="grid items-center gap-3 px-4 py-2.5 sm:grid-cols-[1fr_9rem_7rem_2rem]">
                <input
                    v-if="row.source === 'github'" :value="row.repo ?? ''" type="text" placeholder="owner/repo" spellcheck="false" aria-label="Repository"
                    :class="[field, 'font-mono']" @input="setRepo(i, $event.target.value.trim())"
                >
                <PackageNameInput
                    v-else :source="row.source" :model-value="row.name" aria-label="Package"
                    :placeholder="{ cran: 'Search CRAN…', bioc: 'Search Bioconductor…', pypi: 'Name on PyPI' }[row.source]"
                    @update:model-value="(name) => update(i, { name })"
                />
                <select :value="row.source" aria-label="Source" :class="field" @change="setSource(i, $event.target.value)">
                    <option v-for="s in sources" :key="s.value" :value="s.value">{{ s.label }}</option>
                </select>
                <div v-if="r" class="[&>label]:w-fit" :title="'Call library() when scaffold() runs'">
                    <UiSwitch :model-value="row.auto_attach" @update:model-value="(on) => update(i, { auto_attach: on })" />
                    <span class="sr-only">Auto-attach {{ row.name }}</span>
                </div>
                <input
                    v-else :value="row.version ?? ''" type="text" placeholder="any" spellcheck="false" aria-label="Version"
                    :class="[field, 'font-mono']" @input="update(i, { version: $event.target.value.trim() || null })"
                >
                <button
                    type="button" :aria-label="`Remove ${row.name || 'package'}`" title="Remove"
                    class="justify-self-end rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                    @click="remove(i)"
                ><XMarkIcon class="size-5" /></button>
            </div>
            <p v-if="!rows.length" class="px-4 py-3 text-sm text-slate-500">No packages yet.</p>
        </div>
        <div><UiButton size="sm" variant="outline" @click="add">Add package</UiButton></div>

    </div>
</template>
