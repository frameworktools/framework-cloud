<script setup>
import { useId } from 'vue';
import UiField from '@/Components/UiField.vue';
import { selectClass } from '@/ui.js';

defineProps({
    label: String,
    description: String,
    error: String,
    // [{ value, label }]
    options: { type: Array, default: () => [] },
});

const id = useId();
const model = defineModel({ type: [String, Number], default: '' });
</script>

<template>
    <UiField :id="id" :label="label" :description="description" :error="error">
        <select :id="id" v-model="model" :class="selectClass" :aria-invalid="error ? 'true' : undefined" :aria-describedby="description ? `${id}-description` : undefined">
            <slot><option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option></slot>
        </select>
    </UiField>
</template>
