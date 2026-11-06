<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { ask } from '@/confirm.js';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import UiButton from '@/Components/UiButton.vue';
import UiTabs from '@/Components/UiTabs.vue';
import UiTable from '@/Components/UiTable.vue';
import AgentsTabs from './AgentsTabs.vue';
import { CheckIcon, ClipboardDocumentIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ mcpUrl: String, connections: Array, historyCount: Number });

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
    <Head :title="connections.length ? 'Connected agents' : 'Connect your agent'" />

    <AppLayout>
        <div class="flex max-w-4xl flex-col gap-8">
            <div class="flex flex-col gap-6">
                <PageHeader :title="connections.length ? 'Connected agents' : 'Connect your agent'">
                    <template #description>
                        Claude, ChatGPT, and other AI agents can read your projects, published artifacts, and reports, and write reports from your results.
                        You approve each agent and choose what it may do.
                    </template>
                </PageHeader>
                <AgentsTabs :history-count="historyCount" />
            </div>

            <section v-if="connections.length" class="flex flex-col gap-3">
                <h2 class="text-base font-medium text-slate-900 dark:text-white">Connected agents</h2>
                <UiTable label="Connected agents">
                    <thead>
                        <tr><th>Agent</th><th>Access</th><th>Connected</th><th>Last used</th><th class="actions"><span class="sr-only">Actions</span></th></tr>
                    </thead>
                    <tbody>
                        <tr v-for="connection in connections" :key="connection.id">
                            <td class="strong">{{ connection.name }}</td>
                            <td>{{ connection.access }}</td>
                            <td>{{ connection.created_at }}</td>
                            <td>{{ connection.last_used_at ?? 'Never' }}</td>
                            <td class="actions"><UiButton variant="ghost" size="sm" @click="disconnect(connection)">Disconnect</UiButton></td>
                        </tr>
                    </tbody>
                </UiTable>
                <p class="text-sm text-slate-500 dark:text-slate-400">To change an agent's access, disconnect it and connect it again.</p>
            </section>

            <section class="flex flex-col gap-3">
                <div>
                    <h2 class="text-base font-medium text-slate-900 dark:text-white">Server address</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Add this address to your AI app as a connector, or custom MCP server.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <code class="rounded-lg bg-slate-800/5 px-3 py-2 font-mono text-sm text-slate-900 dark:bg-white/10 dark:text-white">{{ mcpUrl }}</code>
                    <UiButton variant="ghost" @click="copy">
                        <component :is="copied ? CheckIcon : ClipboardDocumentIcon" class="size-4 text-slate-500 dark:text-slate-400" aria-hidden="true" />{{ copied ? 'Copied' : 'Copy address' }}
                    </UiButton>
                </div>
            </section>

            <section class="flex flex-col gap-4">
                <h2 class="text-base font-medium text-slate-900 dark:text-white">Setup steps</h2>
                <UiTabs v-model="app" :tabs="apps" label="AI app" />

                <ol v-if="app === 'claude'" class="flex flex-col gap-4 text-sm text-slate-700 dark:text-slate-300">
                    <li :class="step"><span :class="number">1</span><span>In Claude, open Settings, then Connectors.</span></li>
                    <li :class="step"><span :class="number">2</span><span>Select Add custom connector. Name it Framework Cloud and paste the server address.</span></li>
                    <li :class="step"><span :class="number">3</span><span>Select Connect. Sign in here, choose the agent's access, and select Connect.</span></li>
                    <li :class="step"><span :class="number">4</span><span>In a chat, turn on Framework Cloud from the tools menu.</span></li>
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
                    <dt class="font-medium text-slate-800 dark:text-slate-200">Drafts only</dt>
                    <dd class="text-slate-500 dark:text-slate-400">Reads projects, artifacts, and reports. Creates and edits report drafts. Recommended.</dd>
                    <dt class="font-medium text-slate-800 dark:text-slate-200">Read only</dt>
                    <dd class="text-slate-500 dark:text-slate-400">Reads projects, artifacts, and reports.</dd>
                    <dt class="font-medium text-slate-800 dark:text-slate-200">Full access</dt>
                    <dd class="text-slate-500 dark:text-slate-400">Also publishes reports and changes who can read them.</dd>
                </dl>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    Agents cannot run code or publish new artifacts. What an agent reads, including private projects and drafts, goes to the company that runs it.
                </p>
            </section>

        </div>
    </AppLayout>
</template>
