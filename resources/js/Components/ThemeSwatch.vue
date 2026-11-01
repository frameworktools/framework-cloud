<script setup>
// A small, approximate look at a theme: text and an accent on its background
defineProps({ colors: { type: Array, default: null } }); // [background, text, accent]
</script>

<template>
    <span
        v-if="colors" class="flex h-7 w-11 shrink-0 flex-col justify-center gap-1 rounded border border-slate-300 px-1.5 dark:border-slate-600"
        :style="{ backgroundColor: colors[0] }" aria-hidden="true" title="An approximate preview of the theme's colors"
    >
        <span class="block h-0.5 w-6 rounded" :style="{ backgroundColor: colors[1] }" />
        <span class="block h-0.5 w-4 rounded" :style="{ backgroundColor: colors[2] }" />
    </span>
</template>
