<script setup>
defineProps({ title: String, description: String, flush: Boolean });
</script>

<!-- A group of related fields on a gray panel, as in the Framework GUI's settings -->
<template>
    <section
        class="flex flex-col gap-6"
        :class="flush ? '' : 'rounded-lg bg-slate-50 p-6 dark:bg-slate-800/50'"
    >
        <header v-if="title || description || $slots.description || $slots.header" class="flex flex-wrap items-start justify-between gap-3">
            <div class="flex flex-col gap-1">
                <h2 v-if="title" class="text-lg font-semibold text-slate-900 dark:text-white">{{ title }}</h2>
                <p v-if="description || $slots.description" class="text-sm text-slate-600 dark:text-slate-400">
                    <slot name="description">{{ description }}</slot>
                </p>
            </div>
            <slot name="header" />
        </header>

        <slot />

        <div
            v-if="$slots.actions"
            class="flex justify-end gap-2"
            :class="flush ? '' : 'border-t border-slate-200 pt-4 dark:border-slate-700'"
        >
            <slot name="actions" />
        </div>
    </section>
</template>
