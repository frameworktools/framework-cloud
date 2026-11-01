<script setup>
// One of several things that can be picked together
defineProps({ label: String, description: String, disabled: Boolean });

const model = defineModel({ type: Boolean, default: false });
</script>

<template>
    <label class="flex items-start gap-3" :class="disabled ? 'cursor-not-allowed opacity-50' : 'cursor-pointer'">
        <input v-model="model" type="checkbox" :disabled="disabled" class="mt-0.5 size-4 shrink-0 rounded accent-sky-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-500">
        <span class="flex flex-col gap-0.5">
            <span class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ label }}</span>
            <span v-if="description" class="text-xs text-slate-500 dark:text-slate-400">{{ description }}</span>
        </span>
    </label>
</template>
