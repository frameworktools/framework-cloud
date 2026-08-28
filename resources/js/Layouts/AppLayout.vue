<script setup>
import { computed } from 'vue';
import { usePage, router } from '@inertiajs/vue3';

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
        <aside class="hidden w-60 shrink-0 flex-col border-r border-neutral-200 bg-neutral-50 p-4 md:flex dark:border-neutral-800 dark:bg-neutral-900">
            <Link href="/dashboard" class="px-2 py-1 font-semibold tracking-tight">
                framework<span class="text-orange-500">.pub</span>
            </Link>

            <nav class="mt-6 flex flex-1 flex-col gap-6">
                <div class="flex flex-col gap-0.5">
                    <div class="mb-1 px-2 text-xs font-medium uppercase tracking-wide text-neutral-500">Platform</div>
                    <Link
                        v-for="item in mainNav" :key="item.href" :href="item.href"
                        class="rounded-md px-2 py-1.5 text-sm"
                        :class="current(item.href)
                            ? 'bg-orange-100 font-medium text-orange-700 dark:bg-orange-950/50 dark:text-orange-300'
                            : 'text-neutral-700 hover:bg-neutral-100 dark:text-neutral-300 dark:hover:bg-neutral-800'"
                    >
                        {{ item.label }}
                    </Link>
                </div>

                <div v-if="adminNav.length" class="flex flex-col gap-0.5">
                    <div class="mb-1 px-2 text-xs font-medium uppercase tracking-wide text-neutral-500">Admin</div>
                    <Link
                        v-for="item in adminNav" :key="item.href" :href="item.href"
                        class="rounded-md px-2 py-1.5 text-sm"
                        :class="current(item.href)
                            ? 'bg-orange-100 font-medium text-orange-700 dark:bg-orange-950/50 dark:text-orange-300'
                            : 'text-neutral-700 hover:bg-neutral-100 dark:text-neutral-300 dark:hover:bg-neutral-800'"
                    >
                        {{ item.label }}
                    </Link>
                </div>
            </nav>

            <div class="flex items-center justify-between gap-2 border-t border-neutral-200 pt-3 dark:border-neutral-800">
                <Link href="/settings/profile" class="flex min-w-0 items-center gap-2 rounded-md px-1 py-1 hover:bg-neutral-100 dark:hover:bg-neutral-800">
                    <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-orange-500 text-xs font-semibold text-white">
                        {{ user?.initials }}
                    </span>
                    <span class="truncate text-sm text-neutral-800 dark:text-neutral-200">{{ user?.name }}</span>
                </Link>
                <button class="rounded-md px-2 py-1 text-xs text-neutral-500 hover:text-neutral-900 dark:hover:text-neutral-100" @click="logout">
                    Sign out
                </button>
            </div>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="flex items-center justify-between border-b border-neutral-200 px-4 py-3 md:hidden dark:border-neutral-800">
                <Link href="/dashboard" class="font-semibold">framework<span class="text-orange-500">.pub</span></Link>
                <button class="text-sm text-neutral-500" @click="logout">Sign out</button>
            </header>

            <main class="mx-auto w-full max-w-4xl flex-1 px-6 py-10">
                <slot />
            </main>
        </div>
    </div>
</template>
