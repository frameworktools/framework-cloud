<script setup>
import ThemeToggle from '@/Components/ThemeToggle.vue';

defineProps({ title: String, description: String });
</script>

<template>
    <div class="relative flex min-h-screen flex-col items-center justify-center px-6 py-12">
        <ThemeToggle class="absolute top-4 right-4" />

        <div class="w-full max-w-sm">
            <a href="/" class="mb-8 block text-center font-semibold tracking-tight">
                <span class="inline-flex items-center gap-2"><img :src="'/images/logo.png'" alt="" class="h-8 w-auto" /><span>Framework <span class="text-sky-600 dark:text-sky-500">Cloud</span></span></span>
            </a>

            <h1 class="text-center text-xl font-semibold tracking-tight">{{ title }}</h1>
            <p v-if="description" class="mt-2 text-center text-sm text-slate-500 dark:text-slate-400">
                {{ description }}
            </p>

            <div class="mt-8 flex flex-col gap-6">
                <slot />
            </div>
        </div>
    </div>
</template>
