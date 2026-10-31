<script setup>
defineProps({ hover: Boolean, padded: { type: Boolean, default: true } });
</script>

<!-- A bordered white card for one item in a list, as in the Framework GUI -->
<template>
    <div
        class="rounded-lg border border-slate-200 bg-white shadow-xs dark:border-slate-800 dark:bg-slate-900 dark:shadow-none"
        :class="[
            padded ? 'p-5' : '',
            hover ? 'transition hover:border-slate-300 hover:shadow-sm dark:hover:border-slate-700' : '',
        ]"
    >
        <slot />
    </div>
</template>
