<script setup>
import { Link } from '@inertiajs/vue3';

// An inline text link: underlined quietly, not painted blue. "accent" for the rare link that
// should stand out; "subtle" for grey links in fine print. Actions use UiButton instead.
defineProps({ href: String, external: Boolean, variant: { type: String, default: 'default' } });
const variants = {
    default: 'text-slate-800 decoration-slate-800/25 hover:decoration-current dark:text-white dark:decoration-white/30',
    accent: 'text-(--color-accent-content) decoration-sky-700/30 hover:decoration-current dark:decoration-sky-400/40',
    subtle: 'text-slate-500 decoration-slate-500/30 hover:text-slate-800 hover:decoration-current dark:text-slate-400 dark:hover:text-white',
};
</script>

<template>
    <a v-if="external" :href="href" target="_blank" rel="noopener" class="font-medium underline underline-offset-4" :class="variants[variant]"><slot /></a>
    <Link v-else :href="href" class="font-medium underline underline-offset-4" :class="variants[variant]"><slot /></Link>
</template>
