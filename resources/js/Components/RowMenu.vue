<script setup>
// The "…" menu at the end of a row or card: edit, share, status, delete.
// Items: { label, onSelect?, href?, external?, danger?, icon?, current? } or { divider: true }.
// It floats in a layer of its own, so tables and cards that clip overflow don't cut it off.
import { nextTick, onBeforeUnmount, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { EllipsisHorizontalIcon } from '@heroicons/vue/20/solid';

const props = defineProps({ items: { type: Array, required: true }, label: { type: String, default: 'Actions' } });
const open = ref(false);
const button = ref(null);
const menu = ref(null);
const position = ref({ top: 0, left: 0 });

// Below the button, or above it when there's no room below; it follows the button as the page scrolls
const place = () => {
    if (!button.value) return;
    const rect = button.value.getBoundingClientRect();
    const width = 224;
    const height = menu.value?.offsetHeight ?? 0;
    const below = rect.bottom + 4 + height <= window.innerHeight - 8;
    position.value = {
        top: below || !height ? rect.bottom + 4 : Math.max(8, rect.top - height - 4),
        left: Math.max(8, Math.min(rect.right - width, window.innerWidth - width - 8)),
    };
};
const close = () => { open.value = false; removeListeners(); };
const onOutside = (event) => {
    if (!menu.value?.contains(event.target) && !button.value?.contains(event.target)) close();
};
const onKey = (event) => {
    if (event.key === 'Escape') { close(); button.value?.focus(); }
    if (['ArrowDown', 'ArrowUp'].includes(event.key)) {
        event.preventDefault();
        const entries = [...(menu.value?.querySelectorAll('[role=menuitem]') ?? [])];
        const at = entries.indexOf(document.activeElement);
        entries[(at + (event.key === 'ArrowDown' ? 1 : entries.length - 1)) % entries.length]?.focus({ preventScroll: true });
    }
};
const removeListeners = () => {
    document.removeEventListener('mousedown', onOutside);
    document.removeEventListener('keydown', onKey);
    window.removeEventListener('scroll', place, true);
    window.removeEventListener('resize', close);
};
const toggle = async () => {
    if (open.value) return close();
    place();
    open.value = true;
    document.addEventListener('mousedown', onOutside);
    document.addEventListener('keydown', onKey);
    window.addEventListener('scroll', place, true);
    window.addEventListener('resize', close);
    await nextTick();
    // Now that it has a height, flip it above the button if it won't fit below
    place();
    // preventScroll: focusing mustn't scroll the page out from under the menu
    menu.value?.querySelector('[role=menuitem]')?.focus({ preventScroll: true });
};
const choose = (item) => {
    close();
    if (item.onSelect) item.onSelect();
    else if (item.href && item.external) window.open(item.href, '_blank', 'noopener');
    else if (item.href) router.visit(item.href);
};
onBeforeUnmount(removeListeners);
</script>

<template>
    <button ref="button" type="button" :aria-label="label" :title="label" aria-haspopup="menu" :aria-expanded="String(open)"
        class="inline-flex size-8 items-center justify-center rounded-md text-slate-500 hover:bg-slate-800/5 hover:text-slate-800 focus-visible:outline-2 focus-visible:outline-sky-600 dark:text-slate-400 dark:hover:bg-white/10 dark:hover:text-white"
        @click.stop.prevent="toggle">
        <EllipsisHorizontalIcon class="size-5" aria-hidden="true" />
    </button>
    <Teleport to="body">
        <div v-if="open" ref="menu" role="menu" :aria-label="label" class="fixed z-50 w-56 rounded-lg border border-slate-200 bg-white p-1 text-sm shadow-lg dark:border-white/10 dark:bg-slate-900"
            :style="{ top: `${position.top}px`, left: `${position.left}px` }">
            <template v-for="(item, index) in items" :key="index">
                <div v-if="item.divider" class="my-1 h-px bg-slate-800/10 dark:bg-white/10" role="separator" />
                <button v-else type="button" role="menuitem"
                    class="flex w-full items-center gap-2 rounded-md px-2.5 py-1.5 text-left focus:outline-none"
                    :class="item.danger
                        ? 'text-red-700 hover:bg-red-50 focus:bg-red-50 dark:text-red-400 dark:hover:bg-red-950/40 dark:focus:bg-red-950/40'
                        : 'text-slate-700 hover:bg-slate-800/5 focus:bg-slate-800/5 dark:text-slate-200 dark:hover:bg-white/10 dark:focus:bg-white/10'"
                    @click="choose(item)">
                    <component :is="item.icon" v-if="item.icon" class="size-4 shrink-0 opacity-70" aria-hidden="true" />
                    <span class="flex-1">{{ item.label }}</span>
                    <span v-if="item.current" class="text-xs text-slate-400">Current</span>
                </button>
            </template>
        </div>
    </Teleport>
</template>
