<script setup>
import { computed, ref, watch } from 'vue';
import { ChevronDownIcon } from '@heroicons/vue/20/solid';
import UiCheckbox from '@/Components/UiCheckbox.vue';
import { fitsLanguage } from '@/library.js';
import { inputClass } from '@/ui.js';
const props = defineProps({ sets: Array, language: String, label: String, tags: { type: Array, default: () => [] }, skills: { type: Array, default: () => [] }, kind: { type: String, default: 'package' } });
const model = defineModel({ type: Array, default: () => [] });
const selectedTag = ref('');
const search = ref('');
const active = ref(null);
const available = computed(() => props.sets.filter(set => fitsLanguage(set.language, props.language)
    && !(set.key === 'framework/ggplot2' && props.sets.some(other => other.key === 'framework/visualization'))));
const tagGroups = computed(() => ['ecosystem', 'theme'].map(kind => ({
    kind, tags: props.tags.filter(tag => (tag.kind ?? 'theme') === kind && available.value.some(set => set.tags?.includes(tag.key))),
})).filter(group => group.tags.length));
const rows = computed(() => available.value.filter(set => (!selectedTag.value || set.tags?.includes(selectedTag.value)) && [set.name, set.description].join(' ').toLocaleLowerCase().includes(search.value.trim().toLocaleLowerCase())));
const current = computed(() => rows.value.find(set => set.key === active.value) ?? rows.value[0]);
const selectedCount = computed(() => available.value.filter(set => model.value.includes(set.key)).length);
const contents = computed(() => {
    if (!current.value) return [];
    if (props.kind === 'package') return (current.value.package_members?.[props.language] ?? current.value.packages ?? []).map(name => ({ key: name, name }));
    return (current.value.members?.[props.language] ?? []).map(key => {
        const skill = props.skills.find(skill => skill.skill === key && fitsLanguage(skill.language, props.language));
        return { key, name: skill?.name ?? key, description: skill?.summary ?? skill?.description };
    });
});
const toggle = (key, on) => { active.value = key; model.value = on ? [...new Set([...model.value, key])] : model.value.filter(value => value !== key); };
const toggleTag = key => { selectedTag.value = key; };
watch(() => props.language, () => { selectedTag.value = ''; active.value = null; search.value = ''; });
</script>

<template>
    <details open class="group/sets min-w-0" :aria-label="label">
        <summary class="flex cursor-pointer list-none items-center justify-between gap-3 [&::-webkit-details-marker]:hidden">
            <span class="flex items-center gap-2 text-sm font-medium text-slate-900 dark:text-white"><ChevronDownIcon class="size-4 -rotate-90 transition group-open/sets:rotate-0" />{{ label }}</span>
            <span class="text-xs text-slate-500 dark:text-slate-400">{{ selectedCount }} selected</span>
        </summary>
        <div class="mt-3 flex min-w-0 flex-col gap-4">
            <p class="text-sm text-slate-500 dark:text-slate-400">Quickly add sets of {{ kind === 'package' ? 'packages' : 'skills' }}. You can review them below.</p>
            <div class="flex flex-wrap items-center gap-1">
                <button type="button" class="rounded-md px-3 py-1.5 text-sm font-medium focus-visible:outline-2 focus-visible:outline-sky-600" :class="!selectedTag ? 'bg-slate-800 text-white dark:bg-white dark:text-slate-900' : 'text-slate-600 dark:text-slate-400'" :aria-pressed="!selectedTag" @click="selectedTag = ''">All</button>
                <div v-for="group in tagGroups" :key="group.kind" class="contents" role="group" :aria-label="group.kind === 'ecosystem' ? 'Ecosystems' : 'Themes'">
                    <button v-for="tag in group.tags" :key="tag.key" type="button" :aria-pressed="selectedTag === tag.key" class="rounded-md px-3 py-1.5 text-sm font-medium transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-600" :class="selectedTag === tag.key ? 'bg-slate-800 text-white dark:bg-white dark:text-slate-900' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-white/5'" @click="toggleTag(tag.key)">{{ tag.name }}</button>
                </div>
            </div>
            <label><span class="sr-only">Search {{ label.toLowerCase() }}</span><input v-model="search" type="search" :placeholder="`Search ${label.toLowerCase()}`" :class="inputClass" /></label>
            <div v-if="rows.length" class="grid min-w-0 gap-5 md:grid-cols-[minmax(0,2fr)_minmax(0,3fr)] md:gap-6">
                <div class="max-h-80 overflow-y-auto pr-1 md:max-h-96" :aria-label="`${label} list`">
                    <div v-for="set in rows" :key="set.key" class="flex items-center gap-3 rounded-md py-1 pl-2" :class="current?.key === set.key ? 'bg-sky-50 dark:bg-sky-950/60' : ''">
                        <UiCheckbox :model-value="model.includes(set.key)" :sr-label="`Select ${set.name}`" @update:model-value="toggle(set.key, $event)" />
                        <button type="button" class="min-w-0 flex-1 py-2 pr-3 text-left text-sm focus-visible:outline-2 focus-visible:outline-sky-600" :class="current?.key === set.key ? 'font-medium text-sky-800 dark:text-sky-300' : 'text-slate-700 dark:text-slate-300'" :aria-pressed="current?.key === set.key" @click="active = set.key">{{ set.name }}</button>
                    </div>
                </div>
                <section class="min-w-0 border-t border-slate-800/10 pt-4 md:border-t-0 md:border-l md:pt-0 md:pl-6 dark:border-white/15" :aria-label="`${label} contents`" aria-live="polite">
                    <h4 class="text-sm font-medium text-slate-900 dark:text-white">{{ current.name }}</h4>
                    <p class="mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400">{{ current.description }}</p>
                    <p class="mt-4 text-xs text-slate-500 dark:text-slate-400">{{ contents.length }} {{ kind === 'package' ? 'packages' : 'skills' }}</p>
                    <ul class="mt-2 max-h-72 divide-y divide-slate-800/10 overflow-y-auto dark:divide-white/15">
                        <li v-for="item in contents" :key="item.key" class="py-2 text-sm text-slate-800 dark:text-slate-200"><span class="break-words">{{ item.name }}</span><p v-if="item.description" class="mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400">{{ item.description }}</p></li>
                    </ul>
                    <p v-if="!contents.length" class="mt-2 text-xs text-slate-500 dark:text-slate-400">No {{ kind === 'package' ? 'packages' : 'skills' }} for this language.</p>
                </section>
            </div>
            <p v-else class="text-sm text-slate-500 dark:text-slate-400">No matching {{ label.toLowerCase() }}.</p>
        </div>
    </details>
</template>
