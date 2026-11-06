<script setup>
import { ask } from '@/confirm.js';
import { computed } from 'vue';
import { useForm, usePage, router } from '@inertiajs/vue3';
import SectionLayout from '@/Layouts/SectionLayout.vue';
import { accountItems } from '@/Layouts/AccountLayout.vue';
import UiButton from '@/Components/UiButton.vue';
import UiInput from '@/Components/UiInput.vue';
import UiCallout from '@/Components/UiCallout.vue';
import UiCard from '@/Components/UiCard.vue';
import UiPanel from '@/Components/UiPanel.vue';
import EmptyState from '@/Components/EmptyState.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { ComputerDesktopIcon, KeyIcon, SparklesIcon } from '@heroicons/vue/24/outline';
import { usePythonServerArg, useServerArg } from '@/cloud.js';

defineProps({ tokens: Array, connections: { type: Array, default: () => [] }, mcpUrl: String });

const page = usePage();
const serverArg = useServerArg();
const pythonServerArg = usePythonServerArg();
const plainToken = computed(() =>
    page.props.flash.status === 'user-token' ? page.props.flash.token : null,
);

const form = useForm({ name: '' });
const create = () => form.post('/tokens', { onSuccess: () => form.reset() });

const disconnect = async (connection) => {
    if (await ask(`Disconnect ${connection.name}? It will lose access to your account.`)) {
        router.delete(`/tokens/apps/${connection.id}`);
    }
};

const revoke = async (id) => {
    if (await ask('Revoke this token? Machines using it will lose access.')) {
        router.delete(`/tokens/${id}`);
    }
};
</script>

<template>
    <Head title="API tokens" />

    <SectionLayout heading="Account" :items="accountItems">
        <div class="flex flex-col gap-8">
            <PageHeader title="API tokens">
                <template #description>
                    Tokens connect R, Python, Stata, and the CLI to your account.
                    Revoke each device’s token separately.
                    <code class="font-mono text-xs">cloud_login()</code> in R or Python, <code class="font-mono text-xs">fw_login</code> in Stata, and <code class="font-mono text-xs">fw login</code> create a token for you after you approve the computer in a browser. The browser can be on any device, so this also works on a server or a cluster. Create a token here for scripts and jobs that can't wait for approval.
                </template>
            </PageHeader>

            <UiPanel title="New token" description="A device name, such as work-laptop.">
                <form class="flex flex-wrap items-end gap-3" @submit.prevent="create">
                    <div class="w-full max-w-xs"><UiInput v-model="form.name" label="Token name" placeholder="e.g. work-laptop" :error="form.errors.name" /></div>
                    <UiButton type="submit" :disabled="form.processing">Create token</UiButton>
                </form>
            </UiPanel>

            <UiCallout v-if="plainToken" variant="success" heading="New token">
                <p>Copy this token now. It is shown only once.</p>
                <code class="break-all font-mono text-sm">{{ plainToken }}</code>
                <div>
                    <div class="text-slate-500">Connect this machine. In R:</div>
                    <code class="break-all font-mono text-xs">framework::cloud_login("{{ plainToken }}"{{ serverArg }})</code>
                </div>
                <div>
                    <div class="text-slate-500">In Python:</div>
                    <code class="break-all font-mono text-xs">framework.cloud_login("{{ plainToken }}"{{ pythonServerArg }})</code>
                </div>
                <div>
                    <div class="text-slate-500">In a terminal, for fw and Stata, which share fw's login. fw asks for the token:</div>
                    <code class="break-all font-mono text-xs">fw login --paste{{ serverFlag }}</code>
                </div>
                <p class="text-xs text-slate-500">
                    Stored in your system keychain (macOS Keychain, Windows Credential Manager, Linux Secret Service).
                    In a batch job, set <code class="font-mono">FW_CLOUD_TOKEN</code> instead.
                </p>
            </UiCallout>

            <section class="flex flex-col gap-4">
                <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Tokens</h2>
                <div v-if="tokens.length" class="flex flex-col gap-3">
                    <UiCard v-for="token in tokens" :key="token.id" hover>
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex min-w-0 items-center gap-4">
                                <div class="hidden size-10 shrink-0 items-center justify-center rounded-lg sm:flex bg-sky-50 text-sky-600 dark:bg-sky-400/10 dark:text-sky-400">
                                    <ComputerDesktopIcon class="size-5" />
                                </div>
                                <div class="min-w-0">
                                    <div class="font-semibold text-slate-900 dark:text-white">{{ token.name }}</div>
                                    <p class="text-sm text-slate-500 dark:text-slate-400">
                                        Created {{ token.created_at }} · {{ token.last_used_at ? `Last used ${token.last_used_at}` : 'Never used' }}
                                    </p>
                                </div>
                            </div>
                            <UiButton variant="danger" size="sm" class="self-start sm:self-auto" @click="revoke(token.id)">Revoke</UiButton>
                        </div>
                    </UiCard>
                </div>
                <EmptyState v-else :icon="KeyIcon" title="No tokens" description="Create a token above, then run cloud_login() with it on your computer." />
            </section>

            <section class="flex flex-col gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Connected AI apps</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Claude, ChatGPT, and other AI apps connect with the server address
                        <code class="font-mono text-xs text-slate-800 dark:text-slate-200">{{ mcpUrl }}</code>.
                        You approve each app and choose what it may do.
                    </p>
                </div>
                <div v-if="connections.length" class="flex flex-col gap-3">
                    <UiCard v-for="connection in connections" :key="connection.id" hover>
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex min-w-0 items-center gap-4">
                                <div class="hidden size-10 shrink-0 items-center justify-center rounded-lg sm:flex bg-sky-50 text-sky-600 dark:bg-sky-400/10 dark:text-sky-400">
                                    <SparklesIcon class="size-5" />
                                </div>
                                <div class="min-w-0">
                                    <div class="font-semibold text-slate-900 dark:text-white">{{ connection.name }}</div>
                                    <p class="text-sm text-slate-500 dark:text-slate-400">
                                        {{ connection.access }} · Connected {{ connection.created_at }} · {{ connection.last_used_at ? `Last used ${connection.last_used_at}` : 'Never used' }}
                                    </p>
                                </div>
                            </div>
                            <UiButton variant="danger" size="sm" class="self-start sm:self-auto" @click="disconnect(connection)">Disconnect</UiButton>
                        </div>
                    </UiCard>
                </div>
                <EmptyState v-else :icon="SparklesIcon" title="No connected apps" description="Add Framework Cloud as a connector in your AI app, then approve it here." />
            </section>
        </div>
    </SectionLayout>
</template>
