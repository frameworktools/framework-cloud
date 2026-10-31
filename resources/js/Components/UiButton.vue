<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    variant: { type: String, default: 'primary' }, // primary | outline | danger | ghost
    size: { type: String, default: 'md' }, // sm | md
    type: { type: String, default: 'button' },
    disabled: Boolean,
    // ⌘S (Ctrl+S off a Mac) presses this button. Give it to one button per page.
    saveShortcut: Boolean,
});

const variants = {
    primary: 'bg-sky-600 text-white hover:bg-sky-700 disabled:bg-sky-300 dark:disabled:bg-sky-900 dark:disabled:text-sky-300/60',
    outline: 'border border-slate-300 text-slate-800 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-100 dark:hover:bg-slate-800',
    danger: 'bg-red-600 text-white hover:bg-red-700 disabled:bg-red-300',
    ghost: 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800',
};

const sizes = {
    sm: 'px-2.5 py-1.5 text-xs',
    md: 'px-4 py-2 text-sm',
};

const isMac = typeof navigator !== 'undefined' && /Mac|iPhone|iPad/.test(navigator.platform || navigator.userAgent);
const hint = isMac ? '⌘S' : 'Ctrl+S';
const button = ref(null);

function onKeydown(event) {
    if (event.key.toLowerCase() !== 's' || !(isMac ? event.metaKey : event.ctrlKey) || event.altKey || event.shiftKey) return;
    // Never the browser's "Save page" dialog, even while a save is running
    event.preventDefault();
    // Buttons hidden on this screen size (the sidebar copy on mobile) stay out of it
    if (!props.disabled && button.value?.offsetParent !== null) button.value.click();
}

onMounted(() => props.saveShortcut && window.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));
</script>

<template>
    <button
        ref="button"
        :type="type"
        :disabled="disabled"
        :aria-keyshortcuts="saveShortcut ? (isMac ? 'Meta+S' : 'Control+S') : undefined"
        :title="saveShortcut ? `Save (${hint})` : undefined"
        class="inline-flex items-center justify-center gap-2 rounded-md font-medium transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-500 focus-visible:ring-offset-2 disabled:cursor-not-allowed dark:focus-visible:ring-offset-slate-950"
        :class="[variants[variant], sizes[size]]"
    >
        <slot />
        <kbd
            v-if="saveShortcut"
            class="rounded px-1.5 py-0.5 font-sans text-xs font-medium opacity-70 max-sm:hidden"
            :class="variant === 'primary' || variant === 'danger' ? 'bg-white/20' : 'bg-slate-100 dark:bg-slate-800'"
        >{{ hint }}</kbd>
    </button>
</template>
