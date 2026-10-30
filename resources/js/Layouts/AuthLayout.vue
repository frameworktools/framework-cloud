<script setup>
import ThemeToggle from '@/Components/ThemeToggle.vue';

defineProps({ title: String, description: String });

const features = [
    {
        heading: 'Blueprints',
        body: 'Start each project from a structure you choose: folders, R packages, Quarto templates, and instructions for AI assistants. Build your own, or copy and change someone else\'s.',
    },
    {
        heading: 'GitHub',
        body: 'Create a project as a GitHub repository, then clone it on any computer with everything in place.',
    },
    {
        heading: 'Publishing',
        body: 'Publish rendered Quarto notebooks to a link you can share with collaborators.',
    },
    {
        heading: 'Your settings',
        body: 'Your author details and project defaults follow you to every computer you work on.',
    },
];
</script>

<template>
    <div class="flex min-h-screen">
        <aside class="hidden w-[30rem] shrink-0 flex-col justify-between border-r border-neutral-200 bg-neutral-50 p-10 lg:flex xl:w-[34rem] dark:border-neutral-800 dark:bg-neutral-900">
            <div>
                <a href="/" class="font-semibold tracking-tight">
                    Framework <span class="text-orange-500">Cloud</span>
                </a>

                <h2 class="mt-16 text-2xl font-semibold tracking-tight text-balance">
                    Start, share, and publish research projects in R.
                </h2>
                <p class="mt-3 text-neutral-600 dark:text-neutral-400">
                    Framework Cloud works with the Framework R package to set up
                    projects the same way every time.
                </p>

                <dl class="mt-10 flex flex-col gap-6">
                    <div v-for="feature in features" :key="feature.heading" class="border-l-2 border-orange-500 pl-4">
                        <dt class="text-sm font-semibold">{{ feature.heading }}</dt>
                        <dd class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">{{ feature.body }}</dd>
                    </div>
                </dl>
            </div>

            <p class="mt-10 text-sm text-neutral-500 dark:text-neutral-400">
                Your data stays on your computer. Framework Cloud stores project
                settings and templates, plus any notebooks you choose to publish.
            </p>
        </aside>

        <div class="relative flex flex-1 flex-col items-center justify-center px-6 py-12">
            <ThemeToggle class="absolute top-4 right-4" />

            <div class="w-full max-w-sm">
                <div class="mb-8 text-center lg:hidden">
                    <a href="/" class="font-semibold tracking-tight">
                        Framework <span class="text-orange-500">Cloud</span>
                    </a>
                    <p class="mt-2 text-sm text-neutral-500 dark:text-neutral-400">
                        Start, share, and publish research projects in R.
                    </p>
                </div>

                <h1 class="text-center text-xl font-semibold tracking-tight">{{ title }}</h1>
                <p v-if="description" class="mt-2 text-center text-sm text-neutral-500 dark:text-neutral-400">
                    {{ description }}
                </p>

                <div class="mt-8 flex flex-col gap-6">
                    <slot />
                </div>
            </div>
        </div>
    </div>
</template>
