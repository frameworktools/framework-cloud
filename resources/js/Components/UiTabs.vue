<script setup>
import { nextTick, ref } from 'vue';
import { Link } from '@inertiajs/vue3';

// Underlined tabs, as academic.page draws them. Each tab is a link (href) or switches v-model (value).
// Arrow keys move between tabs that switch in place.
const props = defineProps({ tabs: { type: Array, required: true }, label: { type: String, default: 'Sections' } });
const model = defineModel({ type: [String, Number], default: null });
const list = ref(null);
const selected = tab => tab.current ?? (tab.value !== undefined && tab.value === model.value);
function step(event, index) {
    const buttons = props.tabs.map((tab, i) => ({ tab, i })).filter(({ tab }) => tab.href === undefined);
    const at = buttons.findIndex(({ i }) => i === index);
    const next = buttons[(at + (event.key === 'ArrowRight' ? 1 : buttons.length - 1)) % buttons.length];
    model.value = next.tab.value;
    nextTick(() => list.value?.querySelectorAll('[role=tab]')[next.i]?.focus());
}
const tabClass = 'relative -mb-px flex shrink-0 items-center gap-2 whitespace-nowrap border-b-2 px-1 text-sm font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-sky-600';
const stateClass = tab => selected(tab)
    ? 'border-(--color-accent-content) text-(--color-accent-content)'
    : 'border-transparent text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white';
</script>

<template>
    <div class="relative">
        <div ref="list" :role="tabs.some(tab => tab.href === undefined) ? 'tablist' : undefined" :aria-label="label"
            class="flex h-10 gap-6 overflow-x-auto overflow-y-hidden border-b border-slate-800/10 dark:border-white/15">
            <template v-for="(tab, index) in tabs" :key="tab.value ?? tab.href">
                <Link v-if="tab.href !== undefined" :href="tab.href" :class="[tabClass, stateClass(tab)]" :aria-current="selected(tab) ? 'page' : undefined" preserve-scroll>
                    {{ tab.label }}<span v-if="tab.count !== undefined" class="rounded-md bg-slate-800/5 px-1.5 text-xs tabular-nums text-slate-500 dark:bg-white/10 dark:text-slate-400">{{ tab.count }}</span>
                </Link>
                <button v-else type="button" role="tab" :aria-selected="selected(tab) ? 'true' : 'false'" :tabindex="selected(tab) ? 0 : -1" :class="[tabClass, stateClass(tab)]"
                    @click="model = tab.value" @keydown.right.prevent="step($event, index)" @keydown.left.prevent="step($event, index)">
                    {{ tab.label }}<span v-if="tab.count !== undefined" class="rounded-md bg-slate-800/5 px-1.5 text-xs tabular-nums text-slate-500 dark:bg-white/10 dark:text-slate-400">{{ tab.count }}</span>
                </button>
            </template>
        </div>
    </div>
</template>
