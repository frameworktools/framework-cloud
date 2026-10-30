<script setup>
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import UiButton from '@/Components/UiButton.vue';
import UiInput from '@/Components/UiInput.vue';
import UiSelect from '@/Components/UiSelect.vue';
import UiSwitch from '@/Components/UiSwitch.vue';
import UiTextarea from '@/Components/UiTextarea.vue';
import LayerSummary from '@/Components/LayerSummary.vue';
import { visibilityOptions } from '@/library.js';

const props = defineProps({
    blueprint: Object, // null when creating
    start: Object, // a version to begin from: the one being edited, or ?from=
    bases: Array,
    facets: Array,
    categories: Object,
});

const editing = computed(() => !!props.blueprint);

// Pinned versions from the starting point keep their own summaries, which
// can differ from the facet's latest version.
const pinned = Object.fromEntries(
    [props.start?.base, ...(props.start?.facets ?? [])].filter(Boolean).map((v) => [v.id, v]),
);

const baseVersionId = ref(props.start?.base.id ?? null);
const selected = ref((props.start?.facets ?? []).map((v) => ({ facet_id: v.facet_id, version_id: v.id })));

const facetById = computed(() => Object.fromEntries([...props.bases, ...props.facets].map((f) => [f.id, f])));
const baseFacet = computed(() => {
    const version = pinned[baseVersionId.value];
    return props.bases.find((b) => b.version_id === baseVersionId.value || b.id === version?.facet_id) ?? null;
});

const grouped = computed(() => {
    const sections = Object.entries(props.categories).map(([key, label]) => ({
        key,
        label,
        facets: props.facets.filter((f) => f.category === key),
    }));
    sections.push({ key: 'other', label: 'Other', facets: props.facets.filter((f) => !props.categories[f.category]) });
    return sections.filter((s) => s.facets.length);
});

const isOn = (facet) => selected.value.some((s) => s.facet_id === facet.id);
const toggle = (facet, on) => {
    if (on && !isOn(facet)) selected.value.push({ facet_id: facet.id, version_id: facet.version_id });
    if (!on) selected.value = selected.value.filter((s) => s.facet_id !== facet.id);
};
const move = (index, by) => {
    const list = [...selected.value];
    const [item] = list.splice(index, 1);
    list.splice(index + by, 0, item);
    selected.value = list;
};

// One fragment for the side panel: the facet, the pinned version, and whether a newer one exists
const fragment = (facetId, versionId) => {
    const facet = facetById.value[facetId];
    const pin = pinned[versionId];
    return {
        facet,
        name: facet?.name ?? pin?.name,
        handle: facet?.handle ?? pin?.handle,
        version: pin && versionId !== facet?.version_id ? pin.version : facet?.version,
        summary: pin && versionId !== facet?.version_id ? pin.summary : facet?.summary ?? [],
        outdated: !!facet && versionId !== facet.version_id,
    };
};

const baseFragment = computed(() => (baseVersionId.value ? fragment(baseFacet.value?.id ?? pinned[baseVersionId.value]?.facet_id, baseVersionId.value) : null));
const updateBase = () => (baseVersionId.value = baseFacet.value.version_id);
const updateFacet = (index) => {
    const item = selected.value[index];
    selected.value[index] = { ...item, version_id: facetById.value[item.facet_id].version_id };
};

const form = useForm({
    name: props.blueprint?.name ?? '',
    description: props.blueprint?.description ?? '',
    visibility: props.blueprint?.visibility ?? 'private',
    settings_yaml: props.start?.layer_yaml ?? '',
    notes: '',
});
const showAdvanced = ref(!!props.start?.layer_yaml);

const save = () => {
    const data = (d) => ({ ...d, base_version_id: baseVersionId.value, facet_version_ids: selected.value.map((s) => s.version_id) });
    if (editing.value) {
        form.transform(data).put(`/blueprints/${props.blueprint.id}`);
    } else {
        form.transform(data).post('/blueprints');
    }
};
</script>

