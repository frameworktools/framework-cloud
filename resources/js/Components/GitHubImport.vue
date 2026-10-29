<script setup>
import { useForm } from '@inertiajs/vue3';
import UiButton from '@/Components/UiButton.vue';
import UiInput from '@/Components/UiInput.vue';

// No <form> element, so it can sit inside another form
const props = defineProps({ action: String, label: String, placeholder: String });

const form = useForm({ url: '' });
const submit = () => form.post(props.action, { preserveScroll: true });
</script>

<template>
    <div class="flex flex-wrap items-end gap-2">
        <div class="w-full max-w-md"><UiInput v-model="form.url" :label="label" :placeholder="placeholder" :error="form.errors.url" @keydown.enter.prevent="submit" /></div>
        <UiButton variant="outline" :disabled="form.processing || !form.url" @click="submit">Import</UiButton>
    </div>
</template>
