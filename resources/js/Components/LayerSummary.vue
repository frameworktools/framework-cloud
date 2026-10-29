<script setup>
// What one layer adds or removes, as the server describes it (App\Support\LayerSummary).
defineProps({
    sections: { type: Array, default: () => [] },
    empty: { type: String, default: 'Changes nothing on its own.' },
});
</script>

<template>
    <dl v-if="sections.length" class="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-xs">
        <template v-for="section in sections" :key="section.label">
            <dt class="text-neutral-500 dark:text-neutral-400">{{ section.label }}</dt>
            <dd class="min-w-0 break-words font-mono text-neutral-800 dark:text-neutral-200">{{ section.items.join(', ') }}</dd>
        </template>
    </dl>
    <p v-else class="text-xs text-neutral-500 dark:text-neutral-400">{{ empty }}</p>
</template>
