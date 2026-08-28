<script setup>
defineProps({
    variant: { type: String, default: 'primary' }, // primary | outline | danger | ghost
    size: { type: String, default: 'md' }, // sm | md
    type: { type: String, default: 'button' },
    disabled: Boolean,
});

const variants = {
    primary: 'bg-orange-500 text-white hover:bg-orange-600 disabled:bg-orange-300',
    outline: 'border border-neutral-300 text-neutral-800 hover:bg-neutral-50 dark:border-neutral-600 dark:text-neutral-100 dark:hover:bg-neutral-800',
    danger: 'bg-red-600 text-white hover:bg-red-700 disabled:bg-red-300',
    ghost: 'text-neutral-600 hover:bg-neutral-100 dark:text-neutral-300 dark:hover:bg-neutral-800',
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
        class="inline-flex items-center justify-center gap-2 rounded-md font-medium transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-orange-500 focus-visible:ring-offset-2 disabled:cursor-not-allowed dark:focus-visible:ring-offset-neutral-950"
        :class="[variants[variant], sizes[size]]"
    >
        <slot />
    </button>
</template>
