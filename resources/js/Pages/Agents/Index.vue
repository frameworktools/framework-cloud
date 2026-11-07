<script setup>
import { computed, ref } from 'vue';
import PageHeader from '@/Components/PageHeader.vue';
import UiTabs from '@/Components/UiTabs.vue';
import AgentsLayout from './AgentsLayout.vue';
import SetupSteps from './SetupSteps.vue';

const props = defineProps({ mcpUrl: String, plugin: Boolean, active: Boolean });

const app = ref('claude');
const apps = [
    { value: 'claude', label: 'Claude' },
    { value: 'chatgpt', label: 'ChatGPT' },
];

// The Framework Cloud plugin connects to framework.pub only; other servers skip its section
const marketplace = 'frameworktools/plugins';
const signIn = 'In the browser that opens, sign in, choose the access, and select Connect.';

/*
 * Each tab: the desktop app (connect the MCP server, configure the plugin, then
 * use it in the coding app), then the command line.
 */
const setup = computed(() => {
    const url = props.mcpUrl;
    const tabs = {
        claude: {
            app: [
                {
                    title: 'Connect the MCP server',
                    steps: [
                        ['In the Claude desktop app, open Customize, then Connectors. Select Add, then Custom connector, and paste the server address:', { copy: url }],
                        ['Choose Sign in now and Register automatically. Leave Request headers empty.'],
                        [signIn],
                    ],
                },
                {
                    title: 'Configure the Framework plugin',
                    plugin: true,
                    steps: [
                        ['Open Customize, then Plugins. Select Add, then Add marketplace, then Add from a repository, and enter:', { copy: marketplace }],
                        ['Under Discover, add Framework Cloud.'],
                        ['Select Manage next to Framework Cloud and open Connectors. If framework is not connected, select Connect, then Continue.'],
                    ],
                },
            ],
            use: ['Framework Cloud then works in Claude Code. To switch to Claude Code, select ', { code: '</>' }, ' at the top of the Claude desktop app.'],
            cli: [
                props.plugin
                    ? ['Add the plugin:', { cmd: `claude plugin marketplace add ${marketplace}` }, { cmd: 'claude plugin install framework-cloud@framework' }, 'Or add the server alone:', { cmd: `claude mcp add --transport http framework ${url}` }]
                    : ['Add the server:', { cmd: `claude mcp add --transport http framework ${url}` }],
                ['Run ', { code: 'claude' }, ', then ', { code: '/mcp' }, '. Choose framework and authenticate. ', signIn],
            ],
        },
        chatgpt: {
            app: [
                {
                    title: 'Connect the MCP server',
                    steps: [
                        ['In the ChatGPT desktop app, open Settings, then Plugins, and choose the MCPs tab. Select Add to add a custom MCP server.'],
                        ['Name it framework and select Streamable HTTP. Paste the server address as the URL:', { copy: url }, 'Leave the rest empty and save it.'],
                        // The plugin asks people to sign in when they first use it
                        ...(props.plugin ? [] : [['Select Authenticate next to framework. ', signIn]]),
                    ],
                },
                {
                    title: 'Configure the Framework plugin',
                    plugin: true,
                    steps: [
                        ['On the same Plugins page, select Add to add a marketplace, and enter:', { copy: marketplace }],
                        ['Search for Framework and install Framework Cloud.'],
                        ['The first time you use it, Framework Cloud asks you to sign in. ', signIn],
                    ],
                },
            ],
            use: ['Framework Cloud then works in Codex. To switch to Codex, open the ChatGPT menu at the top left and choose Codex.'],
            cli: [
                props.plugin
                    ? ['Add the plugin:', { cmd: `codex plugin marketplace add ${marketplace}` }, { cmd: 'codex plugin add framework-cloud@framework' }, 'Or add the server alone:', { cmd: `codex mcp add framework --url ${url}` }]
                    : ['Add the server:', { cmd: `codex mcp add framework --url ${url}` }],
                ['Sign in:', { cmd: 'codex mcp login framework' }, signIn],
            ],
        },
    };
    const tab = tabs[app.value];

    return { ...tab, app: tab.app.filter((part) => !part.plugin || props.plugin) };
});

const heading = 'text-base font-medium text-slate-900 dark:text-white';
const label = 'text-sm font-semibold text-slate-900 dark:text-white';
</script>

<template>
    <Head :title="active ? 'Connected agents' : 'Connect your agent'" />

    <AgentsLayout>
        <div class="flex flex-col gap-8">
            <PageHeader :title="active ? 'Connected agents' : 'Connect your agent'" description="Connect Claude, ChatGPT, or another AI agent to your account." />

            <section class="flex flex-col gap-6">
                <p class="text-sm text-slate-500 dark:text-slate-400">Claude Code and Codex make it easier to work with Framework Cloud and the files on your computer. The Claude desktop app includes Claude Code, and the ChatGPT desktop app includes Codex.</p>
                <UiTabs v-model="app" :tabs="apps" label="AI app" />

                <h3 :class="heading">Desktop app</h3>
                <template v-for="(part, i) in setup.app" :key="part.title">
                    <h4 :class="[label, i && 'mt-3']">{{ part.title }}</h4>
                    <SetupSteps :steps="part.steps" />
                </template>
                <p class="text-sm text-slate-700 dark:text-slate-300">
                    <template v-for="(bit, i) in setup.use" :key="i"><template v-if="typeof bit === 'string'">{{ bit }}</template><code v-else class="font-mono text-xs">{{ bit.code }}</code></template>
                </p>

                <div class="flex flex-col gap-5 border-t border-slate-800/10 pt-6 dark:border-white/15">
                    <h3 :class="heading">CLI</h3>
                    <SetupSteps :steps="setup.cli" />
                </div>
            </section>
        </div>
    </AgentsLayout>
</template>
