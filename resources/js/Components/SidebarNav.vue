<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { HomeIcon, FolderIcon, AdjustmentsHorizontalIcon, PhotoIcon, Square3Stack3DIcon, DocumentTextIcon, Square2StackIcon, FolderOpenIcon, CubeIcon, SparklesIcon, RectangleStackIcon, UserCircleIcon, QuestionMarkCircleIcon, ServerStackIcon, LinkIcon, TrashIcon, UsersIcon } from '@heroicons/vue/24/outline';
const icons = { '/admin/users': UsersIcon, '/agents': LinkIcon, '/trash': TrashIcon, '/settings': AdjustmentsHorizontalIcon, '/published': PhotoIcon, '/collections': Square3Stack3DIcon, '/reports': DocumentTextIcon, '/blueprints': Square2StackIcon, '/structures': FolderOpenIcon, '/package-sets': CubeIcon, '/skills': SparklesIcon, '/skill-sets': RectangleStackIcon, '/account': UserCircleIcon, '/docs': QuestionMarkCircleIcon, '/admin': ServerStackIcon };
const icon = item => icons[Object.keys(icons).find(prefix => item.href === prefix || item.href.startsWith(prefix + '/'))] ?? FolderIcon;

const page = usePage();
const nav = computed(() => page.props.nav ?? []);
const section = (name) => nav.value.filter((i) => (i.section ?? null) === name);
const mainNav = computed(() => section(null));
// Headed groups in order; "You" (settings, account, server) sits last without a heading
const groups = computed(() => [['Your research', 'Your research'], ['Framework', 'Framework'], ['Agents', 'AI Agents'], ['You', null]]
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
    <nav aria-label="Main" class="flex flex-col gap-4">
        <div class="flex flex-col gap-0.5">
            <Link href="/dashboard" class="flex items-center gap-2.5 rounded-md px-2 py-1 text-sm" :class="linkClass(path === '/dashboard')"><HomeIcon class="size-4 shrink-0 opacity-70" aria-hidden="true" />Dashboard</Link>
            <Link href="/projects" class="flex items-center gap-2.5 rounded-md px-2 py-1 text-sm" :class="linkClass(projectsCurrent)"><FolderIcon class="size-4 shrink-0 opacity-70" aria-hidden="true" />Projects</Link>
            <Link
                v-for="item in mainNav" :key="item.href" :href="item.href"
                class="flex items-center gap-2.5 rounded-md px-2 py-1 text-sm" :class="linkClass(current(item))"
            ><component :is="icon(item)" class="size-4 shrink-0 opacity-70" aria-hidden="true" />{{ item.label }}</Link>
        </div>

        <div
            v-for="group in groups" :key="group.heading ?? 'you'" class="flex flex-col gap-0.5"
            :class="{ 'border-t border-slate-200 pt-3 dark:border-slate-800': !group.heading }"
        >
            <div v-if="group.heading" class="mb-0.5 px-2 text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ group.heading }}</div>
            <template v-for="item in group.items" :key="item.href">
                <a v-if="item.external" :href="item.href" class="flex items-center gap-2.5 rounded-md px-2 py-1 text-sm" :class="linkClass(false)"><component :is="icon(item)" class="size-4 shrink-0 opacity-70" aria-hidden="true" />{{ item.label }}</a>
                <Link v-else :href="item.href" class="flex items-center gap-2.5 rounded-md px-2 py-1 text-sm" :class="linkClass(current(item))"><component :is="icon(item)" class="size-4 shrink-0 opacity-70" aria-hidden="true" />{{ item.label }}</Link>
            </template>
        </div>
    </nav>
</template>
