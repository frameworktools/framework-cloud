<script setup>
import { nextTick, onBeforeUnmount, ref, useId, watch } from 'vue';

// A dialog: Escape or the backdrop closes it, and focus moves into it when it opens
defineProps({ title: String });
const show = defineModel({ type: Boolean, default: false });
const id = useId();
const panel = ref(null);

const onKey = (event) => {
    if (event.key === 'Escape') show.value = false;
};
watch(show, async (open) => {
    if (open) {
        window.addEventListener('keydown', onKey);
        await nextTick();
        panel.value?.querySelector('button, [href], input, select, textarea')?.focus();
    } else {
        window.removeEventListener('keydown', onKey);
    }
});
onBeforeUnmount(() => window.removeEventListener('keydown', onKey));
</script>

<template>
    <Teleport to="body">
        <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-950/50" @click="show = false" />
            <div ref="panel" role="dialog" aria-modal="true" :aria-labelledby="title ? `${id}-title` : undefined" class="relative w-full max-w-lg rounded-xl bg-white p-6 shadow-xl dark:bg-slate-900 dark:ring-1 dark:ring-slate-700">
                <h2 v-if="title" :id="`${id}-title`" class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ title }}</h2>
                <div class="mt-3 flex flex-col gap-4">
                    <slot />
                </div>
            </div>
        </div>
    </Teleport>
</template>
