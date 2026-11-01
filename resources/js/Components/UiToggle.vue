<script setup>
// The switch alone, for rows that lay out their own label (SettingRow).
// Attributes such as id and aria-labelledby go on the button.
defineOptions({ inheritAttrs: false });
defineProps({ disabled: Boolean });

const model = defineModel({ type: Boolean, default: false });
</script>

<template>
    <button
        v-bind="$attrs"
        type="button"
        role="switch"
        :aria-checked="model"
        :disabled="disabled"
        class="relative inline-flex h-5 w-9 shrink-0 items-center rounded-full transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-900"
        :class="[model ? 'bg-sky-600' : 'bg-slate-300 dark:bg-slate-600', disabled ? 'cursor-not-allowed opacity-50' : 'cursor-pointer']"
        @click="model = !model"
    >
        <span class="inline-block size-3.5 transform rounded-full bg-white transition-transform" :class="model ? 'translate-x-[1.125rem]' : 'translate-x-1'" />
    </button>
</template>
