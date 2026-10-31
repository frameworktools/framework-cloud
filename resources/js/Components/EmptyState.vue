<script setup>
defineProps({ icon: [Object, Function], title: String, description: String, compact: Boolean });
</script>

<!-- What a list shows when it has nothing in it: an icon, a sentence, and the main action -->
<template>
    <div
        class="flex flex-col items-center rounded-lg border-2 border-dashed border-slate-300 px-6 text-center dark:border-slate-700"
        :class="compact ? 'py-8' : 'py-12'"
    >
        <component :is="icon" v-if="icon" class="text-slate-400 dark:text-slate-500" :class="compact ? 'size-8' : 'size-10'" aria-hidden="true" />
        <h3 class="mt-3 text-sm font-semibold text-slate-900 dark:text-white">{{ title }}</h3>
        <p v-if="description || $slots.default" class="mt-1 max-w-md text-sm text-slate-500 dark:text-slate-400">
            <slot>{{ description }}</slot>
        </p>
        <div v-if="$slots.actions" class="flex flex-wrap justify-center gap-2" :class="compact ? 'mt-4' : 'mt-6'">
            <slot name="actions" />
        </div>
    </div>
</template>
