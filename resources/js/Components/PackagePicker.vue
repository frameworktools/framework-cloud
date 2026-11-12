<script setup>
import { computed, ref, watch } from 'vue';
import PickerLayout from '@/Components/PickerLayout.vue';
import UiCheckbox from '@/Components/UiCheckbox.vue';
import UiSwitch from '@/Components/UiSwitch.vue';
import BulkCheckbox from '@/Components/BulkCheckbox.vue';
import UiTable from '@/Components/UiTable.vue';
import { categoryCounts } from '@/skill-catalog.js';
import { inputClass, selectClass } from '@/ui.js';
const props = defineProps({ packages: Array, sets: Array, categories: Array, selectedSets: Array, language: String });
const model = defineModel({ type: Object, default: () => ({}) });
const autoAttach = defineModel('autoAttach', { type: Object, default: () => ({}) });
const category = ref('selected');
const search = ref('');
const source = ref('');
const available = computed(() => props.packages.filter(pkg => pkg.language === props.language));
const inherited = pkg => pkg.sets.some(key => props.selectedSets.includes(key));
const checked = pkg => model.value[pkg.key] ?? inherited(pkg);
const attached = pkg => {
    if (pkg.key in autoAttach.value) return autoAttach.value[pkg.key];
    if (model.value[pkg.key] === true) return !!pkg.definition.auto_attach;
    let value = !!pkg.definition.auto_attach;
    for (const key of props.selectedSets) {
        const entry = props.sets.find(set => set.key === key)?.package_entries?.[props.language]?.[pkg.key];
        if (entry) value = !!entry.auto_attach;
    }
    return value;
};
const setAttached = (pkg, on) => { autoAttach.value = { ...autoAttach.value, [pkg.key]: on }; };
const selectedCount = computed(() => available.value.filter(checked).length);
const counts = computed(() => {
    const themes = categoryCounts(available.value, props.categories, (pkg, key) => pkg.categories.includes(key));
    return [{ key: 'selected', name: 'Selected', count: selectedCount.value }, ...themes.slice(1), themes[0]];
});
const sources = computed(() => [...new Set(available.value.map(pkg => pkg.definition.source).filter(Boolean))]);
const rows = computed(() => available.value.filter(pkg => (category.value === 'selected' ? checked(pkg) : (!category.value || pkg.categories.includes(category.value)))
    && (!source.value || pkg.definition.source === source.value)
    && pkg.name.toLocaleLowerCase().includes(search.value.trim().toLocaleLowerCase())));
const allSelected = computed(() => rows.value.length > 0 && rows.value.every(checked));
const someSelected = computed(() => rows.value.some(checked));
const toggle = (pkg, on) => {
    const next = { ...model.value };
    if (on && inherited(pkg)) delete next[pkg.key];
    else next[pkg.key] = on;
    model.value = next;
};
const toggleAll = on => {
    const next = { ...model.value };
    for (const pkg of rows.value) {
        if (on && inherited(pkg)) delete next[pkg.key];
        else next[pkg.key] = on;
    }
    model.value = next;
};
const toggleRow = (event, pkg) => {
    if (event.target.closest('input, button, a, label, summary, details, fieldset[disabled]')) return;
    toggle(pkg, !checked(pkg));
};
watch(() => [...props.selectedSets], (selected, previous) => {
    const added = selected.filter(key => !previous.includes(key));
    const next = { ...model.value };
    for (const pkg of available.value.filter(pkg => pkg.sets.some(key => added.includes(key)))) delete next[pkg.key];
    model.value = next;
});
</script>

<template>
    <section class="flex min-w-0 flex-col gap-4" aria-label="Individual packages">
        <div class="flex items-center justify-between gap-3">
            <h3 class="text-sm font-medium text-slate-900 dark:text-white">Packages</h3>
            <p role="status" class="text-xs text-slate-500 dark:text-slate-400">{{ selectedCount }} selected</p>
        </div>
        <p v-if="language === 'r'" class="text-xs text-slate-500 dark:text-slate-400">Auto-attach loads selected packages with library() when scaffold() runs. This option applies to R only.</p>
        <PickerLayout v-model="category" :categories="counts" label="Package themes">
            <div class="flex flex-col gap-3 lg:flex-row">
                <label class="min-w-0 flex-1"><span class="sr-only">Search packages</span><input v-model="search" type="search" placeholder="Search packages" :class="inputClass" /></label>
                <label class="lg:w-36"><span class="sr-only">Package source</span><select v-model="source" :class="selectClass"><option value="">All sources</option><option v-for="item in sources" :key="item" :value="item">{{ item }}</option></select></label>
            </div>
            <UiTable v-if="rows.length" label="Individual packages">
                <thead><tr><th class="w-8"><BulkCheckbox :all-selected="allSelected" :some-selected="someSelected" label="packages" @select="toggleAll(true)" @clear="toggleAll(false)" /></th><th>Package</th><th>Source</th><th v-if="language === 'r'">Auto-attach</th></tr></thead>
                <tbody><tr v-for="pkg in rows" :key="pkg.key" class="cursor-pointer hover:bg-slate-50 dark:hover:bg-white/5" @click="toggleRow($event, pkg)">
                    <td><UiCheckbox :id="`choose-package-${language}-${pkg.key}`" :model-value="checked(pkg)" @update:model-value="toggle(pkg, $event)" /></td>
                    <td class="whitespace-normal"><label :for="`choose-package-${language}-${pkg.key}`" class="cursor-pointer font-medium text-slate-900 dark:text-white">{{ pkg.name }}</label>
                        <p v-if="inherited(pkg) && checked(pkg)" class="mt-1 text-xs text-slate-500 dark:text-slate-400">From {{ sets.filter(set => selectedSets.includes(set.key) && pkg.sets.includes(set.key)).map(set => set.name).join(', ') }}</p>
                    </td><td>{{ pkg.definition.source }}</td>
                    <td v-if="language === 'r'"><UiSwitch class="justify-start" :model-value="checked(pkg) && attached(pkg)" :disabled="!checked(pkg)" :sr-label="`Auto-attach ${pkg.name}`" @update:model-value="setAttached(pkg, $event)" /></td>
                </tr></tbody>
            </UiTable>
            <p v-else class="py-4 text-sm text-slate-500 dark:text-slate-400">{{ category === 'selected' && !selectedCount ? 'No packages selected. Choose a set above or browse a theme.' : 'No matching packages.' }}</p>
        </PickerLayout>
    </section>
</template>
