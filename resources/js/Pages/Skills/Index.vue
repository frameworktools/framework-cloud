<script setup>
import { computed, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { ChevronRightIcon } from '@heroicons/vue/20/solid';
import { PlusIcon } from '@heroicons/vue/24/outline';
import AppLayout from '@/Layouts/AppLayout.vue';
import UiButton from '@/Components/UiButton.vue';
import UiCallout from '@/Components/UiCallout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import LanguageTabs from '@/Components/LanguageTabs.vue';
import GitHubImport from '@/Components/GitHubImport.vue';
import { fitsLanguage, group, groups, languageNames, languageScope, languageTab, rememberLanguage } from '@/library.js';

// Skills for R or Python; any-language skills show in both. Each opens to read in full.
const props = defineProps({ skills: Array, language: String });

const page = usePage();
const errors = computed(() => Object.values(page.props.errors ?? {}));

const language = ref(languageTab(props.language));
watch(language, rememberLanguage);
const other = computed(() => (language.value === 'r' ? 'python' : 'r'));

// From Framework and Yours always show; Shared by others only when there are some
const sections = computed(() => groups
    .map((g) => {
        const all = props.skills.filter((s) => group(s) === g.key);
        return { ...g, all, rows: all.filter((s) => fitsLanguage(s.language, language.value)) };
    })
    .filter((g) => g.all.length || g.key !== 'community'));

const read = (s) => `/skills/${s.id}?language=${language.value}`;
const fork = (id) => router.post(`/skills/${id}/fork`);
</script>

<template>
    <Head title="Skills" />

    <AppLayout>
        <div class="flex flex-col gap-8">
            <PageHeader title="Skills" small>
                <template #description>
                    Instructions agents load when a task matches. Group them in
                    <Link href="/skill-sets" class="text-sky-600 hover:underline dark:text-sky-400">skill sets</Link>.
                </template>
                <template #actions>
                    <Link href="/skills/create"><UiButton><PlusIcon class="size-4" />New skill</UiButton></Link>
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
                    You haven't made a skill yet.
                    <Link href="/skills/create" class="font-medium text-sky-600 hover:underline dark:text-sky-400">New skill</Link>
                </p>
                <p v-else-if="!section.rows.length" class="text-sm text-slate-500 dark:text-slate-400">
                    No skills here for {{ languageNames[language] }}.
                    <button type="button" class="ml-1 font-medium text-sky-600 hover:underline dark:text-sky-400" @click="language = other">Show {{ languageNames[other] }}</button>
                </p>

                <div v-else class="divide-y divide-slate-200 rounded-xl border border-slate-200 bg-white dark:divide-slate-800 dark:border-slate-800 dark:bg-slate-900">
                    <div v-for="s in section.rows" :key="s.id" class="flex flex-col gap-2 px-4 py-3.5 sm:flex-row sm:items-start sm:gap-3 sm:px-5">
                        <Link :href="read(s)" class="group min-w-0 flex-1 rounded focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-500">
                            <span class="flex flex-wrap items-baseline gap-x-2">
                                <span class="font-semibold text-slate-900 group-hover:underline dark:text-white">{{ s.name }}</span>
                                <span class="text-xs text-slate-500">{{ languageScope(s.language) }}</span>
                            </span>
                            <span class="mt-0.5 block text-sm text-slate-600 dark:text-slate-400">{{ s.descriptions?.[language] ?? s.description }}</span>
                        </Link>
                        <div class="flex shrink-0 items-center gap-2">
                            <Link :href="read(s)"><UiButton size="sm" variant="outline">Read</UiButton></Link>
                            <Link v-if="s.editable" :href="`/skills/${s.id}/edit`"><UiButton size="sm" variant="ghost">Edit</UiButton></Link>
                            <UiButton v-else size="sm" variant="ghost" @click="fork(s.id)">Copy and edit</UiButton>
                        </div>
                    </div>
                </div>
            </section>

            <details class="group">
                <summary class="flex cursor-pointer list-none items-center gap-1 text-sm font-medium text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white [&::-webkit-details-marker]:hidden">
                    <ChevronRightIcon class="size-4 transition-transform group-open:rotate-90" />Import a skill from GitHub
                </summary>
                <div class="mt-3 max-w-2xl">
                    <GitHubImport action="/skills/import" label="GitHub link" placeholder="https://github.com/owner/repo/tree/main/skills/my-skill" />
                </div>
            </details>
        </div>
    </AppLayout>
</template>
