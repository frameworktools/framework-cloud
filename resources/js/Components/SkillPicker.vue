<script setup>
import { computed, ref, watch } from 'vue';
import PickerLayout from '@/Components/PickerLayout.vue';
import UiCheckbox from '@/Components/UiCheckbox.vue';
import BulkCheckbox from '@/Components/BulkCheckbox.vue';
import UiBadge from '@/Components/UiBadge.vue';
import UiTable from '@/Components/UiTable.vue';
import { categoryCounts, filterSkills } from '@/skill-catalog.js';
import { groups } from '@/library.js';
import { inputClass, selectClass } from '@/ui.js';

const props = defineProps({ skills: Array, sets: Array, categories: Array, language: String });
const model = defineModel({ type: Array, default: () => [] });
const search = ref('');
const source = ref('');
const category = ref('selected');
const selectedSets = defineModel('selectedSets', { type: Array, default: () => [] });
const excludedSkills = defineModel('excludedSkills', { type: Array, default: () => [] });
const included = computed(() => {
    const members = new Map();
    for (const set of props.sets.filter(set => selectedSets.value.includes(set.key))) {
        for (const key of set.members?.[props.language] ?? []) {
            const names = members.get(key) ?? [];
            members.set(key, [...names, set.name]);
        }
    }
    return members;
});
const checked = skill => !excludedSkills.value.includes(skill.key) && (model.value.includes(skill.key) || included.value.has(skill.skill));
const languageSkills = computed(() => filterSkills(props.skills, { language: props.language }));
const selectedCount = computed(() => languageSkills.value.filter(checked).length);
const counts = computed(() => {
    const themes = categoryCounts(languageSkills.value, props.categories, (skill, key) => skill.tags?.some(tag => tag.key === key));
    return [{ key: 'selected', name: 'Selected', count: selectedCount.value }, ...themes.slice(1), themes[0]];
});
const rows = computed(() => filterSkills(props.skills, { language: props.language, search: search.value, source: source.value, category: category.value === 'selected' ? '' : category.value })
    .filter(skill => category.value !== 'selected' || checked(skill)));
const allSelected = computed(() => rows.value.length > 0 && rows.value.every(checked));
const partlySelected = computed(() => !allSelected.value && rows.value.some(checked));
const toggle = (skill, on) => {
    excludedSkills.value = excludedSkills.value.filter(key => key !== skill.key);
    model.value = model.value.filter(key => key !== skill.key);
    if (on && !included.value.has(skill.skill)) model.value = [...model.value, skill.key];
    if (!on && included.value.has(skill.skill)) excludedSkills.value = [...excludedSkills.value, skill.key];
};
const toggleAll = on => {
    const visible = rows.value;
    const keys = new Set(visible.map(skill => skill.key));
    const remaining = model.value.filter(key => !keys.has(key));
    model.value = on ? [...new Set([...remaining, ...visible.filter(skill => !included.value.has(skill.skill)).map(skill => skill.key)])] : remaining;
    const excluded = excludedSkills.value.filter(key => !keys.has(key));
    excludedSkills.value = on ? excluded : [...new Set([...excluded, ...visible.filter(skill => included.value.has(skill.skill)).map(skill => skill.key)])];
};
watch(() => [...selectedSets.value], (selected, previous) => {
    const added = props.sets.filter(set => selected.includes(set.key) && !previous.includes(set.key));
    const keys = languageSkills.value.filter(skill => added.some(set => set.members?.[props.language]?.includes(skill.skill))).map(skill => skill.key);
    excludedSkills.value = excludedSkills.value.filter(key => !keys.includes(key));
});
const toggleRow = (event, skill) => {
    if (event.target.closest('input, button, a, label, summary, details, fieldset[disabled]')) return;
    toggle(skill, !checked(skill));
};

</script>

<template>
    <section class="flex min-w-0 flex-col gap-4" aria-label="Individual skills">
        <div class="flex items-center justify-between gap-3">
            <h3 class="text-sm font-medium text-slate-900 dark:text-white">Skills</h3>
            <p role="status" class="text-xs text-slate-500 dark:text-slate-400">{{ selectedCount }} selected</p>
        </div>
        <PickerLayout v-model="category" :categories="counts" label="Skill themes">
            <div class="flex flex-col gap-3 lg:flex-row">
                <label class="min-w-0 flex-1"><span class="sr-only">Search skills</span><input v-model="search" type="search" placeholder="Search skills" :class="inputClass" /></label>
                <label class="lg:w-36"><span class="sr-only">Skill source</span><select v-model="source" :class="selectClass">
                    <option value="">All sources</option>
                    <option v-for="group in groups" :key="group.key" :value="group.key">{{ group.label }}</option>
                </select></label>
            </div>
            <UiTable v-if="rows.length" label="Individual skills">
                <thead><tr><th class="w-8"><BulkCheckbox :all-selected="allSelected" :some-selected="allSelected || partlySelected" label="skills" @select="toggleAll(true)" @clear="toggleAll(false)" /></th><th>Skill</th></tr></thead>
                <tbody>
                    <tr v-for="skill in rows" :key="skill.key" class="cursor-pointer hover:bg-slate-50 dark:hover:bg-white/5" @click="toggleRow($event, skill)">
                        <td class="align-top"><UiCheckbox :id="`choose-skill-${skill.id}`" :model-value="checked(skill)" @update:model-value="toggle(skill, $event)" /></td>
                        <td class="whitespace-normal">
                            <label :for="`choose-skill-${skill.id}`" class="cursor-pointer font-medium text-slate-900 dark:text-white">{{ skill.name }}</label>
                            <UiBadge v-if="skill.editable && !skill.is_system" class="ml-2">Yours</UiBadge>
                            <p class="mt-1 text-sm leading-relaxed">{{ skill.summary ?? skill.descriptions?.[language] ?? skill.description }}</p>
                            <p v-if="included.has(skill.skill) && checked(skill)" class="mt-1 text-xs text-slate-500 dark:text-slate-400">From {{ included.get(skill.skill).join(', ') }}</p>
                        </td>
                    </tr>
                </tbody>
            </UiTable>
            <p v-else class="py-4 text-sm text-slate-500 dark:text-slate-400">{{ category === 'selected' && !selectedCount ? 'No skills selected. Choose a set above or browse a theme.' : 'No matching skills.' }}</p>
        </PickerLayout>
    </section>
</template>
