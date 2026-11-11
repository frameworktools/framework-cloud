<script setup>
defineProps({ categories: Array, label: String });
const model = defineModel({ default: '' });
</script>

<template>
    <div class="grid min-w-0 gap-5 sm:grid-cols-[9rem_minmax(0,1fr)] sm:gap-6">
        <nav :aria-label="label" class="flex gap-1 overflow-x-auto [scrollbar-width:none] [&::-webkit-scrollbar]:hidden border-b border-slate-800/10 pb-3 sm:flex-col sm:self-start sm:border-0 sm:pb-0 dark:border-white/15">
            <button v-for="category in categories" :key="category.key" type="button" :aria-pressed="model === category.key"
                class="flex shrink-0 items-center justify-between gap-3 rounded-md px-3 py-2 text-left text-sm transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-600"
                :class="model === category.key ? 'bg-sky-50 font-medium text-sky-800 dark:bg-sky-950/60 dark:text-sky-300' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-white/5'"
                @click="model = category.key">
                <span class="min-w-0 whitespace-nowrap sm:whitespace-normal">{{ category.name }}</span><span class="shrink-0 font-mono text-xs opacity-60">{{ category.count }}</span>
            </button>
        </nav>
        <div class="flex min-w-0 flex-col gap-4"><slot /></div>
    </div>
</template>
