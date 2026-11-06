<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { ask } from '@/confirm.js';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import UiButton from '@/Components/UiButton.vue';
import UiTabs from '@/Components/UiTabs.vue';
import UiTable from '@/Components/UiTable.vue';
import UiBadge from '@/Components/UiBadge.vue';
import AgentsTabs from './AgentsTabs.vue';
import { CheckIcon, ClipboardDocumentIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ mcpUrl: String, connections: Array, active: Boolean, historyCount: Number, approvedApps: { type: Array, default: () => [] } });

// Web apps approved once reconnect with this access, without the consent screen
const accessOptions = [{ value: 'full', label: 'Full access' }, { value: 'read', label: 'Read only' }];
const setAccess = (app, access) => router.patch(`/agents/apps/${app.id}`, { access }, { preserveScroll: true });
const forget = async (app) => {
    if (await ask(`Forget ${app.name}? It asks for approval the next time it connects. Its current connection stays.`)) {
        router.delete(`/agents/apps/${app.id}`, { preserveScroll: true });
    }
};

const copied = ref(false);
async function copy() {
    await navigator.clipboard?.writeText(props.mcpUrl);
    copied.value = true;
    setTimeout(() => (copied.value = false), 1500);
}

const disconnect = async (connection) => {
    if (await ask(`Disconnect ${connection.name}? It loses access to your account at once.`)) {
        router.delete(`/agents/${connection.id}`, { preserveScroll: true });
    }
};

const app = ref('claude');
const apps = [
    { value: 'claude', label: 'Claude' },
    { value: 'chatgpt', label: 'ChatGPT' },
    { value: 'claude-code', label: 'Claude Code' },
    { value: 'codex', label: 'Codex' },
];
const step = 'flex gap-3';
const number = 'flex size-6 shrink-0 items-center justify-center rounded-full bg-slate-800/5 text-xs font-medium tabular-nums text-slate-600 dark:bg-white/10 dark:text-slate-300';
const command = 'mt-2 block overflow-x-auto whitespace-nowrap rounded-lg bg-[#0c1526] px-3 py-2 font-mono text-xs text-slate-100';
</script>

