<script setup>
import { usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const page = usePage();
defineProps({ counts: Object });
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout>
        <div class="flex flex-col gap-8">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    Hello, {{ page.props.auth.user?.name?.split(' ')[0] }}
                </h1>
                <p class="mt-1 text-neutral-600 dark:text-neutral-400">
                    Your settings and projects, everywhere you work.
                </p>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <Link href="/projects" class="rounded-lg border border-neutral-200 p-4 hover:border-orange-300 dark:border-neutral-700 dark:hover:border-orange-700">
                    <div class="text-2xl font-semibold tabular-nums">{{ counts.projects }}</div>
                    <div class="text-sm text-neutral-500 dark:text-neutral-400">Projects</div>
                </Link>
                <Link href="/tokens" class="rounded-lg border border-neutral-200 p-4 hover:border-orange-300 dark:border-neutral-700 dark:hover:border-orange-700">
                    <div class="text-2xl font-semibold tabular-nums">{{ counts.tokens }}</div>
                    <div class="text-sm text-neutral-500 dark:text-neutral-400">Machine tokens</div>
                </Link>
                <div class="rounded-lg border border-neutral-200 p-4 dark:border-neutral-700">
                    <div class="text-2xl font-semibold tabular-nums">{{ counts.settings_revision }}</div>
                    <div class="text-sm text-neutral-500 dark:text-neutral-400">Settings revision</div>
                </div>
            </div>

            <div class="rounded-lg bg-neutral-900 p-5 font-mono text-sm text-neutral-100 dark:ring-1 dark:ring-neutral-700">
                <div class="text-neutral-500"># connect a machine (token from API tokens)</div>
                <div>framework::cloud_login(<span class="text-orange-400">"fw_..."</span>)</div>
                <div class="mt-2 text-neutral-500"># then</div>
                <div>framework::new(<span class="text-orange-400">"my-study"</span>)</div>
            </div>
        </div>
    </AppLayout>
</template>
