<script setup>
import { computed } from 'vue';
import { useForm, usePage, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import UiButton from '@/Components/UiButton.vue';
import UiInput from '@/Components/UiInput.vue';
import UiCallout from '@/Components/UiCallout.vue';
import UiCard from '@/Components/UiCard.vue';
import UiPanel from '@/Components/UiPanel.vue';
import EmptyState from '@/Components/EmptyState.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { ComputerDesktopIcon, KeyIcon } from '@heroicons/vue/24/outline';
import { usePythonServerArg, useServerArg } from '@/cloud.js';

defineProps({ tokens: Array });

const page = usePage();
const serverArg = useServerArg();
const pythonServerArg = usePythonServerArg();
const plainToken = computed(() =>
    page.props.flash.status === 'user-token' ? page.props.flash.token : null,
);

const form = useForm({ name: '' });
const create = () => form.post('/tokens', { onSuccess: () => form.reset() });

const revoke = (id) => {
    if (confirm('Revoke this token? Machines using it will lose access.')) {
        router.delete(`/tokens/${id}`);
    }
};
</script>

<template>
    <Head title="API tokens" />

    <AppLayout>
        <div class="flex flex-col gap-8">
            <PageHeader title="API tokens">
                <template #description>
                    A token lets the framework R package read and sync your settings.
                    Create one per machine so you can revoke them individually.
                </template>
            </PageHeader>

            <UiPanel title="Create a token" description="Name it after the computer it's for.">
                <form class="flex flex-wrap items-end gap-3" @submit.prevent="create">
                    <div class="w-full max-w-xs"><UiInput v-model="form.name" label="Token name" placeholder="e.g. work-laptop" :error="form.errors.name" /></div>
                    <UiButton type="submit" :disabled="form.processing">Create token</UiButton>
                </form>
            </UiPanel>

            <UiCallout v-if="plainToken" variant="success" heading="Copy your token now — it won't be shown again">
                <code class="break-all font-mono text-sm">{{ plainToken }}</code>
                <div>
                    <div class="text-slate-500">Connect this machine. In R:</div>
                    <code class="break-all font-mono text-xs">framework::cloud_login("{{ plainToken }}"{{ serverArg }})</code>
                </div>
                <div>
                    <div class="text-slate-500">In Python:</div>
                    <code class="break-all font-mono text-xs">framework.cloud_login("{{ plainToken }}"{{ pythonServerArg }})</code>
                </div>
                <p class="text-xs text-slate-500">
                    Stored in your system keychain (macOS Keychain, Windows Credential Manager, Linux Secret Service).
                </p>
            </UiCallout>

            <section class="flex flex-col gap-4">
                <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Your tokens</h2>
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
                <EmptyState v-else :icon="KeyIcon" title="No tokens yet." description="Create a token above, then run cloud_login() with it on your computer." />
            </section>
        </div>
    </AppLayout>
</template>
