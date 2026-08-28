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
        <label v-if="label" class="text-sm font-medium text-neutral-800 dark:text-neutral-200">
            {{ label }}
        </label>
        <select
            v-model="model"
            class="w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm text-neutral-900 focus:border-orange-500 focus:outline-none focus:ring-1 focus:ring-orange-500 dark:border-neutral-600 dark:bg-neutral-900 dark:text-neutral-100"
        >
            <option v-for="option in options" :key="option.value" :value="option.value">
                {{ option.label }}
            </option>
        </select>
        <p v-if="description" class="text-xs text-neutral-500 dark:text-neutral-400">{{ description }}</p>
        <p v-if="error" class="text-xs text-red-600 dark:text-red-400">{{ error }}</p>
    </div>
</template>
