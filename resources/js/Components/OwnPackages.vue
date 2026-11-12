<script setup>
import { computed, ref, watch } from 'vue';
import PackagesEditor from '@/Components/PackagesEditor.vue';

// Packages no package set offers, each with its source: CRAN, Bioconductor, or
// GitHub for R, PyPI or GitHub for Python. Edits form.package_definitions, the
// entries the resolver writes to packages.default_packages as they are.
const props = defineProps({ form: Object, catalog: Object });
const f = props.form;

const toRows = (definitions) => Object.entries(definitions ?? {})
    .filter(([, d]) => d && typeof d === 'object')
    .map(([name, d]) => ({ name, source: d.source, version: d.version ?? null, repo: d.repo ?? null, auto_attach: !!d.auto_attach, entry: d }));
// An entry as the resolver checks it: a repo only for GitHub, auto-attach only in
// R. Fields this editor doesn't show, such as a GitHub ref, stay.
const toDefinition = (row) => {
    const { repo: _repo, version: _version, ...kept } = row.entry ?? {};
    return {
        ...kept,
        source: row.source,
        auto_attach: f.language === 'r' && !!row.auto_attach,
        ...(row.version ? { version: row.version } : {}),
        ...(row.source === 'github' && row.repo ? { repo: row.repo } : {}),
        ...(row.source === 'github' ? {} : { ref: undefined }),
    };
};
const toDefinitions = (list) => Object.fromEntries(list.filter((row) => row.name).map((row) => [row.name, JSON.parse(JSON.stringify(toDefinition(row)))]));

// Rows keep blank names while they're typed; the form keeps only named ones.
// A removal pushed with push_all() (null) has no row and stays.
const rows = ref(toRows(f.package_definitions));
// Each watcher ignores the change the other makes, so a blank row being typed stays
let syncing = false;
watch(rows, (list) => {
    if (syncing) return;
    const removals = Object.fromEntries(Object.entries(f.package_definitions ?? {}).filter(([, d]) => d === null));
    syncing = true;
    f.package_definitions = { ...removals, ...toDefinitions(list) };
    syncing = false;
}, { deep: true, flush: 'sync' });
watch(() => f.package_definitions, (definitions) => {
    if (syncing) return;
    syncing = true;
    rows.value = toRows(definitions);
    syncing = false;
}, { flush: 'sync' });

// A package a set offers is chosen in the list above, where its set's entry applies
const offered = computed(() => {
    const names = new Set(props.catalog.packages.filter((p) => p.language === f.language).map((p) => p.name.toLowerCase()));
    return rows.value.filter((row) => row.name && names.has(row.name.toLowerCase())).map((row) => row.name);
});
const error = computed(() => f.errors.package_definitions);
</script>

<template>
    <div class="flex flex-col gap-3">
        <div>
            <h3 class="text-sm font-medium text-slate-900 dark:text-white">Other packages</h3>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Packages from no package set, such as a GitHub package or one CRAN package. scaffold() installs them with the rest.</p>
        </div>
        <PackagesEditor v-model="rows" :language="form.language" />
        <p v-if="offered.length" class="text-sm text-amber-700 dark:text-amber-400">{{ offered.join(', ') }} {{ offered.length === 1 ? 'is' : 'are' }} in a package set. Choose {{ offered.length === 1 ? 'it' : 'them' }} in the list above instead.</p>
        <p v-if="error" class="text-sm text-red-600 dark:text-red-400">{{ error }}</p>
    </div>
</template>
