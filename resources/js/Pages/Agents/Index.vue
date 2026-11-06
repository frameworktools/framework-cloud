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
const host = computed(() => new URL(props.mcpUrl).host);
const signIn = 'In the browser that opens, sign in, choose the access, and select Connect.';

/*
 * Each tab walks through one company's apps in order: connect the MCP server,
 * install the plugin, use it in chat or the coding app, and the command line.
 */
const setup = computed(() => {
    const url = props.mcpUrl;
    const sections = {
        claude: [
            {
                title: 'Connect the MCP server',
                steps: [
                    ['In the Claude desktop app or on claude.ai, open Customize, then Connectors. Select Add, then Custom connector, and paste the server address:', { copy: url }],
                    ['Choose Sign in now and Register automatically. Leave Request headers empty.'],
                    [signIn],
                    ['In Settings, open Capabilities and add this to the allowed websites, exactly as shown. Claude needs it to upload files.', { copy: host.value }, 'Start a new chat afterward.'],
                ],
            },
            {
                title: 'Install the plugin',
                plugin: true,
                steps: [
                    ['Open Customize, then Plugins. Select Add, then Add marketplace, then Add from a repository, and enter:', { copy: marketplace }],
                    ['Under Discover, add Framework Cloud.'],
                    ['Select Manage next to Framework Cloud and open Connectors. If framework is not connected, select Connect, then Continue.'],
                ],
            },
            {
                title: 'Use Framework Cloud',
                steps: [
                    ['In a chat, turn on Framework Cloud from the tools menu.'],
                    ['To use it in Claude Code, select ', { code: '</>' }, ' at the top of the Claude desktop app.'],
                ],
            },
            {
                title: 'Claude Code CLI',
                steps: [
                    props.plugin
                        ? ['In a terminal, add the plugin:', { cmd: `claude plugin marketplace add ${marketplace}` }, { cmd: 'claude plugin install framework-cloud@framework' }, 'Or add the server alone:', { cmd: `claude mcp add --transport http framework ${url}` }]
                        : ['In a terminal, add the server:', { cmd: `claude mcp add --transport http framework ${url}` }],
                    ['Run ', { code: 'claude' }, ', then ', { code: '/mcp' }, '. Choose framework and authenticate. ', signIn],
                ],
            },
        ],
        chatgpt: [
            {
                title: 'Connect the MCP server',
                steps: [
                    ['In the ChatGPT desktop app, open Customize, then Plugins. Select Add, then Add custom MCP server.'],
                    ['Name it framework and select Streamable HTTP. Paste the server address as the URL:', { copy: url }, 'Leave the rest empty and select Save.'],
                    ['Select Authenticate next to framework. ', signIn],
                ],
            },
            {
                title: 'Install the plugin',
                plugin: true,
                steps: [
                    ['Open Customize, then Plugins. Select Add, then Add a marketplace, and enter:', { copy: marketplace }],
                    ['Search for Framework and install Framework Cloud.'],
                ],
            },
            {
                title: 'Use Framework Cloud',
                steps: [
                    ['To use it in Codex, open the ChatGPT menu at the top left and choose Codex.'],
                ],
            },
            {
                title: 'Codex CLI',
                steps: [
                    props.plugin
                        ? ['In a terminal, add the plugin:', { cmd: `codex plugin marketplace add ${marketplace}` }, { cmd: 'codex plugin add framework-cloud@framework' }, 'Or add the server alone:', { cmd: `codex mcp add framework --url ${url}` }]
                        : ['In a terminal, add the server:', { cmd: `codex mcp add framework --url ${url}` }],
                    ['Sign in:', { cmd: 'codex mcp login framework' }, signIn],
                ],
            },
        ],
    }[app.value];

    return sections.filter((section) => !section.plugin || props.plugin);
});

const notes = {
    claude: 'On Team and Enterprise plans, an owner adds custom connectors.',
    chatgpt: 'On chatgpt.com, open Settings, then Apps and connectors. Turn on Developer mode, then select Create.',
};
</script>

<template>
    <Head :title="active ? 'Connected agents' : 'Connect your agent'" />

    <AgentsLayout>
        <div class="flex flex-col gap-8">
            <PageHeader :title="active ? 'Connected agents' : 'Connect your agent'" description="Connect Claude, ChatGPT, or another AI agent to your account." />

            <section class="flex flex-col gap-6">
                <p class="text-sm text-slate-500 dark:text-slate-400">Claude Code and Codex make it easier to work with Framework Cloud and the files on your computer. The Claude desktop app includes Claude Code, and the ChatGPT desktop app includes Codex.</p>
                <UiTabs v-model="app" :tabs="apps" label="AI app" />

                <div v-for="(section, i) in setup" :key="section.title" class="flex flex-col gap-5" :class="i && 'border-t border-slate-800/10 pt-6 dark:border-white/15'">
                    <h3 class="text-base font-medium text-slate-900 dark:text-white">{{ section.title }}</h3>
                    <SetupSteps :steps="section.steps" />
                </div>

                <p class="text-sm text-slate-500 dark:text-slate-400">{{ notes[app] }}</p>
            </section>
        </div>
    </AgentsLayout>
</template>
