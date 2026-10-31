<script setup>
import { computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import UiButton from '@/Components/UiButton.vue';
import UiCallout from '@/Components/UiCallout.vue';
import UiCard from '@/Components/UiCard.vue';
import UiBadge from '@/Components/UiBadge.vue';
import UiPanel from '@/Components/UiPanel.vue';
import EmptyState from '@/Components/EmptyState.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { PlusIcon, PuzzlePieceIcon } from '@heroicons/vue/24/outline';
import GitHubImport from '@/Components/GitHubImport.vue';
import LayerSummary from '@/Components/LayerSummary.vue';
import { group, groups } from '@/library.js';

const props = defineProps({ facets: Array, categories: Object });

const page = usePage();
const errors = computed(() => Object.values(page.props.errors ?? {}));

const sections = computed(() =>
    groups
        .map((g) => ({ ...g, rows: props.facets.filter((f) => group(f) === g.key) }))
        .filter((g) => g.rows.length || g.key === 'mine'),
);

const label = (facet) => (facet.kind === 'structure' ? 'Project type' : props.categories[facet.category] ?? 'Other');
const fork = (id) => router.post(`/facets/${id}/fork`);
</script>

<template>
    <Head title="Facets" />

    <AppLayout>
        <div class="flex flex-col gap-10">
            <PageHeader title="Facets">
                <template #description>
                    A facet adds one thing to a project: folders, packages, git hooks, skills, or files.
                    Blueprints stack facets on a project type.
                </template>
                <template #actions>
                    <Link href="/facets/create?kind=structure"><UiButton variant="outline">New project type</UiButton></Link>
                    <Link href="/facets/create"><UiButton><PlusIcon class="size-4" />New facet</UiButton></Link>
                </template>
            </PageHeader>

            <UiCallout v-if="errors.length" variant="danger">{{ errors.join(' ') }}</UiCallout>

            <UiPanel>
                <GitHubImport
                    action="/facets/import"
                    label="Import a facet from GitHub"
                    placeholder="https://github.com/owner/repo/blob/main/facets/my-facet.facet.yml"
                />
            </UiPanel>

            <section v-for="section in sections" :key="section.key" class="flex flex-col gap-4">
                <h2 class="flex items-baseline gap-2 text-lg font-semibold text-slate-900 dark:text-white">
                    {{ section.label }}
                    <span v-if="section.rows.length" class="text-sm font-normal text-slate-500 dark:text-slate-400">{{ section.rows.length }}</span>
                </h2>

                <EmptyState v-if="!section.rows.length" compact :icon="PuzzlePieceIcon" title="You haven't made a facet yet.">
                    <template #actions>
                        <Link href="/facets/create"><UiButton size="sm" variant="outline"><PlusIcon class="size-3.5" />New facet</UiButton></Link>
                    </template>
                </EmptyState>

                <div v-else class="flex flex-col gap-3">
                    <UiCard v-for="facet in section.rows" :key="facet.id" hover>
                        <div class="grid gap-4 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto]">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-base font-semibold text-slate-900 dark:text-white">{{ facet.name }}</span>
                                    <UiBadge :variant="facet.kind === 'structure' ? 'sky' : 'slate'">{{ label(facet) }}</UiBadge>
                                </div>
                                <div class="mt-0.5 truncate font-mono text-xs text-slate-500 dark:text-slate-400">{{ facet.handle }} · v{{ facet.version }}</div>
                                <p v-if="facet.description" class="mt-2 text-sm text-slate-600 dark:text-slate-400">{{ facet.description }}</p>
                            </div>
                            <div class="min-w-0 self-start rounded-md bg-slate-50 p-3 dark:bg-slate-800/50">
                                <LayerSummary :sections="facet.summary" />
                            </div>
                            <div class="flex shrink-0 items-start gap-2 whitespace-nowrap">
                                <Link v-if="facet.editable" :href="`/facets/${facet.id}/edit`"><UiButton size="sm" variant="outline">Edit</UiButton></Link>
                                <UiButton v-else size="sm" variant="outline" @click="fork(facet.id)">Copy and edit</UiButton>
                            </div>
                        </div>
                    </UiCard>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
