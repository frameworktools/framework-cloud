<script setup>
import { watch } from 'vue';
const props = defineProps({ categories: Array, syncUrl: { type: Boolean, default: true } });
const model = defineModel({ default: '' });
watch(model, value => {
    if (!props.syncUrl) return;
    const url = new URL(window.location.href);
    if (value) url.searchParams.set('category', value);
    else url.searchParams.delete('category');
    window.history.replaceState(window.history.state, '', url);
});
</script>

<template>
    <div class="flex flex-wrap gap-1" role="group" aria-label="Skill categories">
        <button v-for="category in categories" :key="category.key" type="button" :aria-pressed="model === category.key"
            class="rounded-full px-2.5 py-1 text-sm font-medium transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-600"
            :class="model === category.key ? 'bg-slate-800 text-white dark:bg-white dark:text-slate-900' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800'"
            @click="model = category.key">
            {{ category.name }} <span class="ml-1 font-mono text-xs opacity-70">{{ category.count }}</span>
        </button>
    </div>
</template>
