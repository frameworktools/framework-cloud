<script setup>
defineProps({
    heading: String,
    variant: { type: String, default: 'info' }, // info | success | danger
});

const variants = {
    info: 'border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-900',
    success: 'border-emerald-200 bg-emerald-50 dark:border-emerald-800/70 dark:bg-emerald-950/40',
    danger: 'border-red-200 bg-red-50 dark:border-red-900 dark:bg-red-950/40',
};
</script>

<template>
    <div class="rounded-lg border p-4" :class="variants[variant]">
        <div v-if="heading" class="mb-1.5 text-sm font-semibold text-slate-900 dark:text-slate-100">
            {{ heading }}
        </div>
        <div class="flex flex-col gap-2 text-sm text-slate-700 dark:text-slate-300">
            <slot />
        </div>
    </div>
</template>
