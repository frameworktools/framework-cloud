<script setup>
import { useId } from 'vue';
import { ArrowTopRightOnSquareIcon, ChevronRightIcon } from '@heroicons/vue/20/solid';

// One setting as a compact row: the label and a short explanation at the
// left, the control at the right, and an optional "Learn more" disclosure
// (the more prop) for the longer explanation. The control slot gets the ids it should use:
// `id` for the control itself and `describedby` for its explanation.
defineProps({
    label: { type: String, required: true },
    description: String,
    // The longer explanation, behind "Learn more"
    more: String,
    // A page elsewhere that shows the choices, such as a theme gallery, linked under Learn more: { href, label }
    example: Object,
});

const id = useId();
</script>

<template>
    <div class="px-4 py-3">
        <div class="flex flex-wrap items-center justify-between gap-x-6 gap-y-2">
            <div class="min-w-0 flex-1 basis-56">
                <label :id="`${id}-label`" :for="`${id}-control`" class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ label }}</label>
                <p v-if="description" :id="`${id}-description`" class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ description }}</p>
            </div>
            <div class="shrink-0">
                <slot :id="`${id}-control`" :labelledby="`${id}-label`" :describedby="description ? `${id}-description` : undefined" />
            </div>
        </div>
        <details v-if="more || example" class="group mt-1.5">
            <summary class="inline-flex cursor-pointer list-none items-center gap-1 rounded text-xs font-medium text-sky-700 hover:underline focus-visible:outline-2 focus-visible:outline-sky-500 dark:text-sky-400 [&::-webkit-details-marker]:hidden">
                Learn more<span class="sr-only"> about {{ label }}</span>
                <ChevronRightIcon class="size-3.5 transition-transform group-open:rotate-90" aria-hidden="true" />
            </summary>
            <div class="mt-1.5 max-w-prose text-xs leading-relaxed text-slate-600 dark:text-slate-400">
                {{ more }}
                <a
                    v-if="example" :href="example.href" target="_blank" rel="noopener noreferrer"
                    class="inline-flex items-center gap-1 rounded font-medium text-sky-700 hover:underline focus-visible:outline-2 focus-visible:outline-sky-500 dark:text-sky-400"
                >{{ example.label }}<ArrowTopRightOnSquareIcon class="size-3" aria-hidden="true" /><span class="sr-only"> (opens in a new tab)</span></a>
            </div>
        </details>
    </div>
</template>
