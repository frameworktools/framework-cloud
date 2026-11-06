<script setup>
// Numbered setup steps. Each step is a list of parts: text, { code } inline, or { cmd } as a command line.
defineProps({ steps: Array });
</script>

<template>
    <ol class="flex flex-col gap-4 text-sm text-slate-700 dark:text-slate-300">
        <li v-for="(parts, index) in steps" :key="index" class="flex gap-3">
            <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-slate-800/5 text-xs font-medium tabular-nums text-slate-600 dark:bg-white/10 dark:text-slate-300">{{ index + 1 }}</span>
            <span class="min-w-0 flex-1 pt-0.5">
                <template v-for="(part, i) in parts" :key="i">
                    <template v-if="typeof part === 'string'">{{ part }}</template>
                    <code v-else-if="part.code" class="font-mono text-xs">{{ part.code }}</code>
                    <code v-else class="mt-2 block overflow-x-auto whitespace-nowrap rounded-lg bg-[#0c1526] px-3 py-2 font-mono text-xs text-slate-100">{{ part.cmd }}</code>
                </template>
            </span>
        </li>
    </ol>
</template>
