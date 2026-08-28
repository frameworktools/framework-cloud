<script setup>
import { usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({ heading: String, subheading: String });

const page = usePage();
const tabs = [
    { label: 'Profile', href: '/settings/profile' },
    { label: 'Framework defaults', href: '/settings/defaults' },
    { label: 'Passkeys', href: '/settings/passkeys' },
];
const current = (href) => page.url.startsWith(href);
</script>

<template>
    <AppLayout>
        <div class="flex items-start gap-10 max-md:flex-col">
            <nav class="flex w-full flex-col gap-0.5 md:w-48">
                <Link
                    v-for="tab in tabs" :key="tab.href" :href="tab.href"
                    class="rounded-md px-2 py-1.5 text-sm"
                    :class="current(tab.href)
                        ? 'bg-neutral-100 font-medium text-neutral-900 dark:bg-neutral-800 dark:text-neutral-100'
                        : 'text-neutral-600 hover:bg-neutral-50 dark:text-neutral-400 dark:hover:bg-neutral-800/50'"
                >
                    {{ tab.label }}
                </Link>
            </nav>

            <div class="w-full max-w-lg flex-1">
                <h1 class="text-lg font-semibold">{{ heading }}</h1>
                <p v-if="subheading" class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">{{ subheading }}</p>
                <div class="mt-6 flex flex-col gap-6">
                    <slot />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
