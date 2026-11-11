<script setup>
import UiBadge from '@/Components/UiBadge.vue';
import { selectClass } from '@/ui.js';
import SkillCategories from '@/Components/SkillCategories.vue';
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
import LanguageTabs from '@/Components/LanguageTabs.vue';
import { fitsLanguage, group, groups, languageNames, languageScope, languageTab, rememberLanguage } from '@/library.js';
import { pieceCopy } from '@/pieces.js';

// Skill sets for R or Python, each opening to its skills to read and the packages they need
const props = defineProps({ sets: Array, language: String, categories: Array });

const page = usePage();
const copy = pieceCopy.skill_set;
const errors = computed(() => Object.values(page.props.errors ?? {}));

const language = ref(languageTab(props.language));
watch(language, rememberLanguage);
watch(() => props.language, value => { language.value = languageTab(value); });
const category = ref(props.categories.some(c => c.key === new URLSearchParams(window.location.search).get('category')) ? new URLSearchParams(window.location.search).get('category') : '');
const counts = computed(() => categoryCounts(props.sets.filter(s => fitsLanguage(s.language, language.value)), props.categories, (set, key) => set.category === key));
const source = ref('');
const rows = computed(() => props.sets.filter(set => fitsLanguage(set.language, language.value)
    && (!category.value || set.category === category.value)
    && (!source.value || group(set) === source.value)));

// Opened sets stay open when coming back from reading a skill
const OPEN_KEY = 'skill-sets.open';
const open = reactive(new Set((() => { try { return JSON.parse(sessionStorage.getItem(OPEN_KEY) ?? '[]'); } catch { return []; } })()));
const toggle = (id) => {
    open.has(id) ? open.delete(id) : open.add(id);
    try { sessionStorage.setItem(OPEN_KEY, JSON.stringify([...open])); } catch { /* storage unavailable */ }
};

// The skills a project in this language gets from the set
const skillsFor = (set) => set.skills.filter((s) => fitsLanguage(s.language, language.value) && (!s.set_languages || s.set_languages.includes(language.value)));
const packagesFor = set => set.packages_by_language?.[language.value] ?? set.packages;
const read = (skill) => `/skills/${skill.id}?language=${language.value}&from=skill-sets`;
const fork = (id) => router.post(`/skill-sets/${id}/fork`, { language: language.value });
const sourceNames = { cran: 'CRAN', bioc: 'Bioconductor', github: 'GitHub', pypi: 'PyPI' };

const removeItem = async (item) => {
    if (await ask(`Delete ${item.name}? This can't be undone.`)) router.delete(`/skill-sets/${item.id}`, { preserveScroll: true });
};
const menuFor = (item) => [
    item.editable && !item.is_system ? { label: 'Edit', href: `/skill-sets/${item.id}/edit?language=${language.value}` } : { label: 'Copy and edit', onSelect: () => fork(item.id) },
    ...(item.editable && !item.is_system ? [{ label: 'Copy and edit', onSelect: () => fork(item.id) }, { divider: true }, { label: 'Delete', danger: true, onSelect: () => removeItem(item) }] : []),
];
</script>

