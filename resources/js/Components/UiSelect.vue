<script setup>
defineProps({
    label: String,
    description: String,
    error: String,
    // [{ value, label }]
    options: { type: Array, default: () => [] },
});

const model = defineModel({ type: [String, Number], default: '' });
</script>

<template>
    <div class="flex flex-col gap-1.5">
        <label v-if="label" class="text-sm font-medium text-slate-800 dark:text-slate-200">
            {{ label }}
        </label>
        <select
            v-model="model"
            class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100"
        >
            <option v-for="option in options" :key="option.value" :value="option.value">
                {{ option.label }}
            </option>
        </select>
        <p v-if="description" class="text-xs text-slate-500 dark:text-slate-400">{{ description }}</p>
        <p v-if="error" class="text-xs text-red-600 dark:text-red-400">{{ error }}</p>
    </div>
</template>
