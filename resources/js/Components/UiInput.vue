<script setup>
import { useId } from 'vue';
import UiField from '@/Components/UiField.vue';
import { inputClass } from '@/ui.js';

defineProps({
    label: String,
    description: String,
    error: String,
    type: { type: String, default: 'text' },
    placeholder: String,
    required: Boolean,
    autocomplete: String,
    autofocus: Boolean,
    optional: Boolean,
});

const id = useId();
const model = defineModel({ type: [String, Number], default: '' });
</script>

<template>
    <UiField :id="id" :label="label" :description="description" :error="error" :optional="optional">
        <input
            :id="id" v-model="model" :type="type" :placeholder="placeholder" :required="required" :autocomplete="autocomplete" :autofocus="autofocus"
            :aria-invalid="error ? 'true' : undefined" :aria-describedby="error ? `${id}-error` : description ? `${id}-description` : undefined"
            :class="inputClass"
        >
    </UiField>
</template>
