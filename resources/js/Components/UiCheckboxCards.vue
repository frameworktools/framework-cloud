<script setup>
// Any number of choices, as stacked cards with a visible checkbox at the
// right, like UiRadioCards' stacked layout. The checkboxes are real inputs.
defineProps({
    legend: String,
    // The page heading already names the choice: keep the legend for screen readers only
    hideLegend: Boolean,
    options: { type: Array, required: true }, // [{ value, label, description?, dim? }]: dim for a choice that doesn't apply now
});

const model = defineModel({ type: Array, default: () => [] });
const toggle = (value, on) => {
    model.value = on ? [...model.value.filter((v) => v !== value), value] : model.value.filter((v) => v !== value);
};
</script>

<template>
    <fieldset>
        <legend v-if="legend" :class="hideLegend ? 'sr-only' : 'mb-3'" class="text-sm font-semibold text-slate-900 dark:text-white">{{ legend }}</legend>
        <div class="grid max-w-md gap-3">
            <label
                v-for="option in options" :key="option.value"
                class="flex cursor-pointer flex-row-reverse items-start justify-between gap-3 rounded-lg border px-5 py-4 transition has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-sky-500"
                :class="[option.dim ? 'opacity-60' : '', model.includes(option.value)
                    ? 'border-sky-500 bg-sky-50 dark:border-sky-500 dark:bg-sky-950/40'
                    : 'border-slate-200 hover:border-slate-300 dark:border-slate-700 dark:hover:border-slate-600']"
            >
                <input
                    type="checkbox" :value="option.value" :checked="model.includes(option.value)"
                    class="mt-0.5 size-4 shrink-0 rounded accent-sky-600 focus:outline-none"
                    @change="toggle(option.value, $event.target.checked)"
                >
                <span class="flex min-w-0 flex-col gap-0.5">
                    <span class="text-base font-medium text-slate-900 dark:text-white">{{ option.label }}</span>
                    <span v-if="option.description" class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">{{ option.description }}</span>
                </span>
            </label>
        </div>
    </fieldset>
</template>
