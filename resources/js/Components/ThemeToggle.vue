<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';

// Light / system / dark switch. The choice is applied by the inline script
// in app.blade.php (window.fwTheme), which also handles first paint.
const choice = ref(window.fwTheme?.get() ?? 'system');
const sync = (event) => (choice.value = event.detail);
onMounted(() => window.addEventListener('fw-theme', sync));
onBeforeUnmount(() => window.removeEventListener('fw-theme', sync));

const pick = (value) => window.fwTheme?.set(value);

const options = [
    { value: 'light', label: 'Light' },
    { value: 'system', label: 'System' },
    { value: 'dark', label: 'Dark' },
];
</script>

<template>
    <div class="inline-flex items-center gap-0.5 rounded-full border border-neutral-200 p-0.5 dark:border-neutral-700" role="radiogroup" aria-label="Color theme">
        <button
            v-for="option in options" :key="option.value"
            type="button" role="radio" :aria-checked="choice === option.value"
            :title="option.label" :aria-label="option.label"
            class="flex size-6 items-center justify-center rounded-full"
            :class="choice === option.value
                ? 'bg-neutral-200 text-neutral-900 dark:bg-neutral-700 dark:text-neutral-100'
                : 'text-neutral-500 hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-neutral-100'"
            @click="pick(option.value)"
        >
            <svg v-if="option.value === 'light'" class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="4" /><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4" /></svg>
            <svg v-else-if="option.value === 'system'" class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="12" rx="2" /><path d="M8 20h8M12 16v4" /></svg>
            <svg v-else class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 14.5A8 8 0 1 1 9.5 4a6.5 6.5 0 0 0 10.5 10.5Z" /></svg>
        </button>
    </div>
</template>
