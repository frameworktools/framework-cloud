<script setup>
import { router } from '@inertiajs/vue3';
import { ask } from '@/confirm.js';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import UiBadge from '@/Components/UiBadge.vue';
import UiButton from '@/Components/UiButton.vue';
import UiTable from '@/Components/UiTable.vue';
import EmptyState from '@/Components/EmptyState.vue';
import AgentsTabs from './AgentsTabs.vue';
import { LinkIcon } from '@heroicons/vue/24/outline';

defineProps({ connections: Array, connectionCount: Number, historyCount: Number });

const disconnect = async (connection) => {
    if (await ask(`Disconnect ${connection.name}? It loses access to your account at once.`)) {
        router.delete(`/agents/${connection.id}`, { preserveScroll: true });
    }
};
</script>

<template>
    <Head title="Agent connections" />

    <AppLayout>
        <div class="flex max-w-4xl flex-col gap-8">
            <div class="flex flex-col gap-6">
                <PageHeader title="Agent connections">
                    <template #description>
                        Each approval, with ended connections from the last 90 days.
                        Removing an app in Claude or ChatGPT does not end its connection here. Disconnect it here, or it ends after 30 days unused.
                    </template>
                </PageHeader>
                <AgentsTabs :connection-count="connectionCount" :history-count="historyCount" />
            </div>

            <template v-if="connections.length">
                <UiTable label="Agent connections">
                    <thead>
                        <tr><th>Agent</th><th>Status</th><th>Access</th><th>Connected</th><th>Last used</th><th class="actions"><span class="sr-only">Actions</span></th></tr>
                    </thead>
                    <tbody>
                        <tr v-for="connection in connections" :key="connection.id">
                            <td class="strong">{{ connection.name }}</td>
                            <td><UiBadge :variant="connection.active ? 'green' : 'slate'">{{ connection.status }}</UiBadge></td>
                            <td>{{ connection.access }}</td>
                            <td>{{ connection.created_at }}</td>
                            <td>{{ connection.last_used_at ?? 'Never' }}</td>
                            <td class="actions"><UiButton v-if="connection.active" variant="ghost" size="sm" @click="disconnect(connection)">Disconnect</UiButton></td>
                        </tr>
                    </tbody>
                </UiTable>
                <p class="text-sm text-slate-500 dark:text-slate-400">To change an agent's access, disconnect it and connect it again.</p>
            </template>
            <EmptyState v-else :icon="LinkIcon" title="No connections" description="Agents you approve appear here." />
        </div>
    </AppLayout>
</template>
