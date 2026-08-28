<script setup>
import { computed } from 'vue';
import { useForm, usePage, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import UiButton from '@/Components/UiButton.vue';
import UiInput from '@/Components/UiInput.vue';
import UiCallout from '@/Components/UiCallout.vue';

defineProps({ tokens: Array });

const page = usePage();
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
        <div class="flex flex-col gap-6">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">API tokens</h1>
                <p class="mt-1 text-neutral-600 dark:text-neutral-400">
                    A token lets the framework R package read and sync your settings.
                    Create one per machine so you can revoke them individually.
                </p>
            </div>

            <form class="flex items-end gap-3" @submit.prevent="create">
                <div class="w-64"><UiInput v-model="form.name" label="Token name" placeholder="e.g. work-laptop" :error="form.errors.name" /></div>
                <UiButton type="submit" :disabled="form.processing">Create token</UiButton>
            </form>

            <UiCallout v-if="plainToken" variant="success" heading="Copy your token now — it won't be shown again">
                <code class="break-all font-mono text-sm">{{ plainToken }}</code>
                <div>
                    <div class="text-neutral-500">Connect this machine — in R:</div>
                    <code class="break-all font-mono text-xs">framework::cloud_login("{{ plainToken }}")</code>
                </div>
                <p class="text-xs text-neutral-500">
                    Stored in your system keychain (macOS Keychain, Windows Credential Manager, Linux Secret Service).
                </p>
            </UiCallout>

            <div class="flex flex-col divide-y divide-neutral-200 dark:divide-neutral-700">
                <div v-for="token in tokens" :key="token.id" class="flex items-center justify-between gap-4 py-3">
                    <div>
                        <div class="font-medium">{{ token.name }}</div>
                        <p class="text-sm text-neutral-500 dark:text-neutral-400">
                            Created {{ token.created_at }} · {{ token.last_used_at ? `Last used ${token.last_used_at}` : 'Never used' }}
                        </p>
                    </div>
                    <UiButton variant="danger" size="sm" @click="revoke(token.id)">Revoke</UiButton>
                </div>
                <p v-if="!tokens.length" class="py-3 text-sm text-neutral-500">No tokens yet.</p>
            </div>
        </div>
    </AppLayout>
</template>
