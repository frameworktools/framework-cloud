<script setup>
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import UiButton from '@/Components/UiButton.vue';
import { group, groups } from '@/library.js';

const props = defineProps({ blueprints: Array });

const sections = computed(() =>
    groups
        .map((g) => ({ ...g, rows: props.blueprints.filter((b) => group(b) === g.key) }))
        .filter((g) => g.rows.length || g.key === 'mine'),
);

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

                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    <div
                        v-for="blueprint in section.rows" :key="blueprint.id"
                        class="flex flex-col gap-3 rounded-lg border border-neutral-200 p-4 dark:border-neutral-800"
                    >
                        <div class="min-w-0">
                            <Link :href="`/blueprints/${blueprint.id}`" class="font-medium hover:underline">{{ blueprint.name }}</Link>
                            <div class="truncate font-mono text-xs text-neutral-500">{{ blueprint.handle }} · v{{ blueprint.version }}</div>
                        </div>
                        <p v-if="blueprint.description" class="text-sm text-neutral-600 dark:text-neutral-400">{{ blueprint.description }}</p>
                        <div class="flex flex-wrap gap-1.5 text-xs">
                            <span class="rounded bg-neutral-100 px-1.5 py-0.5 text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300">{{ blueprint.base }}</span>
                            <span
                                v-for="facet in blueprint.facets" :key="facet"
                                class="rounded bg-orange-50 px-1.5 py-0.5 text-orange-800 dark:bg-orange-950/50 dark:text-orange-300"
                            >+ {{ facet }}</span>
                        </div>
                        <div class="mt-auto flex gap-2 pt-1">
                            <Link :href="`/projects?blueprint=${blueprint.id}`"><UiButton size="sm">Use</UiButton></Link>
                            <Link v-if="blueprint.editable" :href="`/blueprints/${blueprint.id}/edit`"><UiButton size="sm" variant="outline">Edit</UiButton></Link>
                            <UiButton v-else size="sm" variant="outline" @click="fork(blueprint.id)">Copy and edit</UiButton>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
