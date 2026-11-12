<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Link } from '@inertiajs/vue3';

// Buttons after Flux: one filled accent button per view, ghost for secondary actions,
// subtle for quiet ones and outline when a border is explicitly needed. Give href to render a link that looks like a button.
const props = defineProps({
    variant: { type: String, default: 'primary' }, // primary | outline | filled | ghost | subtle | danger
    size: { type: String, default: 'md' }, // xs | sm | md
    type: { type: String, default: 'button' },
    href: String,
    external: Boolean, // a plain link, opened in a new tab
    disabled: Boolean,
    square: Boolean, // an icon-only button
    // ⌘S (Ctrl+S off a Mac) presses this button. Give it to one button per page.
    saveShortcut: Boolean,
});

const variants = {
    primary: 'border border-black/10 bg-(--color-accent) text-(--color-accent-foreground) hover:bg-(--color-accent-hover) dark:border-0',
    outline: 'border border-slate-200 border-b-slate-300/80 bg-white text-slate-800 shadow-xs hover:bg-slate-50 dark:border-white/10 dark:bg-white/10 dark:text-white dark:hover:bg-white/15',
    filled: 'bg-slate-800/5 text-slate-800 hover:bg-slate-800/10 dark:bg-white/10 dark:text-white dark:hover:bg-white/20',
    // A gray tint in light and a light blue one in dark, so it reads as a button on any surface, cards included
    ghost: 'bg-slate-800/5 text-slate-800 hover:bg-slate-800/10 dark:bg-sky-400/10 dark:text-sky-200 dark:ring-1 dark:ring-sky-300/15 dark:ring-inset dark:hover:bg-sky-400/20',
    subtle: 'text-slate-500 hover:bg-slate-800/5 hover:text-slate-800 dark:text-slate-400 dark:hover:bg-white/15 dark:hover:text-white',
    danger: 'bg-red-600 text-white shadow-[inset_0_1px_--theme(--color-white/.15)] hover:bg-red-700 dark:bg-red-600 dark:hover:bg-red-500',
};
const sizes = {
    md: 'h-10 rounded-lg text-sm',
    sm: 'h-8 rounded-md text-sm',
    xs: 'h-6 rounded-md text-xs',
};
const padding = { md: 'px-4', sm: 'px-3', xs: 'px-2' };
const squares = { md: 'w-10', sm: 'w-8', xs: 'w-6' };

const isMac = typeof navigator !== 'undefined' && /Mac|iPhone|iPad/.test(navigator.platform || navigator.userAgent);
// The hint sits just after the label, and the pair is centered together
const hint = isMac ? '⌘S' : 'Ctrl+S';
const button = ref(null);
const tag = computed(() => props.href ? (props.external ? 'a' : Link) : 'button');
const element = () => button.value?.$el ?? button.value;

function onKeydown(event) {
    if (event.key.toLowerCase() !== 's' || !(isMac ? event.metaKey : event.ctrlKey) || event.altKey || event.shiftKey) return;
    // Never the browser's "Save page" dialog, even while a save is running
    event.preventDefault();
    // Buttons hidden on this screen size (the sidebar copy on mobile) stay out of it
    if (!props.disabled && element()?.offsetParent !== null) element().click();
}

onMounted(() => props.saveShortcut && window.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));
</script>

<template>
    <component
        :is="tag"
        ref="button"
        :type="href ? undefined : type"
        :href="href"
        :target="external ? '_blank' : undefined"
        :rel="external ? 'noopener' : undefined"
        :disabled="href ? undefined : disabled"
        :aria-disabled="href && disabled ? 'true' : undefined"
        :aria-keyshortcuts="saveShortcut ? (isMac ? 'Meta+S' : 'Control+S') : undefined"
        :title="saveShortcut ? `Save (${hint})` : undefined"
        class="relative inline-flex shrink-0 items-center justify-center gap-2 whitespace-nowrap font-medium transition-colors focus:outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-600 disabled:pointer-events-none disabled:cursor-default disabled:opacity-50 aria-disabled:pointer-events-none aria-disabled:opacity-50 dark:focus-visible:outline-sky-500 [&_svg]:size-4 [&_svg]:shrink-0"
        :class="[variants[variant], sizes[size], square ? squares[size] : padding[size]]"
    >
        <slot />
        <kbd
            v-if="saveShortcut"
            class="ml-1 font-sans text-[11px] leading-4 font-normal max-sm:hidden"
            :class="variant === 'primary' ? 'text-sky-100' : variant === 'danger' ? 'text-white/80' : 'text-slate-500 dark:text-slate-400'"
        >{{ hint }}</kbd>
    </component>
</template>
