<script setup>
import { computed, ref } from 'vue';
import PageHeader from '@/Components/PageHeader.vue';
import UiTabs from '@/Components/UiTabs.vue';
import AgentsLayout from './AgentsLayout.vue';
import SetupSteps from './SetupSteps.vue';

const props = defineProps({ mcpUrl: String, plugin: Boolean, active: Boolean });

const app = ref('claude-code');
const apps = [
    { value: 'claude-code', label: 'Claude Code' },
    { value: 'codex', label: 'Codex' },
    { value: 'claude', label: 'Claude' },
    { value: 'chatgpt', label: 'ChatGPT' },
];

// The Framework Cloud plugin connects to framework.pub only; other servers show manual setup alone
const marketplace = 'frameworktools/plugins';
const host = computed(() => new URL(props.mcpUrl).host);
const signIn = 'In the browser that opens, sign in, choose the access, and select Connect.';

/*
 * Each tab: how to switch to that app inside its desktop app, then one or more
 * sections (the app, the command line), each with plugin and manual steps.
 */
const setup = computed(() => {
    const url = props.mcpUrl;
    const allowHost = ['In Settings, open Capabilities and add this to the allowed websites, exactly as shown. Claude needs it to upload files.', { copy: host.value }, 'Start a new chat afterward.'];
    const claudePlugin = [
        ['Open Customize, then Plugins. Select Add, then Add marketplace, and enter:', { copy: marketplace }],
        ['Under Discover, add Framework Cloud.'],
        [signIn],
    ];
    const claudeConnector = [
        ['Open Customize, then Connectors. Select Add, then Custom connector, and paste the server address:', { copy: url }],
        ['Choose Sign in now and Register automatically. Leave Request headers empty.'],
        [signIn],
    ];
    const chatgptPlugin = [
        ['Open Settings, then Plugins. Select Add, then Add a marketplace, and enter:', { copy: marketplace }],
        ['Install Framework Cloud.'],
        [signIn],
    ];
    const chatgptServer = [
        ['Open Settings, then Plugins. Select Add, then Add MCP server.'],
        ['Name it framework, choose Streamable HTTP, and paste the server address:', { copy: url }, 'Save.'],
        ['Select Authenticate next to framework. ', signIn],
    ];
    const claudeCodeSignIn = ['Run ', { code: 'claude' }, ', then ', { code: '/mcp' }, '. Choose framework and authenticate. ', signIn];
    const codexSignIn = ['Sign in:', { cmd: 'codex mcp login framework' }, signIn];

    return {
        'claude-code': {
            toggle: ['To switch to Claude Code in the Claude desktop app, select ', { code: '</>' }, ' at the top of the window.'],
            sections: [
                { title: 'Connect with the Claude Code app', plugin: claudePlugin, manual: claudeConnector },
                {
                    title: 'Connect with the Claude Code CLI',
                    plugin: [['In a terminal, add the plugin:', { cmd: `claude plugin marketplace add ${marketplace}` }, { cmd: 'claude plugin install framework-cloud@framework' }], claudeCodeSignIn],
                    manual: [['In a terminal, add the server:', { cmd: `claude mcp add --transport http framework ${url}` }], claudeCodeSignIn],
                },
            ],
            note: 'Claude Code runs on your computer, so it uploads files itself.',
        },
        codex: {
            toggle: ['To switch to Codex in the ChatGPT desktop app, open the ChatGPT menu at the top left and choose Codex.'],
            sections: [
                { title: 'Connect with the Codex app', plugin: chatgptPlugin, manual: chatgptServer },
                {
                    title: 'Connect with the Codex CLI',
                    plugin: [['In a terminal, add the plugin:', { cmd: `codex plugin marketplace add ${marketplace}` }, { cmd: 'codex plugin add framework-cloud@framework' }], codexSignIn],
                    manual: [['In a terminal, add the server:', { cmd: `codex mcp add framework --url ${url}` }], codexSignIn],
                },
            ],
            note: 'Codex runs on your computer, so it uploads files itself.',
        },
        claude: {
            toggle: ['To switch to chat in the Claude desktop app, select the chat button at the top of the window. On claude.ai, chat is the default.'],
            sections: [{ plugin: [...claudePlugin, allowHost], manual: [...claudeConnector, allowHost] }],
            note: 'On Team and Enterprise plans, an owner adds custom connectors.',
        },
        chatgpt: {
            toggle: ['To switch to chat in the ChatGPT desktop app, open the menu at the top left and choose ChatGPT.'],
            sections: [{ plugin: chatgptPlugin, manual: chatgptServer }],
            note: 'On chatgpt.com, open Settings, then Apps and connectors. Turn on Developer mode, then select Create.',
        },
    }[app.value];
});

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
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    <template v-for="(part, i) in setup.toggle" :key="i"><template v-if="typeof part === 'string'">{{ part }}</template><code v-else class="font-mono text-xs">{{ part.code }}</code></template>
                </p>

                <div v-for="(section, i) in setup.sections" :key="section.title ?? 'app'" class="flex flex-col gap-5" :class="i && 'border-t border-slate-800/10 pt-6 dark:border-white/15'">
                    <h3 v-if="section.title" class="text-base font-medium text-slate-900 dark:text-white">{{ section.title }}</h3>
                    <template v-if="plugin">
                        <h4 :class="label">Plugin</h4>
                        <SetupSteps :steps="section.plugin" />
                    </template>
                    <h4 :class="[label, plugin && 'mt-3']">Manual setup</h4>
                    <SetupSteps :steps="section.manual" />
                </div>

                <p v-if="setup.note" class="text-sm text-slate-500 dark:text-slate-400">{{ setup.note }}</p>
            </section>

            <section class="flex flex-col gap-3 border-t border-slate-800/10 pt-8 dark:border-white/15">
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
    </AgentsLayout>
</template>
