<script setup>
defineProps({
    variant: { type: String, default: 'slate' }, // slate | sky | amber | green | red
    mono: Boolean,
});

const variants = {
    slate: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
    sky: 'bg-sky-50 text-sky-700 ring-1 ring-inset ring-sky-600/20 dark:bg-sky-400/10 dark:text-sky-300 dark:ring-sky-400/20',
    amber: 'bg-amber-50 text-amber-800 ring-1 ring-inset ring-amber-600/20 dark:bg-amber-400/10 dark:text-amber-300 dark:ring-amber-400/20',
    green: 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/20 dark:bg-emerald-400/10 dark:text-emerald-300 dark:ring-emerald-400/20',
    red: 'bg-red-50 text-red-700 ring-1 ring-inset ring-red-600/20 dark:bg-red-400/10 dark:text-red-300 dark:ring-red-400/20',
};
</script>

<template>
    <span
        class="inline-flex items-center whitespace-nowrap rounded-md px-2 py-0.5 text-xs font-medium"
        :class="[variants[variant], mono ? 'font-mono font-normal' : '']"
    ><slot /></span>
</template>
