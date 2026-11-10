<script setup>
import RowMenu from '@/Components/RowMenu.vue';
import { ask } from '@/confirm.js';
import { computed, reactive, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { ChevronRightIcon } from '@heroicons/vue/20/solid';
import { PlusIcon } from '@heroicons/vue/24/outline';
import AppLayout from '@/Layouts/AppLayout.vue';
import UiButton from '@/Components/UiButton.vue';
import UiCallout from '@/Components/UiCallout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { group, groups, languageNames, languageTab } from '@/library.js';
import { pieceCopy } from '@/pieces.js';

// Package sets for one language at a time, each opening to its packages
const props = defineProps({
    sets: Array, // each with packages and in_defaults: how many your defaults already have
    language: String, // the preferred language, R or Python
    defaults_count: Object, // packages in your default list, by language
});

const page = usePage();
const copy = pieceCopy.package_set;
const errors = computed(() => Object.values(page.props.errors ?? {}));
const added = computed(() => (page.props.flash.status === 'package-set-added' ? page.props.flash.added : null));

const languages = ['r', 'python'];
const language = ref(languageTab(props.language, languages));
watch(() => props.language, value => { language.value = languageTab(value, languages); });
watch(language, (value) => {
    const url = new URL(window.location.href);
    url.searchParams.set('language', value);
    window.history.replaceState(window.history.state, '', url);
});

// A set without a language fits neither tab, and shows in both with a warning
const fits = (s) => !s.language?.length || s.language.includes(language.value);
const sections = computed(() => groups
    .map((g) => {
        const all = props.sets.filter((s) => group(s) === g.key);
        return { ...g, all, rows: all.filter(fits) };
    })
    .filter((g) => g.all.length || g.key !== 'community'));
const other = computed(() => languages.find((l) => l !== language.value));

const open = reactive(new Set());
const toggle = (id) => (open.has(id) ? open.delete(id) : open.add(id));

const fork = (id) => router.post(`/package-sets/${id}/fork`);
const addToDefaults = (s) => router.post(`/package-sets/${s.id}/defaults`, {}, { preserveScroll: true });
const missing = (s) => s.packages.length - s.in_defaults;

const sourceNames = { cran: 'CRAN', bioc: 'Bioconductor', github: 'GitHub', pypi: 'PyPI' };
const tab = (on) => on
    ? 'border-sky-600 text-sky-700 dark:border-sky-400 dark:text-sky-300'
    : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-800 dark:text-slate-400 dark:hover:border-slate-600 dark:hover:text-slate-200';

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
            <UiCallout v-if="added" variant="success">
                <template v-if="added.added">Added {{ added.added }} {{ added.added === 1 ? 'package' : 'packages' }} from {{ added.set }} to your {{ languageNames[added.language] }} defaults.</template>
                <template v-else>Your {{ languageNames[added.language] }} defaults already had every package in {{ added.set }}.</template>
                <template v-if="added.added && added.kept"> {{ added.kept }} were already there and kept as you set them.</template>
                <Link href="/settings/packages" class="ml-1 font-medium underline">Review your packages</Link>
            </UiCallout>

            <div role="tablist" aria-label="Language" class="flex gap-6 border-b border-slate-200 dark:border-slate-800">
                <button
                    v-for="l in languages" :key="l" type="button" role="tab" :aria-selected="language === l"
                    class="-mb-px border-b-2 px-1 pb-2.5 text-sm font-medium transition" :class="tab(language === l)"
                    @click="language = l"
                >
                    {{ languageNames[l] }}
                </button>
            </div>

            <section v-for="section in sections" :key="section.key" class="flex flex-col gap-3">
                <h2 class="flex items-baseline gap-2 text-lg font-semibold text-slate-900 dark:text-white">
                    {{ section.label }}
                    <span v-if="section.rows.length" class="text-sm font-normal text-slate-500 dark:text-slate-400">{{ section.rows.length }}</span>
                </h2>

                <!-- Nothing at all, or nothing for this language -->
                <p v-if="!section.all.length" class="text-sm text-slate-500 dark:text-slate-400">
                    {{ copy.empty }}
                    <Link :href="`/package-sets/create?language=${language}`" class="font-medium text-sky-700 hover:underline dark:text-sky-400">New package set</Link>
                </p>
                <p v-else-if="!section.rows.length" class="text-sm text-slate-500 dark:text-slate-400">
                    <template v-if="section.key === 'framework'">Framework has no {{ languageNames[language] }} package sets yet.</template>
                    <template v-else>No {{ languageNames[language] }} package sets here.</template>
                    <button type="button" class="ml-1 font-medium text-sky-700 hover:underline dark:text-sky-400" @click="language = other">Show {{ languageNames[other] }}</button>
                </p>

                <div v-else class="divide-y divide-slate-200 rounded-xl border border-slate-200 bg-white dark:divide-slate-800 dark:border-slate-800 dark:bg-slate-900">
                    <div v-for="s in section.rows" :key="s.id">
                        <div class="flex flex-col gap-2 px-4 py-3.5 sm:flex-row sm:items-start sm:gap-3 sm:px-5">
                            <button
                                type="button" :aria-expanded="open.has(s.id)" :aria-controls="`set-${s.id}`"
                                class="flex min-w-0 flex-1 items-start gap-2 rounded text-left focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-500"
                                @click="toggle(s.id)"
                            >
                                <ChevronRightIcon class="mt-0.5 size-5 shrink-0 text-slate-400 transition-transform" :class="open.has(s.id) ? 'rotate-90' : ''" aria-hidden="true" />
                                <span class="min-w-0">
                                    <span class="flex flex-wrap items-baseline gap-x-2">
                                        <span class="font-semibold text-slate-900 dark:text-white">{{ s.name }}</span>
                                        <span class="text-xs text-slate-500 dark:text-slate-400">{{ s.packages.length }} {{ s.packages.length === 1 ? 'package' : 'packages' }}</span>
                                    </span>
                                    <span v-if="s.description" class="mt-0.5 block text-sm text-slate-600 dark:text-slate-400">{{ s.description }}</span>
                                    <span v-if="!s.language?.length" class="mt-0.5 block text-xs text-amber-700 dark:text-amber-300">No language set. Edit it to choose R or Python before using it.</span>
                                </span>
                            </button>
                            <div class="flex shrink-0 flex-wrap items-center gap-2 pl-7 sm:pl-0">
                                <span v-if="s.language?.length && !missing(s)" class="text-xs text-slate-500 dark:text-slate-400">In your defaults</span>
                                <UiButton
                                    v-else-if="s.language?.length" size="sm" variant="outline" @click="addToDefaults(s)"
                                    :title="`Adds the ${missing(s)} packages your ${languageNames[language]} defaults don't have. Nothing is removed or changed.`"
                                >
                                    Add {{ missing(s) }} to defaults
                                </UiButton>
                                <Link v-if="s.editable" :href="`/package-sets/${s.id}/edit`"><UiButton size="sm" variant="ghost">Edit</UiButton></Link>
                                <UiButton v-else size="sm" variant="ghost" @click="fork(s.id)">Copy and edit</UiButton>
                            <RowMenu :items="menuFor(s)" :label="`Actions for ${s.name}`" />
                            </div>
                        </div>

                        <div v-if="open.has(s.id)" :id="`set-${s.id}`" class="px-4 pb-4 sm:px-5 sm:pl-12">
                            <div class="border-t border-slate-200 pt-3 dark:border-slate-800">
                                <table class="w-full text-left text-sm">
                                    <thead class="text-xs text-slate-500 dark:text-slate-400">
                                        <tr><th class="py-1 pr-4 font-medium">Package</th><th class="py-1 pr-4 font-medium">Source</th><th v-if="language === 'r'" class="py-1 font-medium">Attached by scaffold()</th></tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                        <tr v-for="p in s.packages" :key="p.name">
                                            <td class="py-1.5 pr-4 font-mono text-xs text-slate-900 dark:text-slate-100">{{ p.name }}<span v-if="p.version" class="text-slate-500 dark:text-slate-400"> {{ p.version }}</span></td>
                                            <td class="py-1.5 pr-4 text-xs text-slate-600 dark:text-slate-400">{{ sourceNames[p.source] ?? p.source }}<span v-if="p.repo" class="font-mono"> · {{ p.repo }}</span></td>
                                            <td v-if="language === 'r'" class="py-1.5 text-xs text-slate-600 dark:text-slate-400">{{ p.auto_attach ? 'Yes' : 'No' }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                                <div v-if="s.requirements" class="mt-3 rounded-md border border-amber-200 px-3 py-2 text-xs text-amber-900 dark:border-amber-900/60 dark:text-amber-200">
                                    <span class="font-medium">Also needs:</span> {{ s.requirements }}
                                </div>
                                <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">
                                    Adding to your defaults adds only packages your {{ languageNames[language] }} list doesn't have ({{ missing(s) }} of {{ s.packages.length }}).
                                    Packages already there keep your settings, and nothing is removed.
                                </p>
                                <div class="mt-3 border-t border-slate-200 pt-2 font-mono text-xs text-slate-500 dark:text-slate-400 dark:border-slate-800">{{ s.handle }} v{{ s.version }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
