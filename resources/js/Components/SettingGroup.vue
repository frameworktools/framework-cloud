<script setup>
// A titled group of SettingRows, with subtle lines between them
defineProps({
    title: { type: String, required: true },
    description: String,
});
</script>

<template>
    <section class="flex flex-col gap-2">
        <div>
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ title }}</h3>
            <p v-if="description" class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ description }}</p>
        </div>
        <div class="divide-y divide-slate-200 rounded-lg border border-slate-200 bg-white dark:divide-slate-800 dark:border-slate-700 dark:bg-slate-900">
            <slot />
        </div>
    </section>
</template>
