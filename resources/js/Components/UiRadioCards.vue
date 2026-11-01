<script setup>
// A choice of one, as compact cards with a visible radio. The radios are real
// inputs, so arrow keys move between them.
defineProps({
    name: { type: String, required: true },
    legend: String,
    // The page heading already names the choice: keep the legend for screen readers only
    hideLegend: Boolean,
    options: { type: Array, required: true }, // [{ value, label, description? }]
    columns: { type: String, default: 'sm:grid-cols-2' },
});

const model = defineModel({ type: String });
</script>

<template>
    <fieldset>
        <legend v-if="legend" :class="hideLegend ? 'sr-only' : 'mb-3'" class="text-sm font-semibold text-slate-900 dark:text-white">{{ legend }}</legend>
        <div class="grid gap-3" :class="columns">
            <label
                v-for="option in options" :key="option.value"
                class="flex cursor-pointer items-start gap-3 rounded-lg border px-4 py-3 transition has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-sky-500"
                :class="model === option.value
                    ? 'border-sky-500 bg-sky-50 dark:border-sky-500 dark:bg-sky-950/40'
                    : 'border-slate-200 hover:border-slate-300 dark:border-slate-700 dark:hover:border-slate-600'"
            >
                <input v-model="model" type="radio" :name="name" :value="option.value" class="mt-0.5 size-4 shrink-0 accent-sky-600 focus:outline-none">
                <span class="flex min-w-0 flex-col gap-0.5">
                    <span class="text-sm font-medium text-slate-900 dark:text-white">{{ option.label }}</span>
                    <span v-if="option.description" class="text-xs text-slate-500 dark:text-slate-400">{{ option.description }}</span>
                </span>
            </label>
        </div>
    </fieldset>
</template>
