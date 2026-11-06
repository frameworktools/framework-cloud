<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { ask } from '@/confirm.js';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import UiButton from '@/Components/UiButton.vue';
import UiTabs from '@/Components/UiTabs.vue';
import UiTable from '@/Components/UiTable.vue';
import AgentsTabs from './AgentsTabs.vue';
import SetupSteps from './SetupSteps.vue';
import { CheckIcon, ClipboardDocumentIcon } from '@heroicons/vue/24/outline';
import { ChevronRightIcon } from '@heroicons/vue/20/solid';

const props = defineProps({
    mcpUrl: String, active: Boolean, connectionCount: Number, historyCount: Number,
    approvedApps: { type: Array, default: () => [] },
});

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

const app = ref('claude-code');
const apps = [
    { value: 'claude-code', label: 'Claude Code' },
    { value: 'codex', label: 'Codex' },
    { value: 'claude', label: 'Claude' },
    { value: 'chatgpt', label: 'ChatGPT' },
];

// The Framework Cloud plugin connects to framework.pub only; self-hosted servers use manual setup
const marketplace = 'frameworktools/plugins';
const plugin = computed(() => props.mcpUrl === 'https://framework.pub/mcp');
const host = computed(() => new URL(props.mcpUrl).host);
const signIn = 'In the browser that opens, sign in, choose the access, and select Connect.';

const setup = computed(() => {
    const allowHost = ['In Settings, open Capabilities and add ', { code: host.value }, ' to the allowed websites, exactly as shown. Claude needs it to upload files. Start a new chat afterward.'];
    const openClaudeCode = ['Open Claude Code: select ', { code: '</>' }, ' at the top of the Claude desktop app, or run ', { code: 'claude' }, ' in a terminal.'];
    const openCodex = ['Open Codex: in the ChatGPT desktop app, choose Codex from the ChatGPT menu, or run ', { code: 'codex' }, ' in a terminal.'];

    return {
        'claude-code': {
            plugin: [
                openClaudeCode,
                ['Install the plugin. Skip this if you added it in Claude.', { cmd: `claude plugin marketplace add ${marketplace}` }, { cmd: 'claude plugin install framework-cloud@framework' }],
                ['Run ', { code: '/mcp' }, ', choose framework, and authenticate. ', signIn],
            ],
            manual: [
                openClaudeCode,
                ['Add the server:', { cmd: `claude mcp add --transport http framework ${props.mcpUrl}` }],
                ['Run ', { code: '/mcp' }, ', choose framework, and authenticate. ', signIn],
            ],
        },
        codex: {
            plugin: [
                openCodex,
                ['Install the plugin:', { cmd: `codex plugin marketplace add ${marketplace}` }, { cmd: 'codex plugin add framework-cloud@framework' }],
                ['Sign in:', { cmd: 'codex mcp login framework' }, signIn],
            ],
            manual: [
                openCodex,
                ['Add the server:', { cmd: `codex mcp add framework --url ${props.mcpUrl}` }],
                ['Sign in:', { cmd: 'codex mcp login framework' }, signIn],
            ],
        },
        claude: {
            plugin: [
                ['In Claude, open Customize, then Plugins. Select Add, then Add marketplace, and enter ', { code: marketplace }, '.'],
                ['Under Discover, add Framework Cloud.'],
                [signIn],
                allowHost,
            ],
            manual: [
                ['In Claude, open Customize, then Connectors. Select Add, then Custom connector, and paste the server address.'],
                ['Choose Sign in now and Register automatically. Leave Request headers empty.'],
                [signIn],
                allowHost,
            ],
            note: 'On Team and Enterprise plans, an owner adds custom connectors.',
        },
        chatgpt: {
            plugin: [
                ['In the ChatGPT desktop app, open Settings, then Plugins. Select Add, then Add a marketplace, and enter ', { code: marketplace }, '.'],
                ['Install Framework Cloud.'],
                [signIn],
            ],
            manual: [
                ['In the ChatGPT desktop app, open Settings, then Plugins. Select Add, then Add MCP server.'],
                ['Name it framework, choose Streamable HTTP, and paste the server address. Save.'],
                ['Select Authenticate next to framework. ', signIn],
            ],
            note: 'On chatgpt.com, open Settings, then Apps and connectors. Turn on Developer mode, then select Create.',
        },
    }[app.value];
});
</script>

