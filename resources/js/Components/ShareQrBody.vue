<script setup>
// A short link and QR code with copy and download buttons. share comes from App\Support\Share::for.
import { ref } from 'vue';
import UiButton from '@/Components/UiButton.vue';

defineProps({ share: { type: Object, required: true } });
const copied = ref('');
const flash = (what) => { copied.value = what; setTimeout(() => { copied.value = ''; }, 1500); };
const copyLink = (url) => navigator.clipboard.writeText(url).then(() => flash('link'));
// A PNG on the clipboard pastes straight into Keynote, PowerPoint, and Google Slides
const copyImage = (url) => navigator.clipboard.write([new ClipboardItem({ 'image/png': fetch(url).then((r) => r.blob()) })])
    .then(() => flash('image'), () => flash('failed'));
</script>

<template>
    <div class="flex flex-wrap items-start gap-6">
            <img :src="share.qr_svg" :alt="`QR code for ${share.short}`" class="size-36 rounded-md border border-slate-200 bg-white p-1 dark:border-white/10">
            <div class="flex min-w-0 flex-1 basis-56 flex-col gap-3">
                <p class="break-all font-mono text-lg font-semibold text-slate-900 dark:text-white">{{ share.short }}</p>
                <div class="flex flex-wrap gap-2">
                    <UiButton variant="ghost" size="sm" @click="copyLink(share.short_url)">{{ copied === 'link' ? 'Copied' : 'Copy link' }}</UiButton>
                    <UiButton variant="ghost" size="sm" @click="copyImage(share.qr_png)">{{ copied === 'image' ? 'Copied' : copied === 'failed' ? 'Use Download PNG' : 'Copy QR image' }}</UiButton>
                    <UiButton variant="ghost" size="sm" :href="share.qr_png_download" external>Download PNG</UiButton>
                    <UiButton variant="ghost" size="sm" :href="share.qr_svg_download" external>Download SVG</UiButton>
                </div>
            </div>
        </div>
</template>
