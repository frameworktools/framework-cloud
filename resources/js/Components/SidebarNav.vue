<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { PlusIcon } from '@heroicons/vue/24/outline';

const page = usePage();
const nav = computed(() => page.props.nav ?? []);
const recent = computed(() => page.props.recentProjects ?? { items: [], total: 0 });
const section = (name) => nav.value.filter((i) => (i.section ?? null) === name);
const mainNav = computed(() => section(null));
const frameworkNav = computed(() => section('Framework'));
const settingsNav = computed(() => section('Settings'));

const path = computed(() => page.url.split('?')[0]);
const current = (item) => {
    const base = item.match ?? item.href;
    return path.value === base || path.value.startsWith(base + '/');
};
// The Projects heading is current on the list and on the new-project page; a project's own page highlights its entry
const projectsCurrent = computed(() => path.value === '/projects' || path.value === '/projects/create');
const projectCurrent = (project) => path.value === `/projects/${project.slug}` || path.value.startsWith(`/projects/${project.slug}/`);

const linkClass = (active) => active
    ? 'bg-sky-100 font-medium text-sky-700 dark:bg-sky-950/50 dark:text-sky-300'
    : 'text-slate-700 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800';
</script>

<template>
    <nav class="flex flex-col gap-6">
        <div class="flex flex-col gap-0.5">
            <div class="flex items-center gap-1">
                <Link href="/projects" class="flex-1 rounded-md px-2 py-1.5 text-sm" :class="linkClass(projectsCurrent)">Projects</Link>
                <Link
                    href="/projects/create" title="New project" aria-label="New project"
                    class="rounded-md p-1.5 text-slate-500 hover:bg-slate-100 hover:text-slate-900 dark:hover:bg-slate-800 dark:hover:text-slate-100"
                ><PlusIcon class="size-4" /></Link>
            </div>
            <div v-if="recent.items.length" class="ml-3 flex flex-col gap-0.5 border-l border-slate-200 pl-2 dark:border-slate-800">
                <Link
                    v-for="project in recent.items" :key="project.id" :href="`/projects/${project.slug}`"
                    class="truncate rounded-md px-2 py-1 text-sm" :class="linkClass(projectCurrent(project))"
                >{{ project.name }}</Link>
                <Link
                    v-if="recent.total > recent.items.length" href="/projects"
                    class="rounded-md px-2 py-1 text-xs text-slate-500 hover:text-slate-900 dark:hover:text-slate-100"
                >All {{ recent.total }} projects</Link>
            </div>
            <Link
                v-for="item in mainNav" :key="item.href" :href="item.href"
                class="rounded-md px-2 py-1.5 text-sm" :class="linkClass(current(item))"
            >{{ item.label }}</Link>
        </div>

        <div v-if="frameworkNav.length" class="flex flex-col gap-0.5">
            <div class="mb-1 px-2 text-xs font-medium uppercase tracking-wide text-slate-500">Framework</div>
            <Link
                v-for="item in frameworkNav" :key="item.href" :href="item.href"
                class="rounded-md px-2 py-1.5 text-sm" :class="linkClass(current(item))"
            >{{ item.label }}</Link>
        </div>

        <div v-if="settingsNav.length" class="flex flex-col gap-0.5">
            <div class="mb-1 px-2 text-xs font-medium uppercase tracking-wide text-slate-500">Settings</div>
            <Link
                v-for="item in settingsNav" :key="item.href" :href="item.href"
                class="rounded-md px-2 py-1.5 text-sm" :class="linkClass(current(item))"
            >{{ item.label }}</Link>
        </div>
    </nav>
</template>
