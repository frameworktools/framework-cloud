<script setup>
import { computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import UiButton from '@/Components/UiButton.vue';
import UiCallout from '@/Components/UiCallout.vue';
import { group, groups } from '@/library.js';
import UiCard from '@/Components/UiCard.vue';
import UiPanel from '@/Components/UiPanel.vue';
import EmptyState from '@/Components/EmptyState.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { PlusIcon, SparklesIcon } from '@heroicons/vue/24/outline';
import GitHubImport from '@/Components/GitHubImport.vue';

const props = defineProps({ skills: Array });

const page = usePage();
const errors = computed(() => Object.values(page.props.errors ?? {}));

const sections = computed(() =>
    groups
        .map((g) => ({ ...g, rows: props.skills.filter((s) => group(s) === g.key) }))
        .filter((g) => g.rows.length || g.key === 'mine'),
);

const fork = (id) => router.post(`/skills/${id}/fork`);
</script>

<template>
    <Head title="Skills" />

    <AppLayout>
        <div class="flex flex-col gap-10">
            <PageHeader title="Skills">
                <template #description>
                    A skill is a SKILL.md file of instructions that an AI assistant loads when its
                    description matches the task. Group skills that belong together into a
                    <Link href="/skill-sets" class="text-sky-600 hover:underline dark:text-sky-400">skill set</Link>, or add them to a blueprint one at a time.
                </template>
                <template #actions>
                    <Link href="/skills/create"><UiButton><PlusIcon class="size-4" />New skill</UiButton></Link>
                </template>
            </PageHeader>

            <UiCallout v-if="errors.length" variant="danger">{{ errors.join(' ') }}</UiCallout>

            <UiPanel>
                <GitHubImport
                    action="/skills/import"
                    label="Import a skill from GitHub"
                    placeholder="https://github.com/owner/repo/tree/main/skills/my-skill"
                />
            </UiPanel>

            <section v-for="section in sections" :key="section.key" class="flex flex-col gap-4">
                <h2 class="flex items-baseline gap-2 text-lg font-semibold text-slate-900 dark:text-white">
                    {{ section.label }}
                    <span v-if="section.rows.length" class="text-sm font-normal text-slate-500 dark:text-slate-400">{{ section.rows.length }}</span>
                </h2>

                <EmptyState v-if="!section.rows.length" compact :icon="SparklesIcon" title="You haven't made a skill yet.">
                    <template #actions>
                        <Link href="/skills/create"><UiButton size="sm" variant="outline"><PlusIcon class="size-3.5" />New skill</UiButton></Link>
                    </template>
                </EmptyState>

                <div v-else class="flex flex-col gap-3">
                    <UiCard v-for="skill in section.rows" :key="skill.id" hover>
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                            <div class="flex min-w-0 flex-1 items-start gap-4">
                                <div class="hidden size-10 shrink-0 items-center justify-center rounded-lg sm:flex bg-sky-50 text-sky-600 dark:bg-sky-400/10 dark:text-sky-400">
                                    <SparklesIcon class="size-5" />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <Link :href="`/skills/${skill.id}/edit`" class="text-base font-semibold text-slate-900 hover:underline dark:text-white">{{ skill.name }}</Link>
                                    <div class="font-mono text-xs text-slate-500 dark:text-slate-400" title="The skill's official name, which assistants use">{{ skill.handle }} · v{{ skill.version }}</div>
                                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ skill.description }}</p>
                                </div>
                            </div>
                            <Link v-if="skill.editable" :href="`/skills/${skill.id}/edit`" class="self-start"><UiButton size="sm" variant="outline">Edit</UiButton></Link>
                            <UiButton v-else size="sm" variant="outline" class="shrink-0 self-start whitespace-nowrap" @click="fork(skill.id)">Copy and edit</UiButton>
                        </div>
                    </UiCard>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
