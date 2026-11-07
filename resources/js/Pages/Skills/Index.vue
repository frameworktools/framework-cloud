<script setup>
import { computed, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { PlusIcon, MagnifyingGlassIcon, DocumentDuplicateIcon, PencilSquareIcon } from '@heroicons/vue/24/outline';
import AppLayout from '@/Layouts/AppLayout.vue';
import UiButton from '@/Components/UiButton.vue';
import UiCallout from '@/Components/UiCallout.vue';
import UiTable from '@/Components/UiTable.vue';
import UiLink from '@/Components/UiLink.vue';
import PageHeader from '@/Components/PageHeader.vue';
import LanguageTabs from '@/Components/LanguageTabs.vue';
import GitHubImport from '@/Components/GitHubImport.vue';
import { group, groups, languageNames, languageTab, rememberLanguage } from '@/library.js';
import { filterSkills } from '@/skill-catalog.js';
import { inputClass, selectClass } from '@/ui.js';

const props = defineProps({ skills: Array, language: String, tagOptions: Array });
const page = usePage();
const errors = computed(() => Object.values(page.props.errors ?? {}));
const language = ref(languageTab(props.language));
watch(language, rememberLanguage);
watch(() => props.language, value => { language.value = languageTab(value); });
const search = ref('');
const source = ref('');
const selectedTags = ref(new URLSearchParams(window.location.search).getAll('tag').filter(key => props.tagOptions.some(tag => tag.key === key)));
const importing = ref(false);
const rows = computed(() => filterSkills(props.skills, { language: language.value, source: source.value, search: search.value, tags: selectedTags.value }));
const sections = computed(() => groups.map(section => ({ ...section, rows: rows.value.filter(skill => group(skill) === section.key) })).filter(section => section.rows.length));
const filtered = computed(() => search.value || source.value || selectedTags.value.length);
const toggleTag = key => { selectedTags.value = selectedTags.value.includes(key) ? selectedTags.value.filter(tag => tag !== key) : [...selectedTags.value, key]; };
const clear = () => { search.value = ''; source.value = ''; selectedTags.value = []; };
const read = skill => `/skills/${skill.id}?language=${language.value}`;
const fork = id => router.post(`/skills/${id}/fork`);
</script>

<template>
    <Head title="Skills" />
    <AppLayout>
        <div class="flex min-w-0 flex-col gap-7">
            <PageHeader title="Skills" small>
                <template #description>Agent instructions for R, Python, and Stata. Install bundles through <UiLink href="/skill-sets">skill sets</UiLink>.</template>
                <template #actions>
                    <UiButton variant="ghost" @click="importing = !importing" :aria-expanded="importing" aria-controls="skill-import">Import</UiButton>
                    <UiButton href="/skills/create"><PlusIcon class="size-4" />New skill</UiButton>
                </template>
            </PageHeader>
            <UiCallout v-if="errors.length" variant="danger">{{ errors.join(' ') }}</UiCallout>
            <div v-if="importing" id="skill-import" class="max-w-2xl">
                <GitHubImport action="/skills/import" label="GitHub link" placeholder="https://github.com/owner/repo/tree/main/skills/my-skill" />
            </div>
            <LanguageTabs v-model="language" />
            <section aria-label="Skill filters" class="flex flex-col gap-5">
                <div class="flex flex-col gap-3 sm:flex-row">
                    <div class="relative min-w-0 flex-1">
                        <label for="skill-search" class="sr-only">Search skills</label>
                        <MagnifyingGlassIcon class="pointer-events-none absolute top-3 left-3 size-4 text-slate-400" aria-hidden="true" />
                        <input id="skill-search" v-model="search" type="search" placeholder="Search skills" :class="[inputClass, 'pl-9']" />
                    </div>
                    <div class="sm:w-48">
                        <label for="skill-source" class="sr-only">Skill source</label>
                        <select id="skill-source" v-model="source" :class="selectClass">
                            <option value="">All sources</option>
                            <option v-for="section in groups" :key="section.key" :value="section.key">{{ section.label }}</option>
                        </select>
                    </div>
                </div>
                <fieldset>
                    <legend class="sr-only">Tags</legend>
                    <div class="mb-2 flex items-center justify-between gap-2">
                        <span class="text-sm font-medium text-slate-800 dark:text-white">Tags <span v-if="selectedTags.length > 1" class="ml-1 font-normal text-slate-500 dark:text-slate-400">All selected tags</span></span>
                        <UiLink v-if="page.props.auth.user.is_admin" href="/admin/skill-tags" class="text-xs">Manage tags</UiLink>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button v-for="tag in tagOptions" :key="tag.id" type="button" :aria-pressed="selectedTags.includes(tag.key)" @click="toggleTag(tag.key)"
                            class="rounded-md border px-2.5 py-1 text-xs font-medium transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-600"
                            :class="selectedTags.includes(tag.key) ? 'border-slate-800 bg-slate-800 text-white dark:border-white dark:bg-white dark:text-slate-900' : 'border-slate-800/10 text-slate-600 hover:border-slate-400 dark:border-white/15 dark:text-slate-400 dark:hover:border-slate-500'">{{ tag.name }}</button>
                    </div>
                </fieldset>
            </section>
            <div class="flex min-h-8 items-center justify-between border-b border-slate-800/10 pb-3 text-sm dark:border-white/15">
                <p role="status" aria-live="polite" class="text-slate-500 dark:text-slate-400"><span class="font-mono">{{ rows.length }}</span> {{ rows.length === 1 ? 'skill' : 'skills' }} · {{ languageNames[language] }}</p>
                <UiButton v-if="filtered" variant="ghost" size="sm" @click="clear">Clear filters</UiButton>
            </div>
            <section v-for="section in sections" :key="section.key" class="min-w-0">
                <h2 class="mb-2 text-base font-medium text-slate-900 dark:text-white">{{ section.label }} <span class="ml-1 font-mono text-xs font-normal text-slate-500 dark:text-slate-400">{{ section.rows.length }}</span></h2>
                <UiTable :label="`${section.label} skills`" class="skill-results">
                    <thead><tr><th>Skill</th><th class="hidden lg:table-cell">Tags</th><th class="actions"><span class="sr-only">Actions</span></th></tr></thead>
                    <tbody><tr v-for="skill in section.rows" :key="skill.id">
                        <td class="min-w-0 whitespace-normal">
                            <Link :href="read(skill)" class="font-medium text-slate-900 hover:underline dark:text-white">{{ skill.name }}</Link>
                            <p class="mt-1 max-w-xl text-sm leading-relaxed">{{ skill.summary ?? skill.descriptions?.[language] ?? skill.description }}</p>
                            <div class="mt-2 flex flex-wrap gap-x-3 gap-y-1 lg:hidden">
                                <button v-for="tag in skill.tags" :key="tag.id" type="button" class="inline-block whitespace-nowrap py-1 text-xs underline decoration-slate-300 underline-offset-2 dark:decoration-slate-600" :aria-pressed="selectedTags.includes(tag.key)" @click="toggleTag(tag.key)">{{ tag.name }}</button>
                            </div>
                        </td>
                        <td class="hidden max-w-56 whitespace-normal lg:table-cell"><div class="flex flex-wrap gap-x-3 gap-y-1">
                            <button v-for="tag in skill.tags" :key="tag.id" type="button" class="inline-block whitespace-nowrap py-1 text-xs underline decoration-slate-300 underline-offset-2 hover:text-slate-900 dark:decoration-slate-600 dark:hover:text-white" :aria-pressed="selectedTags.includes(tag.key)" @click="toggleTag(tag.key)">{{ tag.name }}</button>
                        </div></td>
                        <td class="actions align-top">
                            <UiButton v-if="skill.editable" :href="`/skills/${skill.id}/edit?language=${language}`" :aria-label="`Edit ${skill.name}`" size="sm" variant="ghost"><PencilSquareIcon class="sm:hidden" /><span class="hidden sm:inline">Edit</span></UiButton>
                            <UiButton v-else size="sm" variant="ghost" :aria-label="`Copy and edit ${skill.name}`" @click="fork(skill.id)"><DocumentDuplicateIcon class="sm:hidden" /><span class="hidden sm:inline">Copy and edit</span></UiButton>
                        </td>
                    </tr></tbody>
                </UiTable>
            </section>
            <div v-if="!rows.length" class="py-8 text-sm text-slate-500 dark:text-slate-400">
                <h2 class="mb-2 text-base font-medium text-slate-900 dark:text-white">No matching skills</h2>
                <p>Try another search, source, or tag.</p>
                <UiButton v-if="filtered" variant="ghost" class="mt-3" @click="clear">Clear filters</UiButton>
                <UiButton v-else href="/skills/create" variant="ghost" class="mt-3">Create a {{ languageNames[language] }} skill</UiButton>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
@media (min-width: 1024px) {
    .skill-results :deep(table) { table-layout: fixed; }
    .skill-results :deep(th:first-child) { width: 60%; }
    .skill-results :deep(th:last-child) { width: 8.5rem; }
}
@media (max-width: 639px) {
    .skill-results :deep(table) { table-layout: fixed; }
    .skill-results :deep(th:last-child), .skill-results :deep(td:last-child) { width: 3rem; }
}
</style>
