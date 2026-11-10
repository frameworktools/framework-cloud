<script setup>
// A thing's short link and QR code, to copy or download into slides and
// handouts. share comes from App\Support\Share::for; null for private things.
import UiPanel from '@/Components/UiPanel.vue';
import ShareQrBody from '@/Components/ShareQrBody.vue';

// Without a share (private things), the panel says how to get one
defineProps({ share: { type: Object, default: null }, unavailable: { type: String, default: null } });
</script>

<template>
    <UiPanel v-if="share || unavailable" title="QR code" description="Put it on a slide or handout so people can open this on their phones. The short link is easy to type.">
        <ShareQrBody v-if="share" :share="share" />
        <div v-else class="flex items-center gap-4 text-sm text-slate-600 dark:text-slate-300">
            <div class="grid size-24 shrink-0 place-items-center rounded-md border border-dashed border-slate-300 text-xs text-slate-400 dark:border-white/20">QR code</div>
            <p>{{ unavailable }}</p>
        </div>
    </UiPanel>
</template>
