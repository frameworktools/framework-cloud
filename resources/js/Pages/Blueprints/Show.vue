<script setup>
import { computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import UiButton from '@/Components/UiButton.vue';
import UiCallout from '@/Components/UiCallout.vue';
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
        <div class="flex flex-col gap-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0">
                    <Link href="/blueprints" class="text-sm text-slate-500 hover:underline">Blueprints</Link>
                    <h1 class="text-2xl font-semibold tracking-tight">{{ blueprint.name }}</h1>
                    <div class="font-mono text-xs text-slate-500">
                        {{ blueprint.handle }} · v{{ version.version }}{{ version.version !== latest ? ` (latest is v${latest})` : '' }}
                    </div>
                    <p v-if="forked_from" class="mt-1 text-xs text-slate-500">
                        Copied from
                        <Link :href="`/blueprints/${forked_from.id}`" class="hover:underline">{{ forked_from.handle }} v{{ forked_from.version }}</Link>
                    </p>
                    <p v-if="blueprint.description" class="mt-2 max-w-2xl text-slate-600 dark:text-slate-400">{{ blueprint.description }}</p>
                </div>
                <div class="flex gap-2">
                    <Link :href="`/projects/create?blueprint=${blueprint.id}`"><UiButton>Use</UiButton></Link>
                    <Link v-if="blueprint.editable" :href="`/blueprints/${blueprint.id}/edit`"><UiButton variant="outline">Edit</UiButton></Link>
                    <UiButton variant="outline" @click="fork">Copy and edit</UiButton>
                    <UiButton v-if="blueprint.editable && !blueprint.is_system" variant="ghost" @click="destroy">Delete</UiButton>
                </div>
            </div>

            <UiCallout v-if="errors.length" variant="danger">{{ errors.join(' ') }}</UiCallout>

            <UiCallout v-if="blueprint.editable && version.version === latest && outdated.length" heading="Newer versions available">
                {{ outdated.map((f) => `${f.name} v${f.latest_version}`).join(', ') }}.
                <Link :href="`/blueprints/${blueprint.id}/edit`" class="text-sky-600 hover:underline dark:text-sky-400">Review them in the editor</Link>.
            </UiCallout>

            <div class="grid gap-8 lg:grid-cols-[1fr_16rem]">
                <section class="flex flex-col gap-3">
                    <h2 class="text-sm font-medium">Pieces, in the order they apply</h2>
                    <div
                        v-for="(fragment, index) in fragments" :key="fragment.id"
                        class="rounded-lg border border-slate-200 p-4 dark:border-slate-800"
                    >
                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <span class="font-medium">{{ index === 0 ? fragment.name : `+ ${fragment.name}` }}</span>
                            <span class="font-mono text-xs text-slate-500">
                                {{ fragment.handle }} v{{ fragment.version }}
                                <span v-if="fragment.version < fragment.latest_version" class="text-sky-600 dark:text-sky-400">· v{{ fragment.latest_version }} available</span>
                            </span>
                        </div>
                        <div class="mt-3"><LayerSummary :sections="fragment.summary" /></div>
                    </div>
                    <div v-if="version.layer_summary.length" class="rounded-lg border border-slate-200 p-4 dark:border-slate-800">
                        <div class="font-medium">+ This blueprint's extra settings</div>
                        <div class="mt-3"><LayerSummary :sections="version.layer_summary" /></div>
                    </div>
                </section>

                <aside class="flex flex-col gap-2">
                    <h2 class="text-sm font-medium">Versions</h2>
                    <Link
                        v-for="v in [...versions].reverse()" :key="v.id"
                        :href="`/blueprints/${blueprint.id}?version=${v.version}`"
                        class="rounded-md px-2 py-1.5 text-sm"
                        :class="v.version === version.version ? 'bg-slate-100 dark:bg-slate-800' : 'hover:bg-slate-50 dark:hover:bg-slate-900'"
                    >
                        <span class="font-mono">v{{ v.version }}</span>
                        <span class="text-slate-500"> · {{ date(v.created_at) }}</span>
                        <div v-if="v.notes" class="text-xs text-slate-500">{{ v.notes }}</div>
                    </Link>
                </aside>
            </div>
        </div>
    </AppLayout>
</template>