<template>
    <Head :title="active ? 'Connected agents' : 'Connect your agent'" />

    <AppLayout>
        <div class="flex max-w-4xl flex-col gap-8">
            <div class="flex flex-col gap-6">
                <PageHeader :title="active ? 'Connected agents' : 'Connect your agent'">
                    <template #description>
                        Claude, ChatGPT, and other AI agents can read your projects, published artifacts, and reports, and write reports from your results.
                        You approve each agent and choose what it may do.
                    </template>
                </PageHeader>
                <AgentsTabs :history-count="historyCount" />
            </div>

            <section v-if="connections.length" class="flex flex-col gap-3">
                <div>
                    <h2 class="text-base font-medium text-slate-900 dark:text-white">Connection history</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Each time you approve an agent, with ended connections from the last 90 days.
                        Disconnecting in Claude or ChatGPT does not notify Framework Cloud, so the connection stays active here until it expires after 30 days unused.
                        Disconnect it here to end it at once.
                    </p>
                </div>
                <UiTable label="Connection history">
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
            </section>

            <section v-if="approvedApps.length" class="flex flex-col gap-3">
                <div>
                    <h2 class="text-base font-medium text-slate-900 dark:text-white">Approved apps</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        These apps connect again with the access shown, without asking. Forget an app to approve it again next time.
                        Claude Code, Codex, and other apps on your computer always ask.
                    </p>
                </div>
                <UiTable label="Approved apps">
                    <thead><tr><th>App</th><th>Access</th><th>Approved</th><th class="actions"><span class="sr-only">Actions</span></th></tr></thead>
                    <tbody>
                        <tr v-for="app in approvedApps" :key="app.id">
                            <td class="strong">{{ app.name }} <span class="font-normal text-slate-500">({{ app.host }})</span></td>
                            <td>
                                <select :value="app.access" :aria-label="`Access for ${app.name}`" class="rounded-md border border-slate-800/15 bg-white py-1 pr-8 pl-2 text-sm text-slate-800 dark:border-white/15 dark:bg-slate-900 dark:text-white"
                                    @change="setAccess(app, $event.target.value)">
                                    <option v-for="option in accessOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                </select>
                            </td>
                            <td>{{ app.approved_at }}</td>
                            <td class="actions"><UiButton variant="ghost" size="sm" @click="forget(app)">Forget</UiButton></td>
                        </tr>
                    </tbody>
                </UiTable>
            </section>

            <section class="flex flex-col gap-3">
                <div>
                    <h2 class="text-base font-medium text-slate-900 dark:text-white">Server address</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Add this address to your AI app as a connector, or custom MCP server.</p>
                </div>
                <div class="flex w-full max-w-2xl items-center gap-2 rounded-lg bg-slate-800/5 py-1 ps-4 pe-1 dark:bg-white/10">
                    <code class="min-w-0 flex-1 truncate font-mono text-sm text-slate-900 dark:text-white">{{ mcpUrl }}</code>
                    <UiButton variant="ghost" size="sm" class="shrink-0" @click="copy">
                        <component :is="copied ? CheckIcon : ClipboardDocumentIcon" class="size-4 text-slate-500 dark:text-slate-400" aria-hidden="true" /><template v-if="copied">Copied</template><template v-else>Copy<span class="hidden sm:inline">&nbsp;address</span></template>
                    </UiButton>
                </div>
            </section>

            <section class="flex flex-col gap-4">
                <h2 class="text-base font-medium text-slate-900 dark:text-white">Setup steps</h2>
                <UiTabs v-model="app" :tabs="apps" label="AI app" />

                <ol v-if="app === 'claude'" class="flex flex-col gap-4 text-sm text-slate-700 dark:text-slate-300">
                    <li :class="step"><span :class="number">1</span><span>In Claude, open Customize, then Connectors.</span></li>
                    <li :class="step"><span :class="number">2</span><span>Select Add, then Custom connector. Name it Framework Cloud and paste the server address.</span></li>
                    <li :class="step"><span :class="number">3</span><span>Under Authentication, choose Sign in now. Under OAuth client, choose Register automatically. Claude detects both. Leave Request headers empty.</span></li>
                    <li :class="step"><span :class="number">4</span><span>Add the connector. Claude opens Framework Cloud. Sign in, choose the agent's access, and select Connect.</span></li>
                    <li :class="step"><span :class="number">5</span><span>In a chat, turn on Framework Cloud from the tools menu.</span></li>
                </ol>
                <p v-if="app === 'claude'" class="text-sm text-slate-500 dark:text-slate-400">On Team and Enterprise plans, an owner adds custom connectors for the organization.</p>

                <ol v-if="app === 'chatgpt'" class="flex flex-col gap-4 text-sm text-slate-700 dark:text-slate-300">
                    <li :class="step"><span :class="number">1</span><span>In ChatGPT, open Settings, then Apps and connectors. Under Advanced, turn on Developer mode.</span></li>
                    <li :class="step"><span :class="number">2</span><span>Select Create. Name it Framework Cloud, paste the server address, and choose OAuth.</span></li>
                    <li :class="step"><span :class="number">3</span><span>Sign in here, choose the agent's access, and select Connect.</span></li>
                    <li :class="step"><span :class="number">4</span><span>In a chat, add Framework Cloud from the tools menu.</span></li>
                </ol>
                <p v-if="app === 'chatgpt'" class="text-sm text-slate-500 dark:text-slate-400">On Business and Enterprise plans, a workspace admin allows custom connectors.</p>

                <ol v-if="app === 'claude-code'" class="flex flex-col gap-4 text-sm text-slate-700 dark:text-slate-300">
                    <li :class="step"><span :class="number">1</span><span class="min-w-0 flex-1">Add the server in a terminal:<code :class="command">claude mcp add --transport http framework {{ mcpUrl }}</code></span></li>
                    <li :class="step"><span :class="number">2</span><span>In Claude Code, run <code class="font-mono text-xs">/mcp</code>, choose framework, and authenticate.</span></li>
                    <li :class="step"><span :class="number">3</span><span>Sign in here in the browser that opens, choose the agent's access, and select Connect.</span></li>
                </ol>

                <ol v-if="app === 'codex'" class="flex flex-col gap-4 text-sm text-slate-700 dark:text-slate-300">
                    <li :class="step"><span :class="number">1</span><span class="min-w-0 flex-1">Add the server in a terminal:<code :class="command">codex mcp add framework --url {{ mcpUrl }}</code></span></li>
                    <li :class="step"><span :class="number">2</span><span class="min-w-0 flex-1">Sign in:<code :class="command">codex mcp login framework</code></span></li>
                    <li :class="step"><span :class="number">3</span><span>Sign in here in the browser that opens, choose the agent's access, and select Connect.</span></li>
                </ol>
            </section>

            <section class="flex flex-col gap-3">
                <h2 class="text-base font-medium text-slate-900 dark:text-white">Access levels</h2>
                <dl class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-[8rem_1fr]">
                    <dt class="font-medium text-slate-800 dark:text-slate-200">Full access</dt>
                    <dd class="text-slate-500 dark:text-slate-400">Reads everything below. Creates, renames, and deletes projects, publishes and deletes artifacts, writes, publishes, and deletes reports, and manages your skills. Deleted items go to the Trash, where you can restore them. Recommended.</dd>
                    <dt class="font-medium text-slate-800 dark:text-slate-200">Read only</dt>
                    <dd class="text-slate-500 dark:text-slate-400">Reads projects, artifacts, reports, skills, and blueprints.</dd>
                </dl>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    Agents cannot run code. Files up to 10 MB publish through an agent; use the packages or fw publish for larger files. What an agent reads, including private projects and drafts, goes to the company that runs it.
                </p>
            </section>

        </div>
    </AppLayout>
</template>
