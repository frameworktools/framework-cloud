<script setup>
import { router } from '@inertiajs/vue3';
import { ask } from '@/confirm.js';
import PageHeader from '@/Components/PageHeader.vue';
import UiBadge from '@/Components/UiBadge.vue';
import UiButton from '@/Components/UiButton.vue';
import UiTable from '@/Components/UiTable.vue';
import AgentsLayout from './AgentsLayout.vue';

defineProps({ connections: Array });

const disconnect = async (connection) => {
    if (await ask(`Disconnect ${connection.name}? It loses access to your account at once.`)) {
        router.delete(`/agents/${connection.id}`, { preserveScroll: true });
    }
};
</script>

<template>
    <Head title="Agent connections" />

    <AgentsLayout>
        <div class="flex flex-col gap-8">
            <PageHeader title="Agent connections">
                <template #description>
                    The agents connected to your account. Removing an agent in Claude or ChatGPT does not disconnect it. To cut it off, disconnect it here. A connection you don't use for 30 days ends on its own.
                </template>
            </PageHeader>

            <section class="flex flex-col gap-3">
                <div>
                    <h2 class="text-base font-medium text-slate-900 dark:text-white">Connection history</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Ended connections stay here for 90 days.</p>
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
                <p class="text-sm text-slate-500 dark:text-slate-400">To change an approved app's access, use <Link href="/agents/permissions" class="underline underline-offset-2">Permissions</Link>. For Claude Code and Codex, disconnect and connect again.</p>
            </template>
            <p v-else class="mt-3 text-xs text-slate-500 dark:text-slate-400">No connections yet. Approved agents will appear here.</p>
            </section>
        </div>
    </AgentsLayout>
</template>
