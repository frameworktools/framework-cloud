<script setup>
import { router } from '@inertiajs/vue3';
import { ask } from '@/confirm.js';
import PageHeader from '@/Components/PageHeader.vue';
import UiBadge from '@/Components/UiBadge.vue';
import UiButton from '@/Components/UiButton.vue';
import UiTable from '@/Components/UiTable.vue';
import AgentsLayout from './AgentsLayout.vue';

defineProps({ approvedApps: { type: Array, default: () => [] }, connections: Array });

// Web apps approved once reconnect with this access, without the consent screen
const accessOptions = [{ value: 'full', label: 'Full access' }, { value: 'read', label: 'Read only' }];
const setAccess = (app, access) => router.patch(`/agents/apps/${app.id}`, { access }, { preserveScroll: true });
const forget = async (app) => {
    if (await ask(`Forget ${app.name}? It asks for approval the next time it connects. Its current connection stays.`)) {
        router.delete(`/agents/apps/${app.id}`, { preserveScroll: true });
    }
};

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

            <section v-if="approvedApps.length" class="flex flex-col gap-3">
                <div>
                    <h2 class="text-base font-medium text-slate-900 dark:text-white">Approved apps</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">You approved these apps once. They connect again with the access shown, without asking. Claude Code and Codex ask every time.</p>
                </div>
                <UiTable label="Approved apps">
                    <thead><tr><th>App</th><th>Access</th><th>Approved</th><th class="actions"><span class="sr-only">Actions</span></th></tr></thead>
                    <tbody>
                        <tr v-for="approved in approvedApps" :key="approved.id">
                            <td class="strong">{{ approved.name }} <span class="font-normal text-slate-500">({{ approved.host }})</span></td>
                            <td>
                                <select :value="approved.access" :aria-label="`Access for ${approved.name}`" class="rounded-md border border-slate-800/15 bg-white py-1 pr-8 pl-2 text-sm text-slate-800 dark:border-white/15 dark:bg-slate-900 dark:text-white"
                                    @change="setAccess(approved, $event.target.value)">
                                    <option v-for="option in accessOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                </select>
                            </td>
                            <td>{{ approved.approved_at }}</td>
                            <td class="actions"><UiButton variant="ghost" size="sm" @click="forget(approved)">Forget</UiButton></td>
                        </tr>
                    </tbody>
                </UiTable>
            </section>

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
                <p class="text-sm text-slate-500 dark:text-slate-400">To change an agent's access, disconnect it and connect it again.</p>
            </template>
            <p v-else class="mt-3 text-xs text-slate-500 dark:text-slate-400">No connections yet. Approved agents will appear here.</p>
            </section>
        </div>
    </AgentsLayout>
</template>
