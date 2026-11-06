<script setup>
import { computed, ref } from 'vue';
import PageHeader from '@/Components/PageHeader.vue';
import UiButton from '@/Components/UiButton.vue';
import AgentsLayout from './AgentsLayout.vue';
import SetupSteps from './SetupSteps.vue';
import { CheckIcon, ClipboardDocumentIcon } from '@heroicons/vue/24/outline';
import { ChevronRightIcon } from '@heroicons/vue/20/solid';

const props = defineProps({ app: String, mcpUrl: String, plugin: Boolean, active: Boolean });

const copied = ref(false);
async function copy() {
    await navigator.clipboard?.writeText(props.mcpUrl);
    copied.value = true;
    setTimeout(() => (copied.value = false), 1500);
}

// The Framework Cloud plugin connects to framework.pub only; other servers show manual setup alone
const marketplace = 'frameworktools/plugins';
const host = computed(() => new URL(props.mcpUrl).host);
const signIn = 'In the browser that opens, sign in, choose the access, and select Connect.';

const setup = computed(() => {
    const allowHost = ['In Settings, open Capabilities and add ', { code: host.value }, ' to the allowed websites, exactly as shown. Claude needs it to upload files. Start a new chat afterward.'];
    const claudeCodeSignIn = ['Run ', { code: 'claude' }, ', then ', { code: '/mcp' }, '. Choose framework and authenticate. ', signIn];
    const codexSignIn = ['Sign in:', { cmd: 'codex mcp login framework' }, signIn];

    return {
        claude: {
            title: 'Claude',
            description: 'The Claude desktop app and claude.ai. Framework Cloud then works in chats and in Claude Code inside the desktop app.',
            plugin: [
                ['In the Claude desktop app or on claude.ai, open Customize, then Plugins. Select Add, then Add marketplace, and enter ', { code: marketplace }, '.'],
                ['Under Discover, add Framework Cloud.'],
                [signIn],
                allowHost,
            ],
            manual: [
                ['In the Claude desktop app or on claude.ai, open Customize, then Connectors. Select Add, then Custom connector, and paste the server address.'],
                ['Choose Sign in now and Register automatically. Leave Request headers empty.'],
                [signIn],
                allowHost,
            ],
            note: 'On Team and Enterprise plans, an owner adds custom connectors.',
        },
        chatgpt: {
            title: 'ChatGPT',
            description: 'The ChatGPT desktop app, or chatgpt.com with developer mode.',
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
        'claude-code': {
            title: 'Claude Code CLI',
            description: 'Claude Code in a terminal. It runs on your computer, so it uploads files itself.',
            plugin: [
                ['In a terminal, add the plugin:', { cmd: `claude plugin marketplace add ${marketplace}` }, { cmd: 'claude plugin install framework-cloud@framework' }],
                claudeCodeSignIn,
            ],
            manual: [
                ['In a terminal, add the server:', { cmd: `claude mcp add --transport http framework ${props.mcpUrl}` }],
                claudeCodeSignIn,
            ],
        },
        codex: {
            title: 'Codex CLI',
            description: 'Codex in a terminal. It runs on your computer, so it uploads files itself.',
            plugin: [
                ['In a terminal, add the plugin:', { cmd: `codex plugin marketplace add ${marketplace}` }, { cmd: 'codex plugin add framework-cloud@framework' }],
                codexSignIn,
            ],
            manual: [
                ['In a terminal, add the server:', { cmd: `codex mcp add framework --url ${props.mcpUrl}` }],
                codexSignIn,
            ],
        },
    }[props.app];
});
</script>

<template>
    <Head :title="`${setup.title} setup`" />

    <AgentsLayout>
        <div class="flex flex-col gap-8">
            <PageHeader :title="setup.title" :description="setup.description" />

            <section class="flex flex-col gap-5">
                <SetupSteps v-if="plugin" :steps="setup.plugin" />

                <component :is="plugin ? 'details' : 'div'" class="group">
                    <summary v-if="plugin" class="flex cursor-pointer list-none items-center gap-1 text-sm font-medium text-slate-800 dark:text-slate-200 [&::-webkit-details-marker]:hidden">
                        <ChevronRightIcon class="size-4 text-slate-500 transition-transform group-open:rotate-90" aria-hidden="true" />Manual setup
                    </summary>
                    <div class="flex flex-col gap-5" :class="plugin && 'mt-5'">
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
