<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { PlusIcon } from '@heroicons/vue/20/solid';
import SectionLayout from '@/Layouts/SectionLayout.vue';
import UiButton from '@/Components/UiButton.vue';

// The project index's second column: a new project, all projects, and each
// of yours. Inside a project, ProjectLayout takes over.
const page = usePage();
const list = computed(() => page.props.projectList ?? { items: [], total: 0 });
const items = computed(() => [
    { label: 'All projects', href: '/projects', exact: true },
    ...(list.value.items.length ? [{ heading: 'Your projects' }] : []),
    ...list.value.items.map((p) => ({ label: p.name, href: `/projects/${p.slug}` })),
]);
</script>

<template>
    <SectionLayout heading="Projects" :items="items">
        <template #lead>
            <Link href="/projects/create" class="block"><UiButton class="w-full"><PlusIcon class="size-4" />New project</UiButton></Link>
        </template>
        <slot />
        <template v-if="list.total > list.items.length" #aside>
            <Link href="/projects" class="px-3 text-xs text-slate-500 hover:text-slate-900 dark:hover:text-slate-100">All {{ list.total }} projects</Link>
        </template>
    </SectionLayout>
</template>
