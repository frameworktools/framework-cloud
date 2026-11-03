<script setup>
defineProps({
    padded: { type: Boolean, default: true },
    hover: Boolean,
    size: { type: String, default: 'md' }, // md | sm
});
</script>

<template>
    <div
        class="border border-slate-200 bg-white dark:border-white/10 dark:bg-white/5"
        :class="[
            size === 'sm' ? 'rounded-lg' : 'rounded-xl',
            padded ? (size === 'sm' ? 'p-4' : 'p-6') : '',
            hover ? 'transition-colors hover:border-slate-300 dark:hover:border-white/20' : '',
        ]"
    >
        <slot />
    </div>
</template>
