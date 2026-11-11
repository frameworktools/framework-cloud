<script setup>
import { ref } from 'vue';
import UiCheckbox from '@/Components/UiCheckbox.vue';
import UiModal from '@/Components/UiModal.vue';
import UiButton from '@/Components/UiButton.vue';
const props = defineProps({ allSelected: Boolean, someSelected: Boolean, label: String });
const emit = defineEmits(['select', 'clear']);
const confirm = ref(false);
const click = () => {
    if (props.allSelected) emit('clear');
    else if (props.someSelected) confirm.value = true;
    else emit('select');
};
</script>

<template>
    <UiCheckbox :model-value="allSelected" :indeterminate="someSelected && !allSelected" :sr-label="allSelected ? `Deselect all visible ${label}` : `Select all visible ${label}`" @update:model-value="click" />
    <UiModal v-model="confirm" title="Select all?">
        <p class="text-sm text-slate-600 dark:text-slate-400">Select all {{ label }} in the current view?</p>
        <div class="flex justify-end gap-2">
            <UiButton variant="ghost" @click="confirm = false">Cancel</UiButton>
            <UiButton variant="primary" @click="emit('select'); confirm = false">Select all</UiButton>
        </div>
    </UiModal>
</template>
