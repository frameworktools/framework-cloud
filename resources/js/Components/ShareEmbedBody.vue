<script setup>
// An artifact's embed: a live preview drawn by the framework-embed renderer,
// a switch to pin the current version, and the link, image, and HTML to paste into
// another site. embed comes from ShareController::embed.
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import UiSwitch from '@/Components/UiSwitch.vue';
import UiLink from '@/Components/UiLink.vue';
import CopyBlock from '@/Components/CopyBlock.vue';
import UiButton from '@/Components/UiButton.vue';
import { embedSnippet, loadEmbedRenderer } from '@/embed-snippet.js';
import { copyImage, downloadImage, embedImage } from '@/embed-image.js';

const props = defineProps({ embed: { type: Object, required: true }, title: { type: String, default: '' } });

const pinnedSwitch = ref(false);
const pinned = computed(() => pinnedSwitch.value);
const expanded = ref(false);
const link = computed(() => (pinned.value ? props.embed.version_link : props.embed.link));
const html = computed(() => embedSnippet({
    src: pinned.value ? props.embed.version_snippet_src : props.embed.snippet_src,
    href: link.value,
    title: props.title,
}));

// The renderer's chart theme follows the page's dark class, so the preview follows the theme toggle
const dark = ref(document.documentElement.classList.contains('dark'));
const themeObserver = new MutationObserver(() => { dark.value = document.documentElement.classList.contains('dark'); });

const preview = ref(null);
const status = ref('loading');
let renderer = null;
let embedDoc = null;
onMounted(async () => {
    themeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
    try {
        const [loaded, response] = await Promise.all([
            loadEmbedRenderer(),
            fetch(`/api/embed/v1/artifacts/${encodeURIComponent(props.embed.key)}`, { headers: { Accept: 'application/json' }, credentials: 'omit' }),
        ]);
        if (!response.ok) throw new Error(`Embed request failed: ${response.status}`);
        const doc = await response.json();
        if (!preview.value) return;
        [renderer, embedDoc] = [loaded, doc];
        renderer.mount(preview.value, doc, { lazy: false });
        status.value = 'ready';
    } catch {
        status.value = 'error';
    }
});
onBeforeUnmount(() => themeObserver.disconnect());

// The image: copied to the clipboard, or saved where the clipboard can't take images
const imageState = ref('idle'); // idle | working | copied | saved | error
const fileName = computed(() => (props.title || 'artifact').replace(/[^A-Za-z0-9._-]+/g, '-').replace(/^-+|-+$/g, '').toLowerCase() || 'artifact');
const settle = (state) => { imageState.value = state; setTimeout(() => { if (imageState.value === state) imageState.value = 'idle'; }, 2000); };
async function copyAsImage() {
    if (!renderer || imageState.value === 'working') return;
    imageState.value = 'working';
    const blob = embedImage(renderer, embedDoc, link.value);
    try {
        await copyImage(blob);
        settle('copied');
    } catch {
        try {
            downloadImage(await blob, fileName.value);
            settle('saved');
        } catch {
            settle('error');
        }
    }
}
async function downloadAsImage() {
    if (!renderer || imageState.value === 'working') return;
    imageState.value = 'working';
    try {
        downloadImage(await embedImage(renderer, embedDoc, link.value), fileName.value);
        settle('saved');
    } catch {
        settle('error');
    }
}
</script>

<template>
    <div class="flex min-w-0 flex-col gap-5">
        <section aria-label="Preview" class="min-w-0">
            <div class="flex items-center justify-between gap-3">
                <p class="text-sm font-medium text-slate-800 dark:text-white">Preview</p>
                <button v-if="status === 'ready'" type="button" class="text-sm font-medium text-sky-700 hover:underline dark:text-sky-400" :aria-expanded="expanded" @click="expanded = !expanded">{{ expanded ? 'Collapse' : 'Expand' }}</button>
            </div>
            <div class="mt-2 overflow-auto rounded-lg border border-slate-800/10 px-4 text-slate-900 focus-visible:outline-2 focus-visible:outline-sky-500 dark:border-white/15 dark:text-slate-100"
                :class="expanded ? '' : 'max-h-64'" tabindex="0" role="region" :aria-label="`Embed preview of ${title}`" :data-theme="dark ? 'dark' : 'light'">
                <p v-if="status === 'loading'" class="py-4 text-sm text-slate-500 dark:text-slate-400">Loading preview…</p>
                <p v-else-if="status === 'error'" class="py-4 text-sm text-slate-500 dark:text-slate-400">Could not load preview.</p>
                <div ref="preview" class="embed-preview" />
            </div>
        </section>

        <UiSwitch v-model="pinnedSwitch" :label="`Pin to version ${embed.latest_version}`" description="When off, the link and HTML follow the newest version." />

        <div class="flex min-w-0 flex-col gap-2">
            <p class="text-sm text-slate-600 dark:text-slate-300">
                <span class="font-medium text-slate-800 dark:text-white">Link.</span>
                Paste into <UiLink href="https://academic.page" external>Academic.page</UiLink>,
                <UiLink href="https://github.com/frameworktools/framework-embed/releases/latest" external>WordPress</UiLink>,
                <UiLink href="https://github.com/frameworktools/framework-embed/tree/main/drupal" external>Drupal</UiLink>, or
                <UiLink href="https://github.com/frameworktools/framework-embed/tree/main/statamic" external>Statamic</UiLink>.
            </p>
            <CopyBlock :value="link" label="embed link" kind="value" />
        </div>

        <div class="flex min-w-0 flex-wrap items-center justify-between gap-x-4 gap-y-2">
            <p class="text-sm text-slate-600 dark:text-slate-300"><span class="font-medium text-slate-800 dark:text-white">Image.</span> Copy or download a PNG for Substack, email, and slides.</p>
            <div class="flex items-center gap-2">
                <span class="text-sm text-slate-500 dark:text-slate-400" role="status">{{ imageState === 'saved' ? 'Downloaded' : imageState === 'error' ? 'Could not create image.' : '' }}</span>
                <UiButton size="sm" :disabled="status !== 'ready' || imageState === 'working'" @click="copyAsImage">{{ imageState === 'working' ? 'Generating image…' : imageState === 'copied' ? 'Copied' : 'Copy image' }}</UiButton>
                <UiButton size="sm" variant="ghost" :disabled="status !== 'ready' || imageState === 'working'" @click="downloadAsImage">Download PNG</UiButton>
            </div>
        </div>

        <details class="group min-w-0">
            <summary class="cursor-pointer text-sm text-slate-600 marker:text-slate-400 dark:text-slate-300"><span class="font-medium text-slate-800 dark:text-white">HTML.</span> Paste into any page that accepts HTML.</summary>
            <div class="mt-2"><CopyBlock :value="html" label="embed HTML" /></div>
        </details>

        <p class="text-xs text-slate-500 dark:text-slate-400">
            See <UiLink href="/docs/embedding" external>Embedding artifacts</UiLink> for setup instructions.
        </p>
    </div>
</template>

<style scoped>
/* The renderer's figure sits flush in the preview box, which supplies the frame */
.embed-preview :deep(.fw-embed) { margin: 1rem 0; }
</style>
