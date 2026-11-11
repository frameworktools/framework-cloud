<script setup>
import { computed, ref } from 'vue';
import PickerLayout from '@/Components/PickerLayout.vue';
import UiCheckbox from '@/Components/UiCheckbox.vue';
import UiTable from '@/Components/UiTable.vue';
import { fitsLanguage, group, groups } from '@/library.js';
import { categoryCounts } from '@/skill-catalog.js';
import { inputClass, selectClass } from '@/ui.js';
const props = defineProps({ sets: Array, categories: Array, language: String, label: String });
const model = defineModel({ type: Array, default: () => [] });
const category = ref('');
const search = ref('');
const source = ref('');
const available = computed(() => props.sets.filter(set => fitsLanguage(set.language, props.language)));
const counts = computed(() => categoryCounts(available.value, props.categories, (set, key) => set.category === key));
const rows = computed(() => available.value.filter(set => (!category.value || set.category === category.value)
    && (!source.value || group({ ...set, is_system: set.system }) === source.value)
    && search.value.toLocaleLowerCase().trim().split(/\s+/).every(term => [set.name, set.description, ...(set.packages ?? [])].join(' ').toLocaleLowerCase().includes(term))));
const selectedCount = computed(() => available.value.filter(set => model.value.includes(set.key)).length);
const toggle = (key, on) => { model.value = on ? [...new Set([...model.value, key])] : model.value.filter(value => value !== key); };
const toggleRow = (event, set) => {
    if (event.target.closest('input, button, a, label, summary, details, fieldset[disabled]')) return;
    toggle(set.key, !model.value.includes(set.key));
};
</script>

<template>
    <PickerLayout v-model="category" :categories="counts" :label="label === 'Package sets' ? 'Package types' : 'Skill set types'">
        <div class="flex flex-col gap-3 lg:flex-row">
            <label class="min-w-0 flex-1"><span class="sr-only">Search {{ label.toLowerCase() }}</span><input v-model="search" type="search" :placeholder="`Search ${label.toLowerCase()}`" :class="inputClass" /></label>
            <label class="lg:w-36"><span class="sr-only">Source</span><select v-model="source" :class="selectClass">
                <option value="">All sources</option>
                <option v-for="group in groups" :key="group.key" :value="group.key">{{ group.label }}</option>
            </select></label>
        </div>
        <div class="flex flex-wrap items-center justify-between gap-3 text-xs text-slate-500 dark:text-slate-400">
            <p role="status">{{ selectedCount }} selected</p>
        </div>
        <UiTable v-if="rows.length" :label="label">
            <thead><tr><th class="w-8"><span class="sr-only">Selected</span></th><th>{{ label === 'Package sets' ? 'Package set' : 'Skill set' }}</th></tr></thead>
            <tbody><tr v-for="set in rows" :key="set.key" class="cursor-pointer hover:bg-slate-50 dark:hover:bg-white/5" @click="toggleRow($event, set)">
                <td class="align-top"><UiCheckbox :id="`choose-set-${set.id}`" :model-value="model.includes(set.key)" @update:model-value="toggle(set.key, $event)" /></td>
                <td class="whitespace-normal"><label :for="`choose-set-${set.id}`" class="cursor-pointer font-medium text-slate-900 dark:text-white">{{ set.name }}</label>
                    <p class="mt-1 text-sm leading-relaxed">{{ set.description }}</p>
                    <details v-if="set.packages?.length" class="mt-2 text-xs text-slate-500 dark:text-slate-400"><summary class="cursor-pointer">{{ set.packages.length }} packages</summary><p class="mt-2 font-mono leading-relaxed">{{ set.packages.join(', ') }}</p></details>
                </td>
            </tr></tbody>
        </UiTable>
        <p v-else class="py-4 text-sm text-slate-500 dark:text-slate-400">No matching {{ label.toLowerCase() }}.</p>
    </PickerLayout>
</template>
