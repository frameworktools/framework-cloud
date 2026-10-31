<script setup>
import { computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import UiButton from '@/Components/UiButton.vue';
import UiCallout from '@/Components/UiCallout.vue';
import UiCard from '@/Components/UiCard.vue';
import UiBadge from '@/Components/UiBadge.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { ChevronLeftIcon } from '@heroicons/vue/24/outline';
import LayerSummary from '@/Components/LayerSummary.vue';

const props = defineProps({ blueprint: Object, version: Object, versions: Array, forked_from: Object });

const page = usePage();
const errors = computed(() => Object.values(page.props.errors ?? {}));
const latest = computed(() => props.versions[props.versions.length - 1]?.version);
const fragments = computed(() => [props.version.base, ...props.version.facets]);
const outdated = computed(() => fragments.value.filter((f) => f.version < f.latest_version));

const fork = () => router.post(`/blueprints/${props.blueprint.id}/fork`);
const destroy = () => {
    if (confirm(`Delete ${props.blueprint.name}? This can't be undone.`)) {
        router.delete(`/blueprints/${props.blueprint.id}`);
    }
};
const date = (value) => new Date(value).toLocaleDateString();
</script>

<template>
    <Head :title="blueprint.name" />

    <AppLayout>
        <div class="flex flex-col gap-8">
            <div class="flex flex-col gap-3">
                <Link href="/blueprints" class="inline-flex w-fit items-center gap-1 text-sm text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200">
                    <ChevronLeftIcon class="size-4" />Blueprints
                </Link>
                <PageHeader :title="blueprint.name" :description="blueprint.description">
                    <template #actions>
                        <UiButton v-if="blueprint.editable && !blueprint.is_system" variant="ghost" @click="destroy">Delete</UiButton>
                        <UiButton variant="outline" @click="fork">Copy and edit</UiButton>
                        <Link v-if="blueprint.editable" :href="`/blueprints/${blueprint.id}/edit`"><UiButton variant="outline">Edit</UiButton></Link>
                        <Link :href="`/projects/create?blueprint=${blueprint.id}`"><UiButton>Use</UiButton></Link>
                    </template>
                </PageHeader>
                <div class="flex flex-wrap items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                    <UiBadge mono>{{ blueprint.handle }}</UiBadge>
                    <UiBadge :variant="version.version === latest ? 'sky' : 'amber'" mono>
                        v{{ version.version }}{{ version.version !== latest ? ` (latest is v${latest})` : '' }}
                    </UiBadge>
                    <span v-if="forked_from">
                        Copied from
                        <Link :href="`/blueprints/${forked_from.id}`" class="font-mono hover:underline">{{ forked_from.handle }} v{{ forked_from.version }}</Link>
                    </span>
                </div>
            </div>

            <UiCallout v-if="errors.length" variant="danger">{{ errors.join(' ') }}</UiCallout>

            <UiCallout v-if="blueprint.editable && version.version === latest && outdated.length" heading="Newer versions available">
                {{ outdated.map((f) => `${f.name} v${f.latest_version}`).join(', ') }}.
                <Link :href="`/blueprints/${blueprint.id}/edit`" class="text-sky-600 hover:underline dark:text-sky-400">Review them in the editor</Link>.
            </UiCallout>

            <div class="grid items-start gap-8 lg:grid-cols-[minmax(0,1fr)_15rem]">
                <section class="flex flex-col gap-4">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Pieces, in the order they apply</h2>
                    <ol class="flex flex-col gap-3">
                        <li v-for="(fragment, index) in fragments" :key="fragment.id">
                            <UiCard>
                                <div class="flex items-start gap-4">
                                    <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-sky-50 text-xs font-semibold text-sky-700 dark:bg-sky-400/10 dark:text-sky-300">{{ index + 1 }}</span>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                            <span class="flex flex-wrap items-center gap-2">
                                                <span class="font-semibold text-slate-900 dark:text-white">{{ index === 0 ? fragment.name : `+ ${fragment.name}` }}</span>
                                                <UiBadge :variant="index === 0 ? 'sky' : 'slate'">{{ index === 0 ? 'Project type' : 'Facet' }}</UiBadge>
                                            </span>
                                            <span class="font-mono text-xs text-slate-500 dark:text-slate-400">
                                                {{ fragment.handle }} v{{ fragment.version }}
                                                <span v-if="fragment.version < fragment.latest_version" class="text-sky-600 dark:text-sky-400">· v{{ fragment.latest_version }} available</span>
                                            </span>
                                        </div>
                                        <div class="mt-3"><LayerSummary :sections="fragment.summary" /></div>
                                    </div>
                                </div>
                            </UiCard>
                        </li>
                        <li v-if="version.layer_summary.length">
                            <UiCard>
                                <div class="flex items-start gap-4">
                                    <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-sky-50 text-xs font-semibold text-sky-700 dark:bg-sky-400/10 dark:text-sky-300">{{ fragments.length + 1 }}</span>
                                    <div class="min-w-0 flex-1">
                                        <div class="font-semibold text-slate-900 dark:text-white">+ This blueprint's extra settings</div>
                                        <div class="mt-3"><LayerSummary :sections="version.layer_summary" /></div>
                                    </div>
                                </div>
                            </UiCard>
                        </li>
                    </ol>
                </section>

                <aside class="flex flex-col gap-3 rounded-lg bg-slate-50 p-4 lg:sticky lg:top-6 dark:bg-slate-800/50">
                    <h2 class="px-2 text-sm font-semibold text-slate-900 dark:text-white">Versions</h2>
                    <nav class="flex flex-col gap-1">
                        <Link
                            v-for="v in [...versions].reverse()" :key="v.id"
                            :href="`/blueprints/${blueprint.id}?version=${v.version}`"
                            class="rounded-md px-2 py-1.5 text-sm transition"
                            :class="v.version === version.version
                                ? 'bg-white font-medium text-sky-700 shadow-xs ring-1 ring-slate-200 dark:bg-slate-900 dark:text-sky-400 dark:ring-slate-700'
                                : 'text-slate-700 hover:bg-white dark:text-slate-300 dark:hover:bg-slate-900'"
                        >
                            <span class="font-mono">v{{ v.version }}</span>
                            <span class="font-normal text-slate-500 dark:text-slate-400"> · {{ date(v.created_at) }}</span>
                            <div v-if="v.notes" class="text-xs font-normal text-slate-500 dark:text-slate-400">{{ v.notes }}</div>
                        </Link>
                    </nav>
                </aside>
            </div>
        </div>
    </AppLayout>
</template>
