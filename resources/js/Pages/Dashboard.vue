<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import UiCallout from '@/Components/UiCallout.vue';
import UiButton from '@/Components/UiButton.vue';
import UiPanel from '@/Components/UiPanel.vue';
import PageHeader from '@/Components/PageHeader.vue';
import GitHubMark from '@/Components/GitHubMark.vue';
import { ArrowRightIcon, CheckIcon, ComputerDesktopIcon, FolderPlusIcon, PlusIcon, UserCircleIcon } from '@heroicons/vue/24/outline';
import { useServerArg } from '@/cloud.js';

const page = usePage();
const serverArg = useServerArg();
const props = defineProps({ counts: Object, steps: Array, setup: Array });

const copy = {
    profile: { title: 'Fill in your profile', text: 'Your name and affiliation go on the notebooks and reports you create.', href: '/settings/profile', icon: UserCircleIcon },
    machine: { title: 'Connect your computer', text: 'Make a token, then run cloud_login() once in R or Python.', href: '/tokens', icon: ComputerDesktopIcon },
    github: { title: 'Connect GitHub', text: 'Create projects as GitHub repositories, ready to clone.', href: '/settings/github', icon: GitHubMark },
    project: { title: 'Create a project', text: 'Pick a blueprint and set it up on GitHub or your computer.', href: '/projects/create', icon: FolderPlusIcon },
};
const firstName = computed(() => page.props.auth.user?.name?.split(' ')[0]);
const remaining = computed(() => props.steps.filter((s) => !s.done).length);
const setupCopy = {
    mail: 'Email isn\'t set up, so sign-in links aren\'t being sent.',
    github: 'GitHub isn\'t set up, so nobody can sign in with GitHub or create projects there.',
};
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout>
        <div class="flex flex-col gap-10">
            <PageHeader :title="`Hello, ${firstName ?? ''}`" description="Your settings and projects, everywhere you work.">
                <template #actions>
                    <Link href="/projects/create"><UiButton><PlusIcon class="size-4" />New project</UiButton></Link>
                </template>
            </PageHeader>

            <UiCallout v-if="setup.length" variant="danger" heading="Finish setting up this server">
                <span v-for="item in setup" :key="item">{{ setupCopy[item] }}</span>
                <Link href="/admin/setup" class="font-medium text-red-700 hover:underline dark:text-red-300">Open server setup</Link>
            </UiCallout>

            <section v-if="remaining" class="flex flex-col gap-4">
                <div class="flex items-baseline justify-between gap-4">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Get started</h2>
                    <span class="text-sm text-slate-500 dark:text-slate-400">{{ steps.length - remaining }} of {{ steps.length }} done</span>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <Link
                        v-for="(step, index) in steps" :key="step.key" :href="copy[step.key].href"
                        class="group flex gap-4 rounded-lg border p-5 transition"
                        :class="step.done
                            ? 'border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-900/40'
                            : 'border-slate-200 bg-white shadow-xs hover:border-sky-300 hover:shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:shadow-none dark:hover:border-sky-700'"
                    >
                        <span
                            class="flex size-10 shrink-0 items-center justify-center rounded-lg"
                            :class="step.done
                                ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-400'
                                : 'bg-sky-50 text-sky-600 dark:bg-sky-400/10 dark:text-sky-400'"
                        >
                            <CheckIcon v-if="step.done" class="size-5" />
                            <component :is="copy[step.key].icon" v-else class="size-5" />
                        </span>
                        <span class="flex min-w-0 flex-1 flex-col gap-1">
                            <span class="text-xs font-medium text-slate-500 dark:text-slate-400">
                                Step {{ index + 1 }}<span v-if="step.done"> · Done</span>
                            </span>
                            <span class="font-semibold" :class="step.done ? 'text-slate-500 line-through dark:text-slate-400' : 'text-slate-900 dark:text-white'">{{ copy[step.key].title }}</span>
                            <span v-if="!step.done" class="text-sm text-slate-600 dark:text-slate-400">{{ copy[step.key].text }}</span>
                        </span>
                        <ArrowRightIcon v-if="!step.done" class="mt-1 size-4 shrink-0 text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-sky-600 dark:text-slate-600 dark:group-hover:text-sky-400" />
                    </Link>
                </div>
            </section>

            <section class="flex flex-col gap-4">
                <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Overview</h2>
                <div class="grid gap-4 sm:grid-cols-3">
                    <Link href="/projects" class="rounded-lg border border-slate-200 bg-white p-5 shadow-xs transition hover:border-sky-300 hover:shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:shadow-none dark:hover:border-sky-700">
                        <div class="text-sm text-slate-500 dark:text-slate-400">Projects</div>
                        <div class="mt-1 text-3xl font-semibold tabular-nums text-slate-900 dark:text-white">{{ counts.projects }}</div>
                    </Link>
                    <Link href="/tokens" class="rounded-lg border border-slate-200 bg-white p-5 shadow-xs transition hover:border-sky-300 hover:shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:shadow-none dark:hover:border-sky-700">
                        <div class="text-sm text-slate-500 dark:text-slate-400">Machine tokens</div>
                        <div class="mt-1 text-3xl font-semibold tabular-nums text-slate-900 dark:text-white">{{ counts.tokens }}</div>
                    </Link>
                    <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-xs dark:border-slate-800 dark:bg-slate-900 dark:shadow-none">
                        <div class="text-sm text-slate-500 dark:text-slate-400">Settings revision</div>
                        <div class="mt-1 text-3xl font-semibold tabular-nums text-slate-900 dark:text-white">{{ counts.settings_revision }}</div>
                    </div>
                </div>
            </section>

            <UiPanel title="Connect from R or Python" description="Connect each computer once with a token from API tokens.">
                <div class="grid gap-4 lg:grid-cols-2">
                    <div class="overflow-x-auto rounded-lg bg-slate-900 p-5 font-mono text-sm text-slate-100 dark:bg-slate-950 dark:ring-1 dark:ring-slate-700">
                        <div class="text-slate-500"># R</div>
                        <div>framework::cloud_login(<span class="text-sky-400">"fw_..."</span><span v-if="serverArg">, url = <span class="text-sky-400">"{{ page.props.appUrl }}"</span></span>)</div>
                        <div>framework::new(<span class="text-sky-400">"my-study"</span>)</div>
                    </div>
                    <div class="overflow-x-auto rounded-lg bg-slate-900 p-5 font-mono text-sm text-slate-100 dark:bg-slate-950 dark:ring-1 dark:ring-slate-700">
                        <div class="text-slate-500"># Python</div>
                        <div>import framework</div>
                        <div>framework.cloud_login(<span class="text-sky-400">"fw_..."</span><span v-if="serverArg">, url=<span class="text-sky-400">"{{ page.props.appUrl }}"</span></span>)</div>
                        <div>framework.new(<span class="text-sky-400">"my-study"</span>)</div>
                    </div>
                </div>
            </UiPanel>
        </div>
    </AppLayout>
</template>
