<script setup>
import { ChevronDownIcon } from '@heroicons/vue/24/outline';
import { useCollapsed } from '@/collapsed.js';

// A page section whose heading folds it. Whether it's folded is remembered on
// the account, under the remember key.
const props = defineProps({
    title: { type: String, required: true },
    remember: { type: String, required: true },
});
const { collapsed, toggle } = useCollapsed(props.remember);
</script>

<template>
    <section :aria-labelledby="`${remember}-heading`" class="min-w-0">
        <div class="flex items-center justify-between gap-3">
            <h2 :id="`${remember}-heading`" class="text-base font-medium text-slate-900 dark:text-white">
                <button type="button" class="flex items-center gap-1.5 rounded hover:text-slate-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-600 dark:hover:text-slate-200" :aria-expanded="!collapsed" :aria-controls="remember" @click="toggle">
                    {{ title }}
                    <ChevronDownIcon class="size-4 text-slate-400 transition-transform" :class="collapsed ? '-rotate-90' : ''" aria-hidden="true" />
                </button>
            </h2>
            <div v-if="$slots.actions && !collapsed" class="flex items-center gap-2"><slot name="actions" /></div>
        </div>
        <div v-show="!collapsed" :id="remember" class="mt-3"><slot /></div>
    </section>
</template>
