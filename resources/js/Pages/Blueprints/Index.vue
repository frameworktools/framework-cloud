<script setup>
import { computed, reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import UiButton from '@/Components/UiButton.vue';
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
        <div class="flex flex-col gap-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">Blueprints</h1>
                    <p class="mt-1 max-w-2xl text-neutral-600 dark:text-neutral-400">
                        A blueprint is a project type plus the facets you add to it. Start one from
                        scratch, or copy someone else's and change it.
                    </p>
                </div>
                <Link href="/blueprints/create"><UiButton>New blueprint</UiButton></Link>
            </div>

            <section v-for="section in sections" :key="section.key" class="flex flex-col gap-3">
                <h2 class="text-sm font-medium uppercase tracking-wide text-neutral-500">{{ section.label }}</h2>

                <p v-if="!section.rows.length" class="text-sm text-neutral-500">
                    You haven't made a blueprint yet.
                </p>

                <div v-if="section.rows.length" class="flex flex-col divide-y divide-neutral-200 rounded-lg border border-neutral-200 dark:divide-neutral-800 dark:border-neutral-800">
                    <div v-for="blueprint in section.rows" :key="blueprint.id">
                        <div class="flex items-start gap-3 p-4">
                            <button
                                type="button" class="flex min-w-0 flex-1 items-start gap-3 text-left"
                                :aria-expanded="open.has(blueprint.id)"
                                @click="toggle(blueprint.id)"
                            >
                                <svg
                                    class="mt-1 size-4 shrink-0 text-neutral-400 transition-transform" :class="open.has(blueprint.id) ? '' : '-rotate-90'"
                                    viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"
                                ><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.17l3.71-3.94a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" /></svg>
                                <span class="flex min-w-0 flex-col gap-1">
                                    <span class="flex flex-wrap items-center gap-2">
                                        <span class="font-medium">{{ blueprint.name }}</span>
                                        <span class="font-mono text-xs text-neutral-500">{{ blueprint.handle }} · v{{ blueprint.version }}</span>
                                        <span
                                            v-for="facet in blueprint.facets" :key="facet"
                                            class="rounded bg-orange-50 px-1.5 py-0.5 text-xs text-orange-800 dark:bg-orange-950/50 dark:text-orange-300"
                                        >+ {{ facet }}</span>
                                    </span>
                                    <span v-if="blueprint.description" class="text-sm text-neutral-600 dark:text-neutral-400">{{ blueprint.description }}</span>
                                </span>
                            </button>
                            <div class="flex shrink-0 gap-2">
                                <Link :href="`/projects/create?blueprint=${blueprint.id}`"><UiButton size="sm">Use</UiButton></Link>
                                <Link v-if="blueprint.editable" :href="`/blueprints/${blueprint.id}/edit`"><UiButton size="sm" variant="outline">Edit</UiButton></Link>
                                <UiButton v-else size="sm" variant="outline" @click="fork(blueprint.id)">Copy and edit</UiButton>
                            </div>
                        </div>
                        <div v-if="open.has(blueprint.id) && blueprint.fragments" class="px-4 pb-4 pl-11">
                            <BlueprintContents :version="blueprint.fragments" />
                            <Link :href="`/blueprints/${blueprint.id}`" class="mt-3 inline-block text-sm text-orange-600 hover:underline dark:text-orange-400">Full details and versions</Link>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
