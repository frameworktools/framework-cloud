<script setup>
// An artifact's embed: a live preview drawn by the framework-embed renderer,
// a choice of newest or pinned version, and the link and HTML to paste into
// another site. embed comes from ShareController::embed.
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import UiRadioCards from '@/Components/UiRadioCards.vue';
import UiLink from '@/Components/UiLink.vue';
import CopyBlock from '@/Components/CopyBlock.vue';
import { embedSnippet, loadEmbedRenderer } from '@/embed-snippet.js';

const props = defineProps({ embed: { type: Object, required: true }, title: { type: String, default: '' } });

const version = ref('newest');
const pinned = computed(() => version.value === 'pinned');
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
onMounted(async () => {
    themeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
    try {
        const [renderer, response] = await Promise.all([
            loadEmbedRenderer(),
            fetch(`/api/embed/v1/artifacts/${encodeURIComponent(props.embed.key)}`, { headers: { Accept: 'application/json' }, credentials: 'omit' }),
        ]);
        if (!response.ok) throw new Error(`Embed request failed: ${response.status}`);
        const doc = await response.json();
        if (!preview.value) return;
        renderer.mount(preview.value, doc, { lazy: false });
        status.value = 'ready';
    } catch {
        status.value = 'error';
    }
});
onBeforeUnmount(() => themeObserver.disconnect());
</script>

<template>
    <div class="flex min-w-0 flex-col gap-6">
        <section aria-label="Preview" class="min-w-0">
            <p class="text-sm font-medium text-slate-800 dark:text-white">Preview</p>
            <div class="mt-2 max-h-72 overflow-auto rounded-lg border border-slate-800/10 px-4 text-slate-900 focus-visible:outline-2 focus-visible:outline-sky-500 dark:border-white/15 dark:text-slate-100"
                tabindex="0" role="region" :aria-label="`Embed preview of ${title}`" :data-theme="dark ? 'dark' : 'light'">
                <p v-if="status === 'loading'" class="py-4 text-sm text-slate-500 dark:text-slate-400">Loading preview…</p>
                <p v-else-if="status === 'error'" class="py-4 text-sm text-slate-500 dark:text-slate-400">The preview didn't load. The link and HTML still work.</p>
                <div ref="preview" class="embed-preview" />
            </div>
        </section>

        <UiRadioCards v-model="version" name="embed-version" legend="Version" :options="[
            { value: 'newest', label: 'Newest version', description: 'Updates when you publish again.' },
            { value: 'pinned', label: `This version (v${embed.latest_version})`, description: 'Stays as it is now.' },
        ]" />

        <div class="flex min-w-0 flex-col gap-2">
            <p class="text-sm font-medium text-slate-800 dark:text-white">Link</p>
            <p class="text-sm text-slate-500 dark:text-slate-400">
                Paste into <UiLink href="https://academic.page" external>Academic.page</UiLink>,
                or into <UiLink href="https://github.com/frameworktools/framework-embed/releases/latest" external>WordPress</UiLink>,
                <UiLink href="https://github.com/frameworktools/framework-embed/tree/main/drupal" external>Drupal</UiLink>, or
                <UiLink href="https://github.com/frameworktools/framework-embed/tree/main/statamic" external>Statamic</UiLink> with the Framework plugin.
            </p>
            <CopyBlock :value="link" label="embed link" kind="value" />
        </div>

        <div class="flex min-w-0 flex-col gap-2">
            <p class="text-sm font-medium text-slate-800 dark:text-white">HTML</p>
            <p class="text-sm text-slate-500 dark:text-slate-400">Paste into any web page.</p>
            <CopyBlock :value="html" label="embed HTML" />
        </div>

        <p class="text-sm text-slate-500 dark:text-slate-400">
            The <UiLink href="/docs/embedding" external>embedding guide</UiLink> covers each platform.
        </p>
    </div>
</template>

<style scoped>
/* The renderer's figure sits flush in the preview box, which supplies the frame */
.embed-preview :deep(.fw-embed) { margin: 1rem 0; }
</style>
