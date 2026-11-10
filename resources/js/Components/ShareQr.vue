<script setup>
// A thing's short link and QR code, to copy or download into slides and
// handouts. share comes from App\Support\Share::for; null for private things.
import UiPanel from '@/Components/UiPanel.vue';
import ShareQrBody from '@/Components/ShareQrBody.vue';

defineProps({ share: { type: Object, default: null } });
</script>

<template>
    <UiPanel v-if="share" title="QR code" description="Put it on a slide or handout so people can open this on their phones. The short link is easy to type.">
        <ShareQrBody :share="share" />
    </UiPanel>
</template>
