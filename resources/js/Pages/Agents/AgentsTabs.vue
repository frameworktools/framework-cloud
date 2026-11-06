<script setup>
import { usePage } from '@inertiajs/vue3';
import UiTabs from '@/Components/UiTabs.vue';

// Setup and History, shared by both Agents pages
defineProps({ historyCount: Number });
const page = usePage();
</script>

<template>
    <UiTabs label="Agents" :tabs="[
        { label: 'Setup', href: '/agents', current: page.url.split('?')[0] === '/agents' },
        { label: 'History', href: '/agents/history', current: page.url.startsWith('/agents/history'), count: historyCount || undefined },
    ]" />
</template>
