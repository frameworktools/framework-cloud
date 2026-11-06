<script setup>
import SectionLayout from '@/Layouts/SectionLayout.vue';

// The AI agents section: setup steps for each app, then the connections and what agents did
const items = [
    { heading: 'Setup' },
    { label: 'Claude', href: '/agents', exact: true },
    { label: 'ChatGPT', href: '/agents/setup/chatgpt' },
    { label: 'Claude Code CLI', href: '/agents/setup/claude-code' },
    { label: 'Codex CLI', href: '/agents/setup/codex' },
    { heading: 'Activity' },
    { label: 'Connections', href: '/agents/connections' },
    { label: 'History', href: '/agents/history' },
];
</script>

<template>
    <SectionLayout heading="AI agents" :items="items">
        <slot />
    </SectionLayout>
</template>
