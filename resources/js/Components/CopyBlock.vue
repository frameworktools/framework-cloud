<script setup>
import { computed, ref } from 'vue';
import { CheckIcon, ClipboardDocumentIcon } from '@heroicons/vue/24/outline';
import { highlight } from '@/highlight.js';

// A value or command to paste, with a Copy button. "value" sits on a light
// tint (tokens, keys); "command" is code on the dark command surface and may
// run over several lines, highlighted when its language is given.
const props = defineProps({
    value: { type: String, required: true },
    label: { type: String, required: true }, // names the copy button for screen readers
    kind: { type: String, default: 'command' }, // value | command
    language: { type: String, default: null }, // r | python | bash | stata
});

const html = computed(() => (props.kind === 'command' ? highlight(props.value, props.language) : null));
const copied = ref(false);
async function copy() {
    await navigator.clipboard?.writeText(props.value);
    copied.value = true;
    setTimeout(() => (copied.value = false), 1500);
}
</script>

<template>
    <!-- The Copy button floats over the top-right corner, so the code and its scroll bar use the full width -->
    <div class="group relative min-w-0 rounded-lg" :class="kind === 'value' ? 'bg-slate-800/5 dark:bg-white/10' : 'command bg-[#0c1526]'">
        <pre tabindex="0" class="min-w-0 overflow-x-auto rounded-lg px-3 py-2.5 font-mono focus-visible:outline-2 focus-visible:outline-sky-500" :class="kind === 'value' ? 'pe-24 text-sm break-all whitespace-pre-wrap text-slate-900 dark:text-white' : 'text-xs leading-5 text-slate-100'"><code v-if="html" v-html="html" /><code v-else>{{ value }}</code></pre>
        <button
            type="button"
            class="absolute top-1.5 right-1.5 flex items-center gap-1.5 rounded-md px-2 py-1 text-sm font-medium shadow-sm"
            :class="kind === 'value' ? 'bg-white/90 text-slate-700 hover:bg-white dark:bg-slate-800/90 dark:text-slate-200 dark:hover:bg-slate-700' : 'bg-[#0c1526]/90 text-slate-300 ring-1 ring-white/10 hover:bg-slate-800 hover:text-white'"
            :aria-label="`Copy ${label}`"
            @click="copy"
        >
            <component :is="copied ? CheckIcon : ClipboardDocumentIcon" class="size-4 opacity-70" aria-hidden="true" />{{ copied ? 'Copied' : 'Copy' }}
        </button>
    </div>
</template>

<style scoped>
/* A quiet palette on the dark command surface */
.command :deep(.hljs-keyword),
.command :deep(.hljs-built_in),
.command :deep(.hljs-literal) { color: #7dd3fc; }
.command :deep(.hljs-string) { color: #86efac; }
.command :deep(.hljs-number) { color: #fdba74; }
.command :deep(.hljs-comment) { color: #64748b; font-style: italic; }
.command :deep(.hljs-title),
.command :deep(.hljs-title.function_) { color: #c4b5fd; }
.command :deep(.hljs-operator),
.command :deep(.hljs-punctuation) { color: #94a3b8; }
.command :deep(.hljs-variable),
.command :deep(.hljs-params),
.command :deep(.hljs-attr) { color: #fcd34d; }
</style>
