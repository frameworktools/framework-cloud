<script setup>
// The share dialog a "…" menu opens: the thing's short link and QR code,
// loaded when it opens, or why it can't be shared yet.
import { ref, watch } from 'vue';
import UiModal from '@/Components/UiModal.vue';
import UiButton from '@/Components/UiButton.vue';
import ShareQrBody from '@/Components/ShareQrBody.vue';

// target: { type: 'artifact' | 'report' | 'collection' | 'blueprint' | 'project', id, title, settings? }
const props = defineProps({ target: { type: Object, default: null } });
const emit = defineEmits(['close']);
const show = ref(false);
const state = ref({ loading: true, share: null, reason: null });

watch(() => props.target, async (target) => {
    show.value = Boolean(target);
    if (!target) return;
    state.value = { loading: true, share: null, reason: null };
    try {
        const response = await fetch(`/share/${target.type}/${target.id}`, { headers: { Accept: 'application/json' } });
        const data = await response.json();
        state.value = { loading: false, share: data.share, reason: data.reason };
    } catch {
        state.value = { loading: false, share: null, reason: 'The share link could not be loaded. Try again.' };
    }
});
watch(show, (open) => { if (!open) emit('close'); });
</script>

<template>
    <UiModal v-model="show" :title="target ? `Share ${target.title}` : 'Share'">
        <div class="mt-4">
            <p v-if="state.loading" class="text-sm text-slate-500 dark:text-slate-400">Loading…</p>
            <ShareQrBody v-else-if="state.share" :share="state.share" />
            <div v-else class="flex flex-col items-start gap-3 text-sm text-slate-600 dark:text-slate-300">
                <p>{{ state.reason }}</p>
                <UiButton v-if="target?.settings" :href="target.settings" variant="ghost" size="sm">Open sharing settings</UiButton>
            </div>
        </div>
    </UiModal>
</template>
