<script setup>
// A titled group of content in a bordered white card, like academic.page's "Featured" box.
// flush drops the card: the heading and content sit straight on the page.
defineProps({ title: String, description: String, flush: Boolean });
</script>

<template>
    <section
        class="flex flex-col gap-6"
        :class="flush ? '' : 'rounded-xl border border-slate-200 bg-white p-6 dark:border-white/10 dark:bg-white/5'"
    >
        <header v-if="title || description || $slots.description || $slots.header" class="flex flex-wrap items-start justify-between gap-3">
            <div class="flex min-w-0 flex-1 basis-64 flex-col gap-1">
                <h2 v-if="title" class="text-base font-medium text-slate-900 dark:text-white">{{ title }}</h2>
                <p v-if="description || $slots.description" class="max-w-2xl text-sm text-slate-500 dark:text-slate-400">
                    <slot name="description">{{ description }}</slot>
                </p>
            </div>
            <div v-if="$slots.header" class="flex shrink-0 flex-wrap items-center gap-2"><slot name="header" /></div>
        </header>

        <slot />

        <div
            v-if="$slots.actions"
            class="flex justify-end gap-2"
            :class="flush ? '' : 'border-t border-slate-800/10 pt-4 dark:border-white/10'"
        >
            <slot name="actions" />
        </div>
    </section>
</template>
