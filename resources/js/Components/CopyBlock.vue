<script setup>
import { ref } from 'vue';
import { CheckIcon, ClipboardDocumentIcon } from '@heroicons/vue/24/outline';

// A value or command to paste, with a Copy button. "value" sits on a light
// tint (tokens, keys); "command" is code on the dark command surface and may
// run over several lines.
const props = defineProps({
    value: { type: String, required: true },
    label: { type: String, required: true }, // names the copy button for screen readers
    kind: { type: String, default: 'command' }, // value | command
});

const copied = ref(false);
async function copy() {
    await navigator.clipboard?.writeText(props.value);
    copied.value = true;
    setTimeout(() => (copied.value = false), 1500);
}
</script>

<template>
    <div class="flex min-w-0 items-start gap-2 rounded-lg p-1 ps-3" :class="kind === 'value' ? 'bg-slate-800/5 dark:bg-white/10' : 'bg-[#0c1526]'">
        <pre tabindex="0" class="min-w-0 flex-1 overflow-x-auto rounded py-1.5 font-mono focus-visible:outline-2 focus-visible:outline-sky-500" :class="kind === 'value' ? 'text-sm break-all whitespace-pre-wrap text-slate-900 dark:text-white' : 'text-xs leading-5 text-slate-100'"><code>{{ value }}</code></pre>
        <button
            type="button"
            class="flex shrink-0 items-center gap-1.5 rounded-md px-2 py-1 text-sm font-medium"
            :class="kind === 'value' ? 'text-slate-700 hover:bg-slate-800/5 dark:text-slate-200 dark:hover:bg-white/10' : 'text-slate-300 hover:bg-white/10 hover:text-white'"
            :aria-label="`Copy ${label}`"
            @click="copy"
        >
            <component :is="copied ? CheckIcon : ClipboardDocumentIcon" class="size-4 opacity-70" aria-hidden="true" />{{ copied ? 'Copied' : 'Copy' }}
        </button>
    </div>
</template>
