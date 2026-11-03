<script setup>
// small: a quieter subtitle, one size down
defineProps({ title: String, description: String, small: Boolean });
</script>

<template>
    <div class="flex flex-wrap items-start justify-between gap-x-6 gap-y-4">
        <div class="min-w-48 flex-1">
            <slot name="eyebrow" />
            <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-white"><slot name="title">{{ title }}</slot></h1>
            <p v-if="description || $slots.description" class="mt-2 max-w-2xl" :class="small ? 'text-[0.8125rem] text-slate-500 dark:text-slate-400' : 'text-sm text-slate-500 dark:text-slate-400'">
                <slot name="description">{{ description }}</slot>
            </p>
        </div>
        <div v-if="$slots.actions" class="flex shrink-0 flex-wrap items-center gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>
