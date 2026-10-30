<script setup>
defineProps({
    heading: String,
    variant: { type: String, default: 'info' }, // info | success | danger
});

const variants = {
    info: 'border-neutral-200 bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-900',
    success: 'border-emerald-200 bg-emerald-50 dark:border-emerald-800/70 dark:bg-emerald-950/40',
    danger: 'border-red-200 bg-red-50 dark:border-red-900 dark:bg-red-950/40',
};
</script>

<template>
    <div class="rounded-lg border p-4" :class="variants[variant]">
        <div v-if="heading" class="mb-1.5 text-sm font-semibold text-neutral-900 dark:text-neutral-100">
            {{ heading }}
        </div>
        <div class="flex flex-col gap-2 text-sm text-neutral-700 dark:text-neutral-300">
            <slot />
        </div>
    </div>
</template>
