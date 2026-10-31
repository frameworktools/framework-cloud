<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const nav = computed(() => page.props.nav ?? []);
const section = (name) => nav.value.filter((i) => (i.section ?? null) === name);
const mainNav = computed(() => section(null));
// Headed groups in order; "You" (settings, account, server) sits last without a heading
const groups = computed(() => [['Framework', 'Framework'], ['Agents', 'Agents'], ['You', null]]
    .map(([name, heading]) => ({ heading, items: section(name) }))
    .filter((g) => g.items.length));

const path = computed(() => page.url.split('?')[0]);
const current = (item) => [item.match ?? item.href].flat()
    .some((base) => path.value === base || path.value.startsWith(base + '/'));
// Projects is current on every project page; the list itself is in the second column
const projectsCurrent = computed(() => path.value === '/projects' || path.value.startsWith('/projects/'));

const linkClass = (active) => active
    ? 'bg-sky-100 font-medium text-sky-700 dark:bg-sky-950/50 dark:text-sky-300'
    : 'text-slate-700 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800';
</script>

<template>
    <nav class="flex flex-col gap-6">
        <div class="flex flex-col gap-0.5">
            <Link href="/projects" class="rounded-md px-2 py-1.5 text-sm" :class="linkClass(projectsCurrent)">Projects</Link>
            <Link
                v-for="item in mainNav" :key="item.href" :href="item.href"
                class="rounded-md px-2 py-1.5 text-sm" :class="linkClass(current(item))"
            >{{ item.label }}</Link>
        </div>

        <div
            v-for="group in groups" :key="group.heading ?? 'you'" class="flex flex-col gap-0.5"
            :class="{ 'border-t border-slate-200 pt-4 dark:border-slate-800': !group.heading }"
        >
            <div v-if="group.heading" class="mb-1 px-2 text-xs font-medium uppercase tracking-wide text-slate-500">{{ group.heading }}</div>
            <template v-for="item in group.items" :key="item.href">
                <a v-if="item.external" :href="item.href" class="rounded-md px-2 py-1.5 text-sm" :class="linkClass(false)">{{ item.label }}</a>
                <Link v-else :href="item.href" class="rounded-md px-2 py-1.5 text-sm" :class="linkClass(current(item))">{{ item.label }}</Link>
            </template>
        </div>
    </nav>
</template>
