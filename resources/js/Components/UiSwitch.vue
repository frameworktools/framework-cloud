<script setup>
defineProps({
    label: String,
    description: String,
});

const model = defineModel({ type: Boolean, default: false });
</script>

<template>
    <label class="flex cursor-pointer items-start justify-between gap-4">
        <span class="flex flex-col gap-0.5">
            <span class="text-sm font-medium text-neutral-800 dark:text-neutral-200">{{ label }}</span>
            <span v-if="description" class="text-xs text-neutral-500 dark:text-neutral-400">{{ description }}</span>
        </span>
        <button
            type="button"
            role="switch"
            :aria-checked="model"
            class="relative mt-0.5 inline-flex h-5 w-9 shrink-0 items-center rounded-full transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-orange-500 focus-visible:ring-offset-2"
            :class="model ? 'bg-orange-500' : 'bg-neutral-300 dark:bg-neutral-600'"
            @click="model = !model"
        >
            <span
                class="inline-block size-3.5 transform rounded-full bg-white transition-transform"
                :class="model ? 'translate-x-[1.125rem]' : 'translate-x-1'"
            />
        </button>
    </label>
</template>
