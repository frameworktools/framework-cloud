<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { ChevronDownIcon, ChevronRightIcon, PlusIcon, Squares2X2Icon } from '@heroicons/vue/24/outline';
import SectionLayout from '@/Layouts/SectionLayout.vue';

// The project pages' second column: all projects, a new one, and each of
// yours. The project you're in opens to show its pages.
const page = usePage();
const list = computed(() => page.props.projectList ?? { items: [], total: 0 });
const open = computed(() => page.url.match(/^\/projects\/([A-Za-z0-9_-]+)/)?.[1]);

const sections = [
    ['', 'Overview'],
    ['/setup', 'Project setup'],
    ['/github', 'GitHub & Pages'],
    ['/published', 'Published documents'],
    ['/computers', 'Computers & tokens'],
];

const items = computed(() => [
    { label: 'All projects', href: '/projects', icon: Squares2X2Icon, exact: true },
    { label: 'New project', href: '/projects/create', icon: PlusIcon },
    ...(list.value.items.length ? [{ heading: 'Your projects' }] : []),
    ...list.value.items.flatMap((p) => {
        const base = `/projects/${p.slug}`;
        if (p.slug !== open.value) return [{ label: p.name, href: base, icon: ChevronRightIcon }];
        return [
            { label: p.name, href: base, icon: ChevronDownIcon, quiet: true },
            ...sections.map(([path, label]) => ({ label, href: base + path, exact: true, indent: true })),
        ];
    }),
]);
</script>

<template>
    <SectionLayout heading="Projects" :items="items">
        <slot />
        <template v-if="list.total > list.items.length" #aside>
            <Link href="/projects" class="px-3 text-xs text-slate-500 hover:text-slate-900 dark:hover:text-slate-100">All {{ list.total }} projects</Link>
        </template>
    </SectionLayout>
</template>
