<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { ChevronRightIcon } from '@heroicons/vue/20/solid';
import { PlusIcon } from '@heroicons/vue/24/outline';
import AppLayout from '@/Layouts/AppLayout.vue';
import UiButton from '@/Components/UiButton.vue';
import UiCallout from '@/Components/UiCallout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import LanguageTabs from '@/Components/LanguageTabs.vue';
import { fitsLanguage, group, groups, languageNames, languageScope, languageTab, rememberLanguage } from '@/library.js';
import { pieceCopy } from '@/pieces.js';

// Skill sets for R or Python, each opening to its skills to read and the packages they need
const props = defineProps({ sets: Array, language: String });

const page = usePage();
const copy = pieceCopy.skill_set;
const errors = computed(() => Object.values(page.props.errors ?? {}));

const language = ref(languageTab(props.language));
watch(language, rememberLanguage);
const other = computed(() => (language.value === 'r' ? 'python' : 'r'));

const sections = computed(() => groups
    .map((g) => {
        const all = props.sets.filter((s) => group(s) === g.key);
        return { ...g, all, rows: all.filter((s) => fitsLanguage(s.language, language.value)) };
    })
    .filter((g) => g.all.length || g.key !== 'community'));

// Opened sets stay open when coming back from reading a skill
const OPEN_KEY = 'skill-sets.open';
const open = reactive(new Set((() => { try { return JSON.parse(sessionStorage.getItem(OPEN_KEY) ?? '[]'); } catch { return []; } })()));
const toggle = (id) => {
    open.has(id) ? open.delete(id) : open.add(id);
    try { sessionStorage.setItem(OPEN_KEY, JSON.stringify([...open])); } catch { /* storage unavailable */ }
};

// The skills a project in this language gets from the set
const skillsFor = (set) => set.skills.filter((s) => fitsLanguage(s.language, language.value));
const read = (skill) => `/skills/${skill.id}?language=${language.value}&from=skill-sets`;
const fork = (id) => router.post(`/skill-sets/${id}/fork`);
const sourceNames = { cran: 'CRAN', bioc: 'Bioconductor', github: 'GitHub', pypi: 'PyPI' };
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

            <section v-for="section in sections" :key="section.key" class="flex flex-col gap-3">
                <h2 class="flex items-baseline gap-2 text-lg font-semibold text-slate-900 dark:text-white">
                    {{ section.label }}
                    <span v-if="section.rows.length" class="text-sm font-normal text-slate-500 dark:text-slate-400">{{ section.rows.length }}</span>
                </h2>

                <p v-if="!section.all.length" class="text-sm text-slate-500 dark:text-slate-400">
                    {{ copy.empty }}
                    <Link href="/skill-sets/create" class="font-medium text-sky-600 hover:underline dark:text-sky-400">New skill set</Link>
                </p>
                <p v-else-if="!section.rows.length" class="text-sm text-slate-500 dark:text-slate-400">
                    No skill sets here for {{ languageNames[language] }}.
                    <button type="button" class="ml-1 font-medium text-sky-600 hover:underline dark:text-sky-400" @click="language = other">Show {{ languageNames[other] }}</button>
                </p>

                <div v-else class="divide-y divide-slate-200 rounded-xl border border-slate-200 bg-white dark:divide-slate-800 dark:border-slate-800 dark:bg-slate-900">
                    <div v-for="s in section.rows" :id="`set-${s.id}`" :key="s.id" class="scroll-mt-6">
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
                                        <span class="text-xs text-slate-500">{{ languageScope(s.language) }} · {{ skillsFor(s).length }} {{ skillsFor(s).length === 1 ? 'skill' : 'skills' }}</span>
                                    </span>
                                    <span v-if="s.description" class="mt-0.5 block text-sm text-slate-600 dark:text-slate-400">{{ s.description }}</span>
                                </span>
                            </button>
                            <Link v-if="s.editable" :href="`/skill-sets/${s.id}/edit`" class="shrink-0"><UiButton size="sm" variant="outline">Edit</UiButton></Link>
                            <UiButton v-else size="sm" variant="outline" class="shrink-0" @click="fork(s.id)">Copy and edit</UiButton>
                        </div>

                        <div v-if="open.has(s.id)" :id="`set-${s.id}-skills`" class="px-4 pb-4 sm:px-5 sm:pl-12">
                            <ul class="divide-y divide-slate-100 border-t border-slate-200 dark:divide-slate-800 dark:border-slate-800">
                                <li v-for="skill in skillsFor(s)" :key="skill.key" class="flex flex-col gap-1 py-3 sm:flex-row sm:items-start sm:gap-4">
                                    <div class="min-w-0 flex-1">
                                        <div class="text-sm font-medium text-slate-900 dark:text-white">{{ skill.name }}</div>
                                        <p v-if="skill.description" class="mt-0.5 text-xs text-slate-600 dark:text-slate-400">{{ skill.descriptions?.[language] ?? skill.description }}</p>
                                    </div>
                                    <Link v-if="skill.id" :href="read(skill)" class="shrink-0 text-sm font-medium text-sky-600 hover:underline dark:text-sky-400">Read skill</Link>
                                </li>
                            </ul>
                            <p v-if="skillsFor(s).length < s.skills.length" class="mt-1 text-xs text-slate-500">
                                {{ s.skills.length - skillsFor(s).length }} more for {{ languageNames[other] }} projects only.
                            </p>

                            <div v-if="s.packages.length" class="mt-4">
                                <div class="text-xs font-semibold uppercase tracking-wider text-slate-500">Packages these skills need</div>
                                <p class="mt-1 text-sm text-slate-700 dark:text-slate-300">
                                    <template v-for="(p, i) in s.packages" :key="p.name">{{ i ? ', ' : '' }}<span class="font-mono text-xs">{{ p.name }}</span> <span class="text-xs text-slate-500">({{ sourceNames[p.source] ?? p.source }})</span></template>
                                </p>
                            </div>

                            <div class="mt-4 border-t border-slate-200 pt-2 font-mono text-xs text-slate-500 dark:border-slate-800">{{ s.handle }} v{{ s.version }}</div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
