<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import UiCallout from '@/Components/UiCallout.vue';
import { useServerArg } from '@/cloud.js';

const page = usePage();
const serverArg = useServerArg();
const props = defineProps({ counts: Object, steps: Array, setup: Array });

const copy = {
    profile: { title: 'Fill in your profile', text: 'Your name and affiliation go on the notebooks and reports you create.', href: '/settings/profile' },
    machine: { title: 'Connect your computer', text: 'Make a token, then run framework::cloud_login() in R once.', href: '/tokens' },
    github: { title: 'Connect GitHub', text: 'Create projects as GitHub repositories, ready to clone.', href: '/settings/github' },
    project: { title: 'Create a project', text: 'Pick a blueprint and set it up on GitHub or your computer.', href: '/projects/create' },
};
const remaining = computed(() => props.steps.filter((s) => !s.done).length);
const setupCopy = {
    mail: 'Email isn\'t set up, so sign-in links aren\'t being sent.',
    github: 'GitHub isn\'t set up, so nobody can sign in with GitHub or create projects there.',
};
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

            <UiCallout v-if="setup.length" variant="danger" heading="Finish setting up this server">
                <span v-for="item in setup" :key="item">{{ setupCopy[item] }}</span>
                <Link href="/admin/setup" class="font-medium text-red-700 hover:underline dark:text-red-300">Open server setup</Link>
            </UiCallout>

            <section v-if="remaining" class="flex flex-col gap-3">
                <div class="flex items-baseline justify-between">
                    <h2 class="text-lg font-semibold">Get started</h2>
                    <span class="text-sm text-neutral-500">{{ steps.length - remaining }} of {{ steps.length }} done</span>
                </div>
                <div class="flex flex-col divide-y divide-neutral-200 rounded-lg border border-neutral-200 dark:divide-neutral-800 dark:border-neutral-800">
                    <Link
                        v-for="step in steps" :key="step.key" :href="copy[step.key].href"
                        class="flex items-center gap-3 p-4 hover:bg-neutral-50 dark:hover:bg-neutral-900"
                    >
                        <span
                            class="flex size-5 shrink-0 items-center justify-center rounded-full border text-xs"
                            :class="step.done ? 'border-emerald-500 bg-emerald-500 text-white' : 'border-neutral-300 dark:border-neutral-600'"
                        ><svg v-if="step.done" class="size-3" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-8 8a1 1 0 0 1-1.4 0l-4-4a1 1 0 1 1 1.4-1.4L8 12.6l7.3-7.3a1 1 0 0 1 1.4 0Z" clip-rule="evenodd" /></svg></span>
                        <span class="flex min-w-0 flex-col">
                            <span class="font-medium" :class="step.done ? 'text-neutral-500 line-through' : ''">{{ copy[step.key].title }}</span>
                            <span v-if="!step.done" class="text-sm text-neutral-600 dark:text-neutral-400">{{ copy[step.key].text }}</span>
                        </span>
                    </Link>
                </div>
            </section>

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
                <div>framework::cloud_login(<span class="text-orange-400">"fw_..."</span><span v-if="serverArg">, url = <span class="text-orange-400">"{{ page.props.appUrl }}"</span></span>)</div>
                <div class="mt-2 text-neutral-500"># then</div>
                <div>framework::new(<span class="text-orange-400">"my-study"</span>)</div>
            </div>
        </div>
    </AppLayout>
</template>
