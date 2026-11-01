<script setup>
import { computed } from 'vue';
import PackageNameInput from '@/Components/PackageNameInput.vue';
import UiButton from '@/Components/UiButton.vue';
import UiSwitch from '@/Components/UiSwitch.vue';

/**
 * One language's default packages, as the Framework GUI's packages editor
 * had them: each package with its name, where it comes from, and for R
 * whether scaffold() attaches it. Package sets load their packages into
 * the list; the list is what's saved.
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

const blank = () => ({ name: '', source: r.value ? 'cran' : 'pypi', auto_attach: r.value, version: null, repo: null });
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
const load = (preset) => {
    const have = new Set(rows.value.map((row) => row.name.toLowerCase()));
    rows.value = [...rows.value.filter((row) => row.name), ...preset.packages.filter((p) => !have.has(p.name.toLowerCase())).map((p) => ({ ...blank(), ...p }))];
};
const has = (preset) => preset.packages.every((p) => rows.value.some((row) => row.name.toLowerCase() === p.name.toLowerCase()));
</script>

<template>
    <div class="flex flex-col gap-4">
        <div v-if="presets.length" class="flex flex-wrap items-center gap-2">
            <span class="text-sm text-slate-600 dark:text-slate-400">Load a package set:</span>
            <button
                v-for="preset in presets" :key="preset.key" type="button" :disabled="has(preset)"
                :title="has(preset) ? 'Its packages are all in the list' : preset.packages.map((p) => p.name).join(', ')"
                class="rounded-full border border-slate-300 px-3 py-1 text-sm text-slate-700 transition hover:border-sky-500 hover:text-sky-700 disabled:cursor-default disabled:opacity-50 disabled:hover:border-slate-300 disabled:hover:text-slate-700 dark:border-slate-600 dark:text-slate-300"
                @click="load(preset)"
            >+ {{ preset.name }}</button>
        </div>

        <div v-if="rows.length" class="flex flex-col gap-3">
            <div v-for="(row, i) in rows" :key="i" class="rounded-lg border border-slate-200 p-4 dark:border-slate-700">
                <div class="grid gap-3 sm:grid-cols-[1fr_10rem]">
                    <div class="flex flex-col gap-1.5">
                        <label :for="`pkg-${language}-${i}`" class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ row.source === 'github' ? 'Repository' : 'Package' }}</label>
                        <input
                            v-if="row.source === 'github'" :id="`pkg-${language}-${i}`" :value="row.repo ?? ''" type="text" placeholder="owner/repo" spellcheck="false"
                            class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 font-mono text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100"
                            @input="setRepo(i, $event.target.value.trim())"
                        >
                        <PackageNameInput
                            v-else :id="`pkg-${language}-${i}`" :source="row.source" :model-value="row.name"
                            :placeholder="{ cran: 'Search CRAN…', bioc: 'Search Bioconductor…', pypi: 'Name on PyPI' }[row.source]"
                            @update:model-value="(name) => update(i, { name })"
                        />
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label :for="`src-${language}-${i}`" class="text-sm font-medium text-slate-800 dark:text-slate-200">Source</label>
                        <select
                            :id="`src-${language}-${i}`" :value="row.source"
                            class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100"
                            @change="setSource(i, $event.target.value)"
                        >
                            <option v-for="s in sources" :key="s.value" :value="s.value">{{ s.label }}</option>
                        </select>
                    </div>
                </div>
                <div class="mt-3 flex items-center justify-between gap-4">
                    <UiSwitch
                        v-if="r" class="flex-1" :model-value="row.auto_attach" label="Auto-attach" description="Call library() when scaffold() runs."
                        @update:model-value="(on) => update(i, { auto_attach: on })"
                    />
                    <div v-else class="flex items-center gap-2">
                        <label :for="`ver-${language}-${i}`" class="text-sm text-slate-700 dark:text-slate-300">Version</label>
                        <input
                            :id="`ver-${language}-${i}`" :value="row.version ?? ''" type="text" placeholder="any" spellcheck="false"
                            class="w-28 rounded-md border border-slate-300 bg-white px-2 py-1 font-mono text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100"
                            @input="update(i, { version: $event.target.value.trim() || null })"
                        >
                    </div>
                    <UiButton size="sm" variant="outline" @click="remove(i)">Remove</UiButton>
                </div>
            </div>
        </div>
        <p v-else class="text-sm text-slate-500">No packages yet. Add them one at a time, or load a package set.</p>

        <div><UiButton size="sm" variant="outline" @click="add">Add package</UiButton></div>
    </div>
</template>
