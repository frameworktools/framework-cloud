<script setup>
defineProps({ title: String, description: String });
</script>

<template>
    <div class="flex min-h-screen flex-col items-center justify-center px-6 py-12">
        <div class="w-full max-w-sm">
            <a href="/" class="mb-8 block text-center font-semibold tracking-tight">
                framework<span class="text-orange-500">.pub</span>
            </a>

            <h1 class="text-center text-xl font-semibold tracking-tight">{{ title }}</h1>
            <p v-if="description" class="mt-2 text-center text-sm text-neutral-500 dark:text-neutral-400">
                {{ description }}
            </p>

            <div class="mt-8 flex flex-col gap-6">
                <slot />
            </div>
        </div>
    </div>
</template>
