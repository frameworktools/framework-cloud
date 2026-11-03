<script setup>
import { useId } from 'vue';
import UiField from '@/Components/UiField.vue';
import { textareaClass } from '@/ui.js';

defineProps({
    label: String,
    description: String,
    error: String,
    placeholder: String,
    rows: { type: Number, default: 6 },
    code: Boolean, // monospace, no spellcheck: for YAML and Markdown sources
    optional: Boolean,
});

const id = useId();
const model = defineModel({ type: String, default: '' });
</script>

<template>
    <UiField :id="id" :label="label" :description="description" :error="error" :optional="optional">
        <textarea
            :id="id" v-model="model" :rows="rows" :placeholder="placeholder" :spellcheck="!code"
            :aria-invalid="error ? 'true' : undefined" :class="[textareaClass, code && 'font-mono text-xs leading-relaxed']"
        />
    </UiField>
</template>
