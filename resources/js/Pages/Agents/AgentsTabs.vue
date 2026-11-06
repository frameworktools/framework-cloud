<script setup>
import { usePage } from '@inertiajs/vue3';
import UiTabs from '@/Components/UiTabs.vue';

// Setup, Connections, and History, shared by the Agents pages
defineProps({ connectionCount: Number, historyCount: Number });
const page = usePage();
</script>

<template>
    <UiTabs label="Agents" :tabs="[
        { label: 'Setup', href: '/agents', current: page.url.split('?')[0] === '/agents' },
        { label: 'Connections', href: '/agents/connections', current: page.url.startsWith('/agents/connections'), count: connectionCount || undefined },
        { label: 'History', href: '/agents/history', current: page.url.startsWith('/agents/history'), count: historyCount || undefined },
    ]" />
</template>