<template>
    <Head :title="copy.title" />

    <AppLayout>
        <div class="flex flex-col gap-8">
            <PageHeader :title="copy.title" :description="copy.description" small>
                <template #actions>
                    <Link href="/skill-sets/create"><UiButton><PlusIcon class="size-4" />New skill set</UiButton></Link>
                </template>
            </PageHeader>

            <UiCallout v-if="errors.length" variant="danger">{{ errors.join(' ') }}</UiCallout>

            <LanguageTabs v-model="language" />
            <SkillCategories v-model="category" :categories="counts" />
            <div class="sm:w-48">
                <label for="set-source" class="sr-only">Skill set source</label>
                <select id="set-source" v-model="source" :class="selectClass">
                    <option value="">All sources</option>
                    <option v-for="group in groups" :key="group.key" :value="group.key">{{ group.label }}</option>
                </select>
            </div>
            <p v-if="!rows.length" class="text-sm text-slate-500 dark:text-slate-400">No matching skill sets for {{ languageNames[language] }}.</p>
            <section v-else aria-label="Skill sets">
                <div class="divide-y divide-slate-800/10 rounded-xl border border-slate-800/10 bg-white dark:divide-white/15 dark:border-white/15 dark:bg-slate-950">
                    <div v-for="s in rows" :id="`set-${s.id}`" :key="s.id" class="scroll-mt-6">
                        <div class="flex items-start gap-3 px-4 py-3.5 sm:px-5">
                            <button
                                type="button" :aria-expanded="open.has(s.id)" :aria-controls="`set-${s.id}-skills`"
                                class="flex min-w-0 flex-1 items-start gap-2 rounded text-left focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-500"
                                @click="toggle(s.id)"
                            >
                                <ChevronRightIcon class="mt-0.5 size-5 shrink-0 text-slate-400 transition-transform" :class="open.has(s.id) ? 'rotate-90' : ''" aria-hidden="true" />
                                <span class="min-w-0">
                                    <span class="flex flex-wrap items-baseline gap-x-2">
                                        <span class="font-semibold text-slate-900 dark:text-white">{{ s.name }}</span>
                                        <UiBadge v-if="group(s) === 'mine'">Yours</UiBadge>
                                        <span class="text-xs text-slate-500 dark:text-slate-400">{{ languageScope(s.language) }} · {{ skillsFor(s).length }} {{ skillsFor(s).length === 1 ? 'skill' : 'skills' }}</span>
                                    </span>
                                    <span v-if="s.description" class="mt-0.5 block text-sm text-slate-600 dark:text-slate-400">{{ s.description }}</span>
                                </span>
                            </button>
                            <UiButton v-if="s.editable" :href="`/skill-sets/${s.id}/edit?language=${language}`" class="shrink-0" size="sm" variant="ghost" :aria-label="`Edit ${s.name}`"><PencilSquareIcon class="size-4 sm:hidden" /><span class="hidden sm:inline">Edit</span></UiButton>
                            <UiButton v-else size="sm" variant="ghost" class="shrink-0" :aria-label="`Copy and edit ${s.name}`" @click="fork(s.id)"><DocumentDuplicateIcon class="size-4 sm:hidden" /><span class="hidden sm:inline">Copy and edit</span></UiButton>
                            <RowMenu :items="menuFor(s)" :label="`Actions for ${s.name}`" />
                        </div>

                        <div v-if="open.has(s.id)" :id="`set-${s.id}-skills`" class="px-4 pb-4 sm:px-5 sm:pl-12">
                            <ul class="divide-y divide-slate-100 border-t border-slate-200 dark:divide-slate-800 dark:border-slate-800">
                                <li v-for="skill in skillsFor(s)" :key="skill.key" class="flex flex-col gap-1 py-3 sm:flex-row sm:items-start sm:gap-4">
                                    <div class="min-w-0 flex-1">
                                        <div class="text-sm font-medium text-slate-900 dark:text-white">{{ skill.name }}</div>
                                        <p v-if="skill.description" class="mt-0.5 text-xs text-slate-600 dark:text-slate-400">{{ skill.summary ?? skill.descriptions?.[language] ?? skill.description }}</p>
                                    </div>
                                    <Link v-if="skill.id" :href="read(skill)" class="shrink-0 text-sm font-medium text-sky-700 hover:underline dark:text-sky-400">Read skill</Link>
                                </li>
                            </ul>
                            <p v-if="skillsFor(s).length < s.skills.length" class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                {{ s.skills.length - skillsFor(s).length }} skills for other languages.
                            </p>

                            <div v-if="packagesFor(s).length" class="mt-4">
                                <div class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Packages these skills need</div>
                                <p class="mt-1 text-sm text-slate-700 dark:text-slate-300">
                                    <template v-for="(p, i) in packagesFor(s)" :key="p.name">{{ i ? ', ' : '' }}<span class="font-mono text-xs">{{ p.name }}</span> <span class="text-xs text-slate-500 dark:text-slate-400">({{ sourceNames[p.source] ?? p.source }})</span></template>
                                </p>
                            </div>

                            <div class="mt-4 border-t border-slate-200 pt-2 font-mono text-xs text-slate-500 dark:text-slate-400 dark:border-slate-800">{{ s.handle }} v{{ s.version }}</div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
