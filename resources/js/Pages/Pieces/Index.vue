<script setup>
import { computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import UiButton from '@/Components/UiButton.vue';
import UiCallout from '@/Components/UiCallout.vue';
import UiCard from '@/Components/UiCard.vue';
import UiBadge from '@/Components/UiBadge.vue';
import EmptyState from '@/Components/EmptyState.vue';
import PageHeader from '@/Components/PageHeader.vue';
import LayerSummary from '@/Components/LayerSummary.vue';
import { CubeIcon, PlusIcon } from '@heroicons/vue/24/outline';
import { group, groups, languageNames } from '@/library.js';
import { pieceCopy } from '@/pieces.js';

const props = defineProps({ kind: String, path: String, pieces: Array });

const page = usePage();
const copy = computed(() => pieceCopy[props.kind]);
const errors = computed(() => Object.values(page.props.errors ?? {}));

const sections = computed(() =>
    groups
        .map((g) => ({ ...g, rows: props.pieces.filter((p) => group(p) === g.key) }))
        .filter((g) => g.rows.length || g.key === 'mine'),
);

const fork = (id) => router.post(`/${props.path}/${id}/fork`);
</script>

<template>
    <Head :title="copy.title" />

    <AppLayout>
        <div class="flex flex-col gap-10">
            <PageHeader :title="copy.title" :description="copy.description">
                <template #actions>
                    <Link v-if="kind === 'skill_set'" href="/skills"><UiButton variant="outline">Single skills</UiButton></Link>
                    <Link :href="`/${path}/create`"><UiButton><PlusIcon class="size-4" />New {{ copy.noun }}</UiButton></Link>
                </template>
            </PageHeader>

            <UiCallout v-if="errors.length" variant="danger">{{ errors.join(' ') }}</UiCallout>

            <section v-for="section in sections" :key="section.key" class="flex flex-col gap-4">
                <h2 class="flex items-baseline gap-2 text-lg font-semibold text-slate-900 dark:text-white">
                    {{ section.label }}
                    <span v-if="section.rows.length" class="text-sm font-normal text-slate-500 dark:text-slate-400">{{ section.rows.length }}</span>
                </h2>

                <EmptyState v-if="!section.rows.length" compact :icon="CubeIcon" :title="copy.empty">
                    <template #actions>
                        <Link :href="`/${path}/create`"><UiButton size="sm" variant="outline"><PlusIcon class="size-3.5" />New {{ copy.noun }}</UiButton></Link>
                    </template>
                </EmptyState>

                <div v-else class="flex flex-col gap-3">
                    <UiCard v-for="piece in section.rows" :key="piece.id" hover>
                        <div class="grid gap-4 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto]">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-base font-semibold text-slate-900 dark:text-white">{{ piece.name }}</span>
                                    <UiBadge v-for="language in piece.language ?? []" :key="language" variant="sky">{{ languageNames[language] }}</UiBadge>
                                </div>
                                <div class="mt-0.5 truncate font-mono text-xs text-slate-500 dark:text-slate-400">{{ piece.handle }} · v{{ piece.version }}</div>
                                <p v-if="piece.description" class="mt-2 text-sm text-slate-600 dark:text-slate-400">{{ piece.description }}</p>
                            </div>
                            <div class="min-w-0 self-start rounded-md bg-slate-50 p-3 dark:bg-slate-800/50">
                                <LayerSummary :sections="piece.summary" />
                            </div>
                            <div class="flex shrink-0 items-start gap-2 whitespace-nowrap">
                                <Link v-if="piece.editable" :href="`/${path}/${piece.id}/edit`"><UiButton size="sm" variant="outline">Edit</UiButton></Link>
                                <UiButton v-else size="sm" variant="outline" @click="fork(piece.id)">Copy and edit</UiButton>
                            </div>
                        </div>
                    </UiCard>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
