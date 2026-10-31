<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { PlusIcon } from '@heroicons/vue/20/solid';
import SectionLayout from '@/Layouts/SectionLayout.vue';
import UiButton from '@/Components/UiButton.vue';

// The project index's second column: a new project and each of yours.
// Inside a project, ProjectLayout takes over.
const page = usePage();
const list = computed(() => page.props.projectList ?? { items: [], total: 0 });
const items = computed(() => list.value.items.map((p) => ({ label: p.name, href: `/projects/${p.slug}` })));
</script>

<template>
    <SectionLayout heading="Projects" :items="items">
        <template #actions>
            <Link href="/projects/create" class="block"><UiButton class="w-full"><PlusIcon class="size-4" />New project</UiButton></Link>
        </template>
        <slot />
        <template v-if="list.total > list.items.length" #aside>
            <span class="text-xs text-slate-500">Showing the newest {{ list.items.length }} of {{ list.total }}</span>
        </template>
    </SectionLayout>
</template>
