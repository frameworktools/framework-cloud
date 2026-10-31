<script setup>
defineProps({
    variant: { type: String, default: 'primary' }, // primary | outline | danger | ghost
    size: { type: String, default: 'md' }, // sm | md
    type: { type: String, default: 'button' },
    disabled: Boolean,
});

const variants = {
    primary: 'bg-sky-600 text-white hover:bg-sky-700 disabled:bg-sky-300 dark:disabled:bg-sky-900 dark:disabled:text-sky-300/60',
    outline: 'border border-slate-300 text-slate-800 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-100 dark:hover:bg-slate-800',
    danger: 'bg-red-600 text-white hover:bg-red-700 disabled:bg-red-300',
    ghost: 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800',
};

const sizes = {
    sm: 'px-2.5 py-1.5 text-xs',
    md: 'px-4 py-2 text-sm',
};
</script>

<template>
    <button
        :type="type"
        :disabled="disabled"
        class="inline-flex items-center justify-center gap-2 rounded-md font-medium transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-500 focus-visible:ring-offset-2 disabled:cursor-not-allowed dark:focus-visible:ring-offset-slate-950"
        :class="[variants[variant], sizes[size]]"
    >
        <slot />
    </button>
</template>
