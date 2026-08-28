<script setup>
defineProps({
    label: String,
    description: String,
    error: String,
    type: { type: String, default: 'text' },
    placeholder: String,
    required: Boolean,
    autocomplete: String,
    autofocus: Boolean,
});

const model = defineModel({ type: [String, Number], default: '' });
</script>

<template>
    <div class="flex flex-col gap-1.5">
        <label v-if="label" class="text-sm font-medium text-neutral-800 dark:text-neutral-200">
            {{ label }}
        </label>
        <input
            v-model="model"
            :type="type"
            :placeholder="placeholder"
            :required="required"
            :autocomplete="autocomplete"
            :autofocus="autofocus"
            class="w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm text-neutral-900 placeholder-neutral-400 focus:border-orange-500 focus:outline-none focus:ring-1 focus:ring-orange-500 dark:border-neutral-600 dark:bg-neutral-900 dark:text-neutral-100"
            :class="{ 'border-red-500 dark:border-red-500': error }"
        >
        <p v-if="description" class="text-xs text-neutral-500 dark:text-neutral-400">{{ description }}</p>
        <p v-if="error" class="text-xs text-red-600 dark:text-red-400">{{ error }}</p>
    </div>
</template>
