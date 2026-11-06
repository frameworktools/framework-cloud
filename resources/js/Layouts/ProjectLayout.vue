<script setup>
import SectionLayout from '@/Layouts/SectionLayout.vue';

// Inside one project, the second column is that project's pages
const props = defineProps({ project: Object });

const base = `/projects/${props.project.slug}`;
const items = [
    { label: 'Overview', href: base, exact: true },
    { label: 'Project setup', href: `${base}/setup` },
    { label: 'GitHub & Pages', href: `${base}/github` },
    { label: 'Artifacts', href: `${base}/published` },
    { label: 'Collections', href: `/projects/${props.project.slug}/collections` },
    { label: 'Reports', href: `/projects/${props.project.slug}/reports` },
    { label: 'Computers & tokens', href: `${base}/computers` },
];
</script>

<template>
    <SectionLayout :heading="project.name" :back="{ label: 'Back to all projects', href: '/projects' }" :items="items">
        <slot />
    </SectionLayout>
</template>
