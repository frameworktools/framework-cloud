<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { FolderIcon, PlusIcon, Squares2X2Icon } from '@heroicons/vue/24/outline';
import SectionLayout from '@/Layouts/SectionLayout.vue';

// The project pages' second column: all projects, a new one, and each of yours
const page = usePage();
const list = computed(() => page.props.projectList ?? { items: [], total: 0 });
const items = computed(() => [
    { label: 'All projects', href: '/projects', icon: Squares2X2Icon, exact: true },
    { label: 'New project', href: '/projects/create', icon: PlusIcon },
    ...(list.value.items.length ? [{ heading: 'Your projects' }] : []),
    ...list.value.items.map((p) => ({ label: p.name, href: `/projects/${p.slug}`, icon: FolderIcon })),
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
