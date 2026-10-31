<script setup>
// What a blueprint version sets up, fragment by fragment (Library::blueprintVersion).
import LayerSummary from '@/Components/LayerSummary.vue';

defineProps({ version: { type: Object, required: true } });
</script>

<template>
    <div class="flex flex-col gap-4">
        <div v-for="fragment in [version.base, ...version.facets]" :key="fragment.id" class="flex flex-col gap-1.5">
            <div class="text-sm font-medium">
                {{ fragment.kind === 'structure' ? fragment.name : `+ ${fragment.name}` }}
                <span class="font-normal text-slate-500">{{ fragment.kind === 'structure' ? 'project type' : 'facet' }}</span>
            </div>
            <LayerSummary :sections="fragment.summary" />
        </div>
        <div v-if="version.layer_summary.length" class="flex flex-col gap-1.5">
            <div class="text-sm font-medium">Blueprint settings</div>
            <LayerSummary :sections="version.layer_summary" />
        </div>
    </div>
</template>
