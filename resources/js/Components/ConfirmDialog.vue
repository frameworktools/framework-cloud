<script setup>
import { computed } from 'vue';
import UiButton from '@/Components/UiButton.vue';
import UiModal from '@/Components/UiModal.vue';
import { answer, confirmState } from '@/confirm.js';

// Closing with Escape or the backdrop counts as Cancel
const open = computed({ get: () => confirmState.open, set: (value) => { if (!value) answer(false); } });
</script>

<template>
    <UiModal v-model="open" :title="confirmState.title">
        <p class="text-sm text-slate-600 dark:text-slate-400">{{ confirmState.message }}</p>
        <div class="flex justify-end gap-2">
            <UiButton variant="outline" @click="answer(false)">Cancel</UiButton>
            <UiButton :variant="confirmState.danger ? 'danger' : 'primary'" @click="answer(true)">{{ confirmState.action }}</UiButton>
        </div>
    </UiModal>
</template>