<template>
    <Head :title="editing ? `Edit ${blueprint.name}` : 'New blueprint'" />

    <AppLayout>
        <form class="grid gap-8 lg:grid-cols-[1fr_20rem]" @submit.prevent="save">
            <div class="flex min-w-0 flex-col gap-8">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">{{ editing ? `Edit ${blueprint.name}` : 'New blueprint' }}</h1>
                    <p class="mt-1 text-slate-600 dark:text-slate-400">
                        Pick a project type, then switch on what this kind of project needs.
                        Saving makes a new version; projects keep the version they were made from.
                    </p>
                </div>

                <section class="flex flex-col gap-3">
                    <h2 class="font-medium">1. What kind of project is it?</h2>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <button
                            v-for="base in bases" :key="base.id" type="button"
                            class="rounded-lg border p-3 text-left transition-colors"
                            :class="baseFacet?.id === base.id
                                ? 'border-sky-500 bg-sky-50 dark:bg-sky-950/40'
                                : 'border-slate-200 hover:border-slate-400 dark:border-slate-800 dark:hover:border-slate-600'"
                            @click="baseVersionId = baseFacet?.id === base.id ? baseVersionId : base.version_id"
                        >
                            <div class="text-sm font-medium">{{ base.name }}</div>
                            <div class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ base.description }}</div>
                            <div v-if="!base.is_system" class="mt-1 font-mono text-xs text-slate-400">{{ base.handle }}</div>
                        </button>
                    </div>
                    <p v-if="form.errors.base_version_id" class="text-xs text-red-600 dark:text-red-400">{{ form.errors.base_version_id }}</p>
                </section>

                <section class="flex flex-col gap-5">
                    <h2 class="font-medium">2. What does it need?</h2>
                    <div v-for="section in grouped" :key="section.key" class="flex flex-col gap-2">
                        <h3 class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ section.label }}</h3>
                        <div class="flex flex-col divide-y divide-slate-200 rounded-lg border border-slate-200 dark:divide-slate-800 dark:border-slate-800">
                            <div v-for="facet in section.facets" :key="facet.id" class="p-3">
                                <UiSwitch
                                    :model-value="isOn(facet)"
                                    :label="facet.question || facet.name"
                                    :description="facet.question ? `${facet.name}: ${facet.description ?? ''}` : facet.description"
                                    @update:model-value="(on) => toggle(facet, on)"
                                />
                                <div v-if="!facet.is_system" class="mt-1 font-mono text-xs text-slate-400">{{ facet.handle }}</div>
                            </div>
                        </div>
                    </div>
                    <p class="text-sm text-slate-500">
                        Don't see what you need?
                        <Link href="/facets/create" class="text-sky-600 hover:underline dark:text-sky-400">Make a facet</Link>,
                        then come back and switch it on.
                    </p>
                    <p v-if="form.errors.facet_version_ids" class="text-xs text-red-600 dark:text-red-400">{{ form.errors.facet_version_ids }}</p>
                </section>

                <section class="flex flex-col gap-4">
                    <h2 class="font-medium">3. Name and share it</h2>
                    <UiInput v-model="form.name" label="Name" placeholder="e.g. R01 secondary analysis" required :error="form.errors.name" />
                    <UiTextarea v-model="form.description" label="Description" :rows="2" :error="form.errors.description" />
                    <UiSelect v-model="form.visibility" label="Who can see it" :options="visibilityOptions" :error="form.errors.visibility" />
                    <UiInput v-if="editing" v-model="form.notes" label="What changed (optional)" placeholder="Shown in the version history" :error="form.errors.notes" />

                    <div>
                        <button type="button" class="text-sm text-slate-600 hover:underline dark:text-slate-400" @click="showAdvanced = !showAdvanced">
                            {{ showAdvanced ? 'Hide' : 'Show' }} extra settings
                        </button>
                    </div>
                    <UiTextarea
                        v-if="showAdvanced" v-model="form.settings_yaml" code :rows="8"
                        label="Extra settings (YAML)"
                        description="Applied after every facet, so they win. Same keys as settings.yml, for example packages or quarto."
                        :error="form.errors.settings_yaml"
                    />

                    <div class="flex gap-2">
                        <UiButton type="submit" :disabled="form.processing || !baseVersionId">{{ editing ? 'Save new version' : 'Create blueprint' }}</UiButton>
                        <Link :href="editing ? `/blueprints/${blueprint.id}` : '/blueprints'"><UiButton variant="ghost">Cancel</UiButton></Link>
                    </div>
                </section>
            </div>

            <aside class="flex flex-col gap-3 lg:sticky lg:top-6 lg:self-start">
                <h2 class="text-sm font-medium">What you'll get</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    Each piece applies in this order, and a later one wins when two set the same thing.
                    Framework combines them when it creates the project.
                </p>

                <p v-if="!baseFragment" class="rounded-lg border border-dashed border-slate-300 p-3 text-sm text-slate-500 dark:border-slate-700">
                    Choose a project type to start.
                </p>

                <div v-if="baseFragment" class="rounded-lg border border-slate-200 p-3 dark:border-slate-800">
                    <div class="flex items-baseline justify-between gap-2">
                        <span class="text-sm font-medium">{{ baseFragment.name }}</span>
                        <span class="font-mono text-xs text-slate-500">v{{ baseFragment.version }}</span>
                    </div>
                    <button v-if="baseFragment.outdated" type="button" class="mt-1 text-xs text-sky-600 hover:underline dark:text-sky-400" @click="updateBase">
                        Update to v{{ baseFacet.version }}
                    </button>
                    <div class="mt-2"><LayerSummary :sections="baseFragment.summary" /></div>
                </div>

                <div v-for="(item, index) in selected" :key="item.facet_id" class="rounded-lg border border-slate-200 p-3 dark:border-slate-800">
                    <div class="flex items-baseline justify-between gap-2">
                        <span class="text-sm font-medium">+ {{ fragment(item.facet_id, item.version_id).name }}</span>
                        <span class="flex items-center gap-1 font-mono text-xs text-slate-500">
                            v{{ fragment(item.facet_id, item.version_id).version }}
                            <button type="button" class="px-1 hover:text-slate-900 disabled:opacity-30 dark:hover:text-slate-100" :disabled="index === 0" title="Apply earlier" @click="move(index, -1)">↑</button>
                            <button type="button" class="px-1 hover:text-slate-900 disabled:opacity-30 dark:hover:text-slate-100" :disabled="index === selected.length - 1" title="Apply later" @click="move(index, 1)">↓</button>
                        </span>
                    </div>
                    <button
                        v-if="fragment(item.facet_id, item.version_id).outdated" type="button"
                        class="mt-1 text-xs text-sky-600 hover:underline dark:text-sky-400" @click="updateFacet(index)"
                    >
                        Update to v{{ facetById[item.facet_id].version }}
                    </button>
                    <div class="mt-2"><LayerSummary :sections="fragment(item.facet_id, item.version_id).summary" /></div>
                </div>
            </aside>
        </form>
    </AppLayout>
</template>
