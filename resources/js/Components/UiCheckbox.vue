<script setup>
import { CheckIcon, MinusIcon } from '@heroicons/vue/20/solid';
import { nextTick } from 'vue';
const props = defineProps({ id: String, disabled: Boolean, srLabel: String, indeterminate: Boolean });
const model = defineModel({ type: Boolean, default: false });
const change = async event => {
    const input = event.target;
    model.value = input.checked;
    await nextTick();
    input.checked = model.value;
    input.indeterminate = props.indeterminate;
};
</script>

<template>
    <span class="relative inline-flex size-4 shrink-0 align-middle">
        <input :id="id" type="checkbox" :checked="model" :indeterminate="indeterminate" :disabled="disabled" :aria-label="srLabel"
            :aria-checked="indeterminate ? 'mixed' : model"
            class="size-4 cursor-pointer appearance-none rounded border border-slate-300 bg-white shadow-xs transition checked:border-sky-700 checked:bg-sky-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-white/25 dark:bg-white/5 dark:checked:border-sky-500 dark:checked:bg-sky-500"
            :class="indeterminate ? 'border-sky-700! bg-sky-700! dark:border-sky-500! dark:bg-sky-500!' : ''"
            @change="change" />
        <component :is="indeterminate ? MinusIcon : CheckIcon" v-if="model || indeterminate" class="pointer-events-none absolute inset-0 size-4 text-white" :class="disabled ? 'opacity-50' : ''" aria-hidden="true" />
    </span>
</template>
