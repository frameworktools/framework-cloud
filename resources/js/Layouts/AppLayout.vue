<script setup>
import { pageClass } from '@/ui.js';
import { computed, ref, watch } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import ThemeToggle from '@/Components/ThemeToggle.vue';
import SidebarNav from '@/Components/SidebarNav.vue';
import FlashToast from '@/Components/FlashToast.vue';
import { Bars3Icon, XMarkIcon } from '@heroicons/vue/24/outline';

defineProps({ bleed: Boolean });

const page = usePage();
const user = computed(() => page.props.auth.user);
const logout = () => router.post('/logout');

// The phone-width menu closes on every visit
const menuOpen = ref(false);
watch(() => page.url, () => { menuOpen.value = false; });
</script>

<template>
    <a href="#main" class="sr-only rounded-md bg-white px-4 py-2 text-sm font-medium text-slate-900 shadow-lg ring-1 ring-slate-900/10 focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-[100] dark:bg-slate-800 dark:text-white">Skip to content</a>
    <div class="flex min-h-screen">
        <aside aria-label="Sidebar" class="hidden w-60 shrink-0 flex-col overflow-y-auto border-r border-slate-200 bg-slate-50 p-4 md:sticky md:top-0 md:flex md:h-screen dark:border-slate-800 dark:bg-slate-900">
            <Link href="/dashboard" class="px-2 py-1 font-semibold tracking-tight">
                <span class="inline-flex items-center gap-2"><img :src="'/images/logo-80.webp'" alt="" width="73" height="80" class="h-8 w-auto" /><span>Framework <span class="text-sky-700 dark:text-sky-500">Cloud</span></span></span>
            </Link>

            <SidebarNav class="mt-6 flex-1" />

            <div class="mt-3 mb-3 flex items-center justify-between px-2">
                <span class="text-xs text-slate-500 dark:text-slate-400">Theme</span>
                <ThemeToggle />
            </div>

            <div class="flex items-center justify-between gap-2 border-t border-slate-200 pt-3 dark:border-slate-800">
                <Link href="/account/account" class="flex min-w-0 items-center gap-2 rounded-md px-1 py-1 hover:bg-slate-100 dark:hover:bg-slate-800">
                    <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-sky-700 text-xs font-semibold text-white">
                        {{ user?.initials }}
                    </span>
                    <span class="truncate text-sm text-slate-800 dark:text-slate-200">{{ user?.name }}</span>
                </Link>
                <button v-if="!page.props.singleUser" class="rounded-md px-2 py-1 text-xs text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100" @click="logout">
                    Sign out
                </button>
            </div>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="flex items-center justify-between border-b border-slate-200 px-4 py-3 md:hidden dark:border-slate-800">
                <Link href="/dashboard" class="font-semibold"><span class="inline-flex items-center gap-2"><img :src="'/images/logo-80.webp'" alt="" width="73" height="80" class="h-8 w-auto" /><span>Framework <span class="text-sky-700 dark:text-sky-500">Cloud</span></span></span></Link>
                <div class="flex items-center gap-3">
                    <ThemeToggle />
                    <button
                        type="button" class="rounded-md p-1 text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800"
                        :aria-expanded="menuOpen" aria-label="Menu" @click="menuOpen = !menuOpen"
                    >
                        <XMarkIcon v-if="menuOpen" class="size-6" />
                        <Bars3Icon v-else class="size-6" />
                    </button>
                </div>
            </header>
            <div v-if="menuOpen" class="flex flex-col gap-4 border-b border-slate-200 bg-slate-50 px-4 py-4 md:hidden dark:border-slate-800 dark:bg-slate-900">
                <SidebarNav />
                <div class="flex items-center justify-between border-t border-slate-200 pt-3 dark:border-slate-800">
                    <span class="truncate text-sm text-slate-700 dark:text-slate-300">{{ user?.name }}</span>
                    <button v-if="!page.props.singleUser" class="text-sm text-slate-500 dark:text-slate-400" @click="logout">Sign out</button>
                </div>
            </div>

            <!-- bleed: the page lays out its own columns (SectionLayout) -->
            <main v-if="bleed" id="main" tabindex="-1" class="flex-1 focus:outline-none">
                <slot />
            </main>
            <main v-else id="main" tabindex="-1" :class="[pageClass, 'flex-1 focus:outline-none']">
                <slot />
            </main>
        </div>
        <FlashToast />
    </div>
</template>