<template>
    <Head :title="active ? 'Connected agents' : 'Connect your agent'" />

    <AppLayout>
        <div class="flex max-w-4xl flex-col gap-8">
            <div class="flex flex-col gap-6">
                <PageHeader :title="active ? 'Connected agents' : 'Connect your agent'">
                    <template #description>Connect Claude, ChatGPT, or another AI agent to your account. You approve each agent and choose its access.</template>
                </PageHeader>
                <AgentsTabs :connection-count="connectionCount" :history-count="historyCount" />
            </div>

            <section v-if="approvedApps.length" class="flex flex-col gap-3">
                <div>
                    <h2 class="text-base font-medium text-slate-900 dark:text-white">Approved apps</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">These apps reconnect with this access without asking. Apps on your computer always ask.</p>
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

            <section class="flex flex-col gap-4">
                <p class="text-sm text-slate-500 dark:text-slate-400">Claude Code and Codex run on your computer, so they upload files themselves.</p>
                <UiTabs v-model="app" :tabs="apps" label="AI app" />

                <SetupSteps v-if="plugin" :steps="setup.plugin" />

                <component :is="plugin ? 'details' : 'div'" class="group">
                    <summary v-if="plugin" class="flex cursor-pointer list-none items-center gap-1 text-sm font-medium text-slate-800 dark:text-slate-200 [&::-webkit-details-marker]:hidden">
                        <ChevronRightIcon class="size-4 text-slate-500 transition-transform group-open:rotate-90" aria-hidden="true" />Manual setup
                    </summary>
                    <div class="flex flex-col gap-4" :class="plugin && 'mt-4'">
                        <div class="flex w-full max-w-2xl items-center gap-2 rounded-lg bg-slate-800/5 py-1 ps-4 pe-1 dark:bg-white/10">
                            <code class="min-w-0 flex-1 truncate font-mono text-sm text-slate-900 dark:text-white">{{ mcpUrl }}</code>
                            <UiButton variant="ghost" size="sm" class="shrink-0" @click="copy">
                                <component :is="copied ? CheckIcon : ClipboardDocumentIcon" class="size-4 text-slate-500 dark:text-slate-400" aria-hidden="true" /><template v-if="copied">Copied</template><template v-else>Copy<span class="hidden sm:inline">&nbsp;address</span></template>
                            </UiButton>
                        </div>
                        <SetupSteps :steps="setup.manual" />
                    </div>
                </component>

                <p v-if="setup.note" class="text-sm text-slate-500 dark:text-slate-400">{{ setup.note }}</p>
            </section>

            <section class="flex flex-col gap-3">
                <h2 class="text-base font-medium text-slate-900 dark:text-white">Access levels</h2>
                <dl class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-[8rem_1fr]">
                    <dt class="font-medium text-slate-800 dark:text-slate-200">Full access</dt>
                    <dd class="text-slate-500 dark:text-slate-400">Recommended. Creates and changes projects, blueprints, artifacts, reports, and skills. Deletions go to the Trash.</dd>
                    <dt class="font-medium text-slate-800 dark:text-slate-200">Read only</dt>
                    <dd class="text-slate-500 dark:text-slate-400">Reads projects, artifacts, reports, skills, and blueprints.</dd>
                </dl>
                <p class="text-sm text-slate-500 dark:text-slate-400">Agents publish files up to 50 MB. What an agent reads goes to the company that runs it.</p>
            </section>
        </div>
    </AppLayout>
</template>
