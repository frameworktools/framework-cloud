<script setup>
// A titled group of content on a page. Sections stack with a rule between them (wrap them
// in a "divide-y" container or use UiSeparator); they never sit on a grey panel.
defineProps({ title: String, description: String, id: String });
</script>

<template>
    <section :aria-labelledby="id && title ? `${id}-title` : undefined" class="flex flex-col gap-6">
        <div v-if="title || $slots.actions" class="flex flex-wrap items-start justify-between gap-x-6 gap-y-3">
            <div class="min-w-0 flex-1 basis-64">
                <h2 v-if="title" :id="id && `${id}-title`" class="text-base font-medium text-slate-900 dark:text-white">{{ title }}</h2>
                <p v-if="description || $slots.description" class="mt-1 max-w-2xl text-sm text-slate-500 dark:text-slate-400"><slot name="description">{{ description }}</slot></p>
            </div>
            <div v-if="$slots.actions" class="flex shrink-0 flex-wrap items-center gap-2"><slot name="actions" /></div>
        </div>
        <slot />
    </section>
</template>
