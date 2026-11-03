<script setup>
import { labelClass, descriptionClass, errorClass } from '@/ui.js';

// A label, its control, and the help and error under it. The control takes the ids it is given.
defineProps({ id: String, label: String, description: String, error: String, optional: Boolean });
</script>

<template>
    <div class="flex flex-col gap-2">
        <label v-if="label" :for="id" :class="labelClass">{{ label }}<span v-if="optional" class="ml-1 font-normal text-slate-400">(optional)</span></label>
        <slot />
        <p v-if="description || $slots.description" :id="id && `${id}-description`" :class="descriptionClass"><slot name="description">{{ description }}</slot></p>
        <p v-if="error" :id="id && `${id}-error`" :class="errorClass" class="whitespace-pre-line">{{ error }}</p>
    </div>
</template>
