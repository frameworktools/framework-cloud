<script setup>
// A choice of one, as compact cards with a visible radio. The radios are real
// inputs, so arrow keys move between them.
defineProps({
    name: { type: String, required: true },
    legend: String,
    // The page heading already names the choice: keep the legend for screen readers only
    hideLegend: Boolean,
    options: { type: Array, required: true }, // [{ value, label, description?, disabled?, badge? }]
    columns: { type: String, default: 'sm:grid-cols-2' },
    // One card per row, the radio at the right, for a few choices that each need a line of explanation
    stacked: Boolean,
});

const model = defineModel({ type: String });
</script>

<template>
    <fieldset>
        <legend v-if="legend" :class="hideLegend ? 'sr-only' : 'mb-3'" class="text-sm font-semibold text-slate-900 dark:text-white">{{ legend }}</legend>
        <div class="grid gap-3" :class="stacked ? 'max-w-md' : columns">
            <label
                v-for="option in options" :key="option.value"
                class="flex items-start gap-3 rounded-lg border transition has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-sky-500"
                :class="[
                    stacked ? 'flex-row-reverse justify-between px-5 py-4' : 'px-4 py-3',
                    option.disabled ? 'cursor-not-allowed opacity-60' : 'cursor-pointer',
                    model === option.value
                        ? 'border-sky-500 bg-sky-50 dark:border-sky-500 dark:bg-sky-950/40'
                        : 'border-slate-200 hover:border-slate-300 dark:border-slate-700 dark:hover:border-slate-600',
                ]"
            >
                <input v-model="model" type="radio" :name="name" :value="option.value" :disabled="option.disabled" class="mt-0.5 size-4 shrink-0 accent-sky-600 focus:outline-none">
                <span class="flex min-w-0 flex-col gap-0.5">
                    <span class="flex items-center gap-2">
                        <span class="font-medium text-slate-900 dark:text-white" :class="stacked ? 'text-base' : 'text-sm'">{{ option.label }}</span>
                        <span v-if="option.badge" class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ option.badge }}</span>
                    </span>
                    <span v-if="option.description" class="text-slate-500 dark:text-slate-400" :class="stacked ? 'mt-0.5 text-sm' : 'text-xs'">{{ option.description }}</span>
                </span>
            </label>
        </div>
    </fieldset>
</template>
