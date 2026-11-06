<script setup>
import { ref } from 'vue';
import { CheckIcon, ClipboardDocumentIcon } from '@heroicons/vue/24/outline';

// Numbered setup steps. Each step is a list of parts: text, { code } inline,
// { copy } as a value to paste, or { cmd } as a command line. Both of the last two copy with a button.
defineProps({ steps: Array });

const copied = ref(null);
async function copy(text) {
    await navigator.clipboard?.writeText(text);
    copied.value = text;
    setTimeout(() => (copied.value = null), 1500);
}
</script>

<template>
    <ol class="flex flex-col gap-5 text-sm text-slate-700 dark:text-slate-300">
        <li v-for="(parts, index) in steps" :key="index" class="flex gap-4">
            <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-slate-800/5 text-xs font-medium tabular-nums text-slate-600 dark:bg-white/10 dark:text-slate-300">{{ index + 1 }}</span>
            <span class="min-w-0 flex-1 pt-0.5">
                <template v-for="(part, i) in parts" :key="i">
                    <template v-if="typeof part === 'string'">{{ part }}</template>
                    <code v-else-if="part.code" class="font-mono text-xs">{{ part.code }}</code>
                    <span v-else-if="part.copy" class="my-2.5 flex w-full max-w-xl items-center gap-2 rounded-lg bg-slate-800/5 py-1 ps-3 pe-1 dark:bg-white/10">
                        <code class="min-w-0 flex-1 truncate font-mono text-sm text-slate-900 dark:text-white">{{ part.copy }}</code>
                        <button type="button" class="flex shrink-0 items-center gap-1.5 rounded-md px-2 py-1 text-sm font-medium text-slate-700 hover:bg-slate-800/5 dark:text-slate-200 dark:hover:bg-white/10" @click="copy(part.copy)">
                            <component :is="copied === part.copy ? CheckIcon : ClipboardDocumentIcon" class="size-4 text-slate-500 dark:text-slate-400" aria-hidden="true" />{{ copied === part.copy ? 'Copied' : 'Copy' }}
                        </button>
                    </span>
                    <span v-else class="my-2.5 flex items-center gap-2 rounded-lg bg-[#0c1526] py-1 ps-3 pe-1">
                        <code class="min-w-0 flex-1 overflow-x-auto whitespace-nowrap py-1 font-mono text-xs text-slate-100">{{ part.cmd }}</code>
                        <button type="button" class="flex shrink-0 items-center rounded-md p-1.5 text-slate-400 hover:bg-white/10 hover:text-white" :aria-label="`Copy ${part.cmd}`" @click="copy(part.cmd)">
                            <component :is="copied === part.cmd ? CheckIcon : ClipboardDocumentIcon" class="size-4" aria-hidden="true" />
                        </button>
                    </span>
                </template>
            </span>
        </li>
    </ol>
</template>
