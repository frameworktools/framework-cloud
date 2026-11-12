<script setup>
import { onBeforeUnmount, ref, useId, watch } from 'vue';
import { InformationCircleIcon } from '@heroicons/vue/24/outline';

// An (i) button that opens a short explanation beside it on hover, or on a
// click or tap. Closes when the pointer leaves, on a click outside, Escape, or
// the button again. A short delay lets the pointer reach links inside it.
const props = defineProps({ label: { type: String, required: true } }); // what it explains, for screen readers

const open = ref(false);
const root = ref(null);
const id = useId();
const outside = (event) => { if (!root.value?.contains(event.target)) open.value = false; };
const escape = (event) => { if (event.key === 'Escape') open.value = false; };
let timer = null;
const hover = (value) => {
    clearTimeout(timer);
    timer = setTimeout(() => { open.value = value; }, value ? 120 : 200);
};
watch(open, (value) => {
    document[value ? 'addEventListener' : 'removeEventListener']('pointerdown', outside);
    document[value ? 'addEventListener' : 'removeEventListener']('keydown', escape);
});
const toggle = () => { clearTimeout(timer); open.value = !open.value; };
onBeforeUnmount(() => { clearTimeout(timer); document.removeEventListener('pointerdown', outside); document.removeEventListener('keydown', escape); });
</script>

<template>
    <span ref="root" class="relative inline-flex" @pointerenter="$event.pointerType === 'mouse' && hover(true)" @pointerleave="$event.pointerType === 'mouse' && hover(false)">
        <button
            type="button"
            class="rounded-full text-slate-400 hover:text-slate-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-600 dark:text-slate-500 dark:hover:text-slate-200"
            :aria-label="`About ${props.label}`" :aria-expanded="open" :aria-controls="id"
            @click="toggle"
        >
            <InformationCircleIcon class="size-[18px]" stroke-width="1.75" aria-hidden="true" />
        </button>
        <span
            v-if="open" :id="id" role="dialog" :aria-label="`About ${props.label}`"
            class="absolute top-6 left-0 z-20 w-72 rounded-lg border border-slate-800/10 bg-white p-3 text-xs leading-relaxed font-normal text-slate-600 shadow-lg dark:border-white/15 dark:bg-slate-900 dark:text-slate-300"
        ><slot /></span>
    </span>
</template>
