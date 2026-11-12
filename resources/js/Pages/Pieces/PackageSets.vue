<script setup>
import LanguageTabs from '@/Components/LanguageTabs.vue';
import SkillCategories from '@/Components/SkillCategories.vue';
import UiBadge from '@/Components/UiBadge.vue';
import { selectClass } from '@/ui.js';
import { categoryCounts } from '@/skill-catalog.js';
import RowMenu from '@/Components/RowMenu.vue';
import { ask } from '@/confirm.js';
import { computed, reactive, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { ChevronRightIcon } from '@heroicons/vue/20/solid';
import { PlusIcon, PencilSquareIcon, DocumentDuplicateIcon } from '@heroicons/vue/24/outline';
import AppLayout from '@/Layouts/AppLayout.vue';
import UiButton from '@/Components/UiButton.vue';
import UiCallout from '@/Components/UiCallout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { group, groups, languageNames, languageTab } from '@/library.js';
import { pieceCopy } from '@/pieces.js';

// Package sets for one language at a time, each opening to its packages
const props = defineProps({
    sets: Array, // each with its packages
    categories: { type: Array, default: () => [] },
    language: String, // the preferred language, R or Python
});

const page = usePage();
const copy = pieceCopy.package_set;
const errors = computed(() => Object.values(page.props.errors ?? {}));

const languages = ['r', 'python', 'stata'];
const language = ref(languageTab(props.language, languages));
watch(() => props.language, value => { language.value = languageTab(value, languages); });
watch(language, (value) => {
    const url = new URL(window.location.href);
    url.searchParams.set('language', value);
    window.history.replaceState(window.history.state, '', url);
});

const category = ref('');
const source = ref('');
const fits = (s) => !s.language?.length || s.language.includes(language.value);
const counts = computed(() => categoryCounts(props.sets.filter(fits), props.categories, (set, key) => set.tags?.includes(key)).filter(item => item.key === '' || item.count > 0));
const rows = computed(() => props.sets.filter(s => fits(s) && (!category.value || s.tags?.includes(category.value)) && (!source.value || group(s) === source.value)));

const open = reactive(new Set());
const toggle = (id) => (open.has(id) ? open.delete(id) : open.add(id));

const fork = (id) => router.post(`/package-sets/${id}/fork`);

const sourceNames = { cran: 'CRAN', bioc: 'Bioconductor', github: 'GitHub', pypi: 'PyPI', manual: 'Manual' };
const removeItem = async (item) => {
    if (await ask(`Delete ${item.name}? This can't be undone.`)) router.delete(`/package-sets/${item.id}`, { preserveScroll: true });
};
const menuFor = (item) => [
    item.editable && !item.is_system ? { label: 'Edit', href: `/package-sets/${item.id}/edit` } : { label: 'Copy and edit', onSelect: () => fork(item.id) },
    ...(item.editable && !item.is_system ? [{ label: 'Copy and edit', onSelect: () => fork(item.id) }, { divider: true }, { label: 'Delete', danger: true, onSelect: () => removeItem(item) }] : []),
];
</script>

<template>
    <Head :title="copy.title" />

    <AppLayout>
        <div class="flex flex-col gap-8">
            <PageHeader :title="copy.title" :description="copy.description" small>
                <template #actions>
                    <Link :href="`/package-sets/create?language=${language}`"><UiButton><PlusIcon class="size-4" />New package set</UiButton></Link>
                </template>
            </PageHeader>

            <UiCallout v-if="errors.length" variant="danger">{{ errors.join(' ') }}</UiCallout>

            <LanguageTabs v-model="language" :languages="languages" />
            <SkillCategories v-model="category" :categories="counts" />
            <div class="sm:w-48">
                <label for="set-source" class="sr-only">Package set source</label>
                <select id="set-source" v-model="source" :class="selectClass">
                    <option value="">All sources</option>
                    <option v-for="group in groups" :key="group.key" :value="group.key">{{ group.label }}</option>
                </select>
            </div>
            <p v-if="!rows.length" class="text-sm text-slate-500 dark:text-slate-400">No matching package sets for {{ languageNames[language] }}.</p>
            <section v-else aria-label="Package sets">
                <div class="divide-y divide-slate-800/10 rounded-xl border border-slate-800/10 bg-white dark:divide-white/15 dark:border-white/15 dark:bg-slate-950">
                    <div v-for="s in rows" :key="s.id">
                        <div class="flex items-start gap-3 px-4 py-3.5 sm:px-5">
                            <button
                                type="button" :aria-expanded="open.has(s.id)" :aria-controls="`set-${s.id}`"
                                class="flex min-w-0 flex-1 items-start gap-2 rounded text-left focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-500"
                                @click="toggle(s.id)"
                            >
                                <ChevronRightIcon class="mt-0.5 size-5 shrink-0 text-slate-400 transition-transform" :class="open.has(s.id) ? 'rotate-90' : ''" aria-hidden="true" />
                                <span class="min-w-0">
                                    <span class="flex flex-wrap items-baseline gap-x-2">
                                        <span class="font-semibold text-slate-900 dark:text-white">{{ s.name }}</span>
                                        <UiBadge v-if="group(s) === 'mine'">Yours</UiBadge>
                                        <span class="text-xs text-slate-500 dark:text-slate-400">{{ s.packages.length }} {{ s.packages.length === 1 ? 'package' : 'packages' }}</span>
                                    </span>
                                    <span v-if="s.description" class="mt-0.5 block text-sm text-slate-600 dark:text-slate-400">{{ s.description }}</span>
                                    <span v-if="!s.language?.length" class="mt-0.5 block text-xs text-amber-700 dark:text-amber-300">No language set. Edit it to choose R or Python before using it.</span>
                                </span>
                            </button>
                            <UiButton v-if="s.editable" :href="`/package-sets/${s.id}/edit`" class="shrink-0" size="sm" variant="ghost" :aria-label="`Edit ${s.name}`"><PencilSquareIcon class="size-4 sm:hidden" /><span class="hidden sm:inline">Edit</span></UiButton>
                            <UiButton v-else size="sm" variant="ghost" class="shrink-0" :aria-label="`Copy and edit ${s.name}`" @click="fork(s.id)"><DocumentDuplicateIcon class="size-4 sm:hidden" /><span class="hidden sm:inline">Copy and edit</span></UiButton>
                            <RowMenu :items="menuFor(s)" :label="`Actions for ${s.name}`" />
                        </div>

                        <div v-if="open.has(s.id)" :id="`set-${s.id}`" class="px-4 pb-4 sm:px-5 sm:pl-12">
                            <div class="border-t border-slate-200 pt-3 dark:border-slate-800">
                                <table class="w-full text-left text-sm">
                                    <thead class="text-xs text-slate-500 dark:text-slate-400">
                                        <tr><th class="py-1 pr-4 font-medium">Package</th><th class="py-1 pr-4 font-medium">Source</th></tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                        <tr v-for="p in s.packages" :key="p.name">
                                            <td class="py-1.5 pr-4 font-mono text-xs text-slate-900 dark:text-slate-100">{{ p.name }}<span v-if="p.version" class="text-slate-500 dark:text-slate-400"> {{ p.version }}</span></td>
                                            <td class="py-1.5 pr-4 text-xs text-slate-600 dark:text-slate-400">{{ sourceNames[p.source] ?? p.source }}<span v-if="p.repo" class="font-mono"> · {{ p.repo }}</span></td>
                                        </tr>
                                    </tbody>
                                </table>
                                <div v-if="s.requirements" class="mt-3 rounded-md border border-amber-200 px-3 py-2 text-xs text-amber-900 dark:border-amber-900/60 dark:text-amber-200">
                                    <span class="font-medium">Also needs:</span> {{ s.requirements }}
                                </div>
                                <div class="mt-3 border-t border-slate-200 pt-2 font-mono text-xs text-slate-500 dark:text-slate-400 dark:border-slate-800">{{ s.handle }} v{{ s.version }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
