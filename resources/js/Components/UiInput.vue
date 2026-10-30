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
        <label v-if="label" class="text-sm font-medium text-slate-800 dark:text-slate-200">
            {{ label }}
        </label>
        <input
            v-model="model"
            :type="type"
            :placeholder="placeholder"
            :required="required"
            :autocomplete="autocomplete"
            :autofocus="autofocus"
            class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 placeholder-slate-400 focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100"
            :class="{ 'border-red-500 dark:border-red-500': error }"
        >
        <p v-if="description" class="text-xs text-slate-500 dark:text-slate-400">{{ description }}</p>
        <p v-if="error" class="text-xs text-red-600 dark:text-red-400">{{ error }}</p>
    </div>
</template>
