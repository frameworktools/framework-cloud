<script setup>
import { computed, reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import UiButton from '@/Components/UiButton.vue';
import UiCard from '@/Components/UiCard.vue';
import UiBadge from '@/Components/UiBadge.vue';
import EmptyState from '@/Components/EmptyState.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { ChevronRightIcon, PlusIcon, Squares2X2Icon } from '@heroicons/vue/24/outline';
import BlueprintContents from '@/Components/BlueprintContents.vue';
import { group, groups } from '@/library.js';

const props = defineProps({ blueprints: Array });

const sections = computed(() =>
    groups
        .map((g) => ({ ...g, rows: props.blueprints.filter((b) => group(b) === g.key) }))
        .filter((g) => g.rows.length || g.key === 'mine'),
);

const open = reactive(new Set());
const toggle = (id) => (open.has(id) ? open.delete(id) : open.add(id));

const fork = (id) => router.post(`/blueprints/${id}/fork`);
</script>

<template>
    <Head title="Blueprints" />

    <AppLayout>
        <div class="flex flex-col gap-10">
            <PageHeader title="Blueprints">
                <template #description>
                    A blueprint is a saved set of choices for new projects: a project structure, tools,
                    package sets, skills, and settings. Start one from scratch, or copy someone else's and change it.
                </template>
                <template #actions>
                    <Link href="/blueprints/create"><UiButton><PlusIcon class="size-4" />New blueprint</UiButton></Link>
                </template>
            </PageHeader>

            <section v-for="section in sections" :key="section.key" class="flex flex-col gap-4">
                <h2 class="flex items-baseline gap-2 text-lg font-semibold text-slate-900 dark:text-white">
                    {{ section.label }}
                    <span v-if="section.rows.length" class="text-sm font-normal text-slate-500 dark:text-slate-400">{{ section.rows.length }}</span>
                </h2>

                <EmptyState v-if="!section.rows.length" compact :icon="Squares2X2Icon" title="You haven't made a blueprint yet.">
                    <template #actions>
                        <Link href="/blueprints/create"><UiButton size="sm" variant="outline"><PlusIcon class="size-3.5" />New blueprint</UiButton></Link>
                    </template>
                </EmptyState>

                <div v-else class="flex flex-col gap-3">
                    <UiCard v-for="blueprint in section.rows" :key="blueprint.id" hover :padded="false">
                        <div class="flex flex-col gap-4 p-5 sm:flex-row sm:items-start sm:gap-3">
                            <button
                                type="button" class="flex min-w-0 flex-1 items-start gap-3 text-left"
                                :aria-expanded="open.has(blueprint.id)"
                                @click="toggle(blueprint.id)"
                            >
                                <ChevronRightIcon
                                    class="mt-1 size-4 shrink-0 text-slate-400 transition-transform" :class="open.has(blueprint.id) ? 'rotate-90' : ''"
                                    aria-hidden="true"
                                />
                                <span class="flex min-w-0 flex-col gap-1.5">
                                    <span class="flex flex-wrap items-center gap-2">
                                        <span class="text-base font-semibold text-slate-900 dark:text-white">{{ blueprint.name }}</span>
                                        <span class="font-mono text-xs text-slate-500 dark:text-slate-400">{{ blueprint.handle }} · v{{ blueprint.version }}</span>
                                    </span>
                                    <span v-if="blueprint.description" class="text-sm text-slate-600 dark:text-slate-400">{{ blueprint.description }}</span>
                                    <span v-if="blueprint.facets.length" class="flex flex-wrap gap-1.5">
                                        <UiBadge v-for="facet in blueprint.facets" :key="facet" variant="sky">+ {{ facet }}</UiBadge>
                                    </span>
                                </span>
                            </button>
                            <div class="flex shrink-0 gap-2">
                                <Link :href="`/projects/create?blueprint=${blueprint.id}`"><UiButton size="sm">Use</UiButton></Link>
                                <Link v-if="blueprint.editable" :href="`/blueprints/${blueprint.id}/edit`"><UiButton size="sm" variant="outline">Edit</UiButton></Link>
                                <UiButton v-else size="sm" variant="outline" @click="fork(blueprint.id)">Copy and edit</UiButton>
                            </div>
                        </div>
                        <div v-if="open.has(blueprint.id) && blueprint.fragments" class="border-t border-slate-100 px-5 py-4 sm:pl-12 dark:border-slate-800">
                            <BlueprintContents :version="blueprint.fragments" />
                            <Link :href="`/blueprints/${blueprint.id}`" class="mt-4 inline-block text-sm font-medium text-sky-600 hover:underline dark:text-sky-400">Full details and versions</Link>
                        </div>
                    </UiCard>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
