<script setup>
// A small, approximate look at a theme: text and an accent on its background,
// with a tooltip saying the colors are approximate
defineProps({ colors: { type: Array, default: null } }); // [background, text, accent]
</script>

<template>
    <span v-if="colors" class="group relative inline-flex shrink-0" tabindex="0" role="img" aria-label="Approximate theme colors">
        <span
            class="flex h-7 w-11 flex-col justify-center gap-1 rounded border border-slate-300 px-1.5 dark:border-slate-600"
            :style="{ backgroundColor: colors[0] }"
        >
            <span class="block h-0.5 w-6 rounded" :style="{ backgroundColor: colors[1] }" />
            <span class="block h-0.5 w-4 rounded" :style="{ backgroundColor: colors[2] }" />
        </span>
        <span
            role="tooltip"
            class="pointer-events-none absolute bottom-full left-1/2 z-10 mb-2 hidden -translate-x-1/2 whitespace-nowrap rounded-md bg-slate-900 px-2 py-1 text-xs text-white shadow-sm group-hover:block group-focus-visible:block dark:bg-slate-700"
        >Approximate theme colors</span>
    </span>
</template>
