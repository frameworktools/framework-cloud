<script setup>
defineProps({ title: String });
const show = defineModel({ type: Boolean, default: false });
</script>

<template>
    <Teleport to="body">
        <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-950/50" @click="show = false" />
            <div class="relative w-full max-w-lg rounded-xl bg-white p-6 shadow-xl dark:bg-slate-900 dark:ring-1 dark:ring-slate-700">
                <h2 v-if="title" class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ title }}</h2>
                <div class="mt-3 flex flex-col gap-4">
                    <slot />
                </div>
            </div>
        </div>
    </Teleport>
</template>
