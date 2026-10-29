<script setup>
import { computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import UiButton from '@/Components/UiButton.vue';
import UiCallout from '@/Components/UiCallout.vue';
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

const label = (facet) => (facet.kind === 'base' ? 'Project type' : props.categories[facet.category] ?? 'Other');
const fork = (id) => router.post(`/facets/${id}/fork`);
</script>

<template>
    <Head title="Facets" />

    <AppLayout>
        <div class="flex flex-col gap-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">Facets</h1>
                    <p class="mt-1 max-w-2xl text-neutral-600 dark:text-neutral-400">
                        A facet adds one thing to a project: folders, packages, git hooks, skills, or files.
                        Blueprints stack facets on a project type.
                    </p>
                </div>
                <div class="flex gap-2">
                    <Link href="/facets/create?kind=base"><UiButton variant="outline">New project type</UiButton></Link>
                    <Link href="/facets/create"><UiButton>New facet</UiButton></Link>
                </div>
            </div>

            <UiCallout v-if="errors.length" variant="danger">{{ errors.join(' ') }}</UiCallout>

            <GitHubImport
                action="/facets/import"
                label="Import a facet from GitHub"
                placeholder="https://github.com/owner/repo/blob/main/facets/my-facet.facet.yml"
            />

            <section v-for="section in sections" :key="section.key" class="flex flex-col gap-3">
                <h2 class="text-sm font-medium uppercase tracking-wide text-neutral-500">{{ section.label }}</h2>
                <p v-if="!section.rows.length" class="text-sm text-neutral-500">You haven't made a facet yet.</p>

                <div class="flex flex-col divide-y divide-neutral-200 rounded-lg border border-neutral-200 dark:divide-neutral-800 dark:border-neutral-800">
                    <div v-for="facet in section.rows" :key="facet.id" class="grid gap-3 p-4 md:grid-cols-[1fr_1fr_auto]">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-baseline gap-2">
                                <span class="font-medium">{{ facet.name }}</span>
                                <span class="rounded bg-neutral-100 px-1.5 py-0.5 text-xs text-neutral-600 dark:bg-neutral-800 dark:text-neutral-400">{{ label(facet) }}</span>
                            </div>
                            <div class="truncate font-mono text-xs text-neutral-500">{{ facet.handle }} · v{{ facet.version }}</div>
                            <p v-if="facet.description" class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">{{ facet.description }}</p>
                        </div>
                        <LayerSummary :sections="facet.summary" />
                        <div class="flex items-start gap-2">
                            <Link v-if="facet.editable" :href="`/facets/${facet.id}/edit`"><UiButton size="sm" variant="outline">Edit</UiButton></Link>
                            <UiButton v-else size="sm" variant="outline" @click="fork(facet.id)">Copy and edit</UiButton>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
