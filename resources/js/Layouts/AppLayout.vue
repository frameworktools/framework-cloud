<script setup>
import { computed, ref, watch } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import ThemeToggle from '@/Components/ThemeToggle.vue';
import SidebarNav from '@/Components/SidebarNav.vue';
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
    <div class="flex min-h-screen">
        <aside class="hidden w-60 shrink-0 flex-col overflow-y-auto border-r border-slate-200 bg-slate-50 p-4 md:sticky md:top-0 md:flex md:h-screen dark:border-slate-800 dark:bg-slate-900">
            <Link href="/projects" class="px-2 py-1 font-semibold tracking-tight">
                <span class="inline-flex items-center gap-2"><img :src="'/images/logo.png'" alt="" class="h-8 w-auto" /><span>Framework <span class="text-sky-600 dark:text-sky-500">Cloud</span></span></span>
            </Link>

            <SidebarNav class="mt-6 flex-1" />

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
                <Link href="/projects" class="font-semibold"><span class="inline-flex items-center gap-2"><img :src="'/images/logo.png'" alt="" class="h-8 w-auto" /><span>Framework <span class="text-sky-600 dark:text-sky-500">Cloud</span></span></span></Link>
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
                    <button v-if="!page.props.singleUser" class="text-sm text-slate-500" @click="logout">Sign out</button>
                </div>
            </div>

            <!-- bleed: the page lays out its own columns (SectionLayout) -->
            <main v-if="bleed" class="flex-1">
                <slot />
            </main>
            <main v-else class="mx-auto w-full max-w-5xl flex-1 px-6 py-10 lg:px-10">
                <slot />
            </main>
        </div>
    </div>
</template>
