<script setup>
import { computed } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import ThemeToggle from '@/Components/ThemeToggle.vue';

const page = usePage();
const user = computed(() => page.props.auth.user);
const nav = computed(() => page.props.nav ?? []);
const mainNav = computed(() => nav.value.filter((i) => !i.section));
const adminNav = computed(() => nav.value.filter((i) => i.section === 'Admin'));

const current = (href) => page.url === href || page.url.startsWith(href + '/');
const logout = () => router.post('/logout');
</script>

<template>
    <div class="flex min-h-screen">
        <aside class="hidden w-60 shrink-0 flex-col border-r border-slate-200 bg-slate-50 p-4 md:flex dark:border-slate-800 dark:bg-slate-900">
            <Link href="/dashboard" class="px-2 py-1 font-semibold tracking-tight">
                <span class="inline-flex items-center gap-2"><img :src="'/images/logo.png'" alt="" class="h-8 w-auto" /><span>Framework <span class="text-sky-600 dark:text-sky-500">Cloud</span></span></span>
            </Link>

            <nav class="mt-6 flex flex-1 flex-col gap-6">
                <div class="flex flex-col gap-0.5">
                    <div class="mb-1 px-2 text-xs font-medium uppercase tracking-wide text-slate-500">Platform</div>
                    <Link
                        v-for="item in mainNav" :key="item.href" :href="item.href"
                        class="rounded-md px-2 py-1.5 text-sm"
                        :class="current(item.href)
                            ? 'bg-sky-100 font-medium text-sky-700 dark:bg-sky-950/50 dark:text-sky-300'
                            : 'text-slate-700 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'"
                    >
                        {{ item.label }}
                    </Link>
                </div>

                <div v-if="adminNav.length" class="flex flex-col gap-0.5">
                    <div class="mb-1 px-2 text-xs font-medium uppercase tracking-wide text-slate-500">Admin</div>
                    <Link
                        v-for="item in adminNav" :key="item.href" :href="item.href"
                        class="rounded-md px-2 py-1.5 text-sm"
                        :class="current(item.href)
                            ? 'bg-sky-100 font-medium text-sky-700 dark:bg-sky-950/50 dark:text-sky-300'
                            : 'text-slate-700 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'"
                    >
                        {{ item.label }}
                    </Link>
                </div>
            </nav>

            <div class="mb-3 flex items-center justify-between px-2">
                <span class="text-xs text-slate-500">Theme</span>
                <ThemeToggle />
            </div>

            <div class="flex items-center justify-between gap-2 border-t border-slate-200 pt-3 dark:border-slate-800">
                <Link href="/settings/profile" class="flex min-w-0 items-center gap-2 rounded-md px-1 py-1 hover:bg-slate-100 dark:hover:bg-slate-800">
                    <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-sky-600 text-xs font-semibold text-white">
                        {{ user?.initials }}
                    </span>
                    <span class="truncate text-sm text-slate-800 dark:text-slate-200">{{ user?.name }}</span>
                </Link>
                <button v-if="!page.props.singleUser" class="rounded-md px-2 py-1 text-xs text-slate-500 hover:text-slate-900 dark:hover:text-slate-100" @click="logout">
                    Sign out
                </button>
            </div>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="flex items-center justify-between border-b border-slate-200 px-4 py-3 md:hidden dark:border-slate-800">
                <Link href="/dashboard" class="font-semibold"><span class="inline-flex items-center gap-2"><img :src="'/images/logo.png'" alt="" class="h-8 w-auto" /><span>Framework <span class="text-sky-600 dark:text-sky-500">Cloud</span></span></span></Link>
                <div class="flex items-center gap-3">
                    <ThemeToggle />
                    <button v-if="!page.props.singleUser" class="text-sm text-slate-500" @click="logout">Sign out</button>
                </div>
            </header>

            <main class="mx-auto w-full max-w-4xl flex-1 px-6 py-10">
                <slot />
            </main>
        </div>
    </div>
</template>
