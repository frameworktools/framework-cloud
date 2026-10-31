<script setup>
defineProps({ title: String, description: String });
</script>

<!-- The large page title from the Framework GUI, with actions on the right -->
<template>
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0 flex-1">
            <h1 class="text-3xl font-bold tracking-tight text-slate-900 dark:text-white">{{ title }}</h1>
            <p v-if="description || $slots.description" class="mt-2 max-w-2xl text-sm text-slate-600 dark:text-slate-400">
                <slot name="description">{{ description }}</slot>
            </p>
        </div>
        <div v-if="$slots.actions" class="flex shrink-0 items-center gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>
