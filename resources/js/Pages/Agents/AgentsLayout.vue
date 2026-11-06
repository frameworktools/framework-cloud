<script setup>
import SectionLayout from '@/Layouts/SectionLayout.vue';

// The AI agents section: setup steps for each app, then the connections and what agents did
const items = [
    { label: 'Setup', href: '/agents', exact: true },
    { label: 'Permissions', href: '/agents/permissions' },
    { label: 'Connections', href: '/agents/connections' },
    { label: 'History', href: '/agents/history' },
];
</script>

<template>
    <SectionLayout heading="AI agents" :items="items">
        <slot />
    </SectionLayout>
</template>
