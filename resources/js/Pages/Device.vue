<script setup>
import { computed, ref } from 'vue';
import { useForm, usePage, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import UiButton from '@/Components/UiButton.vue';
import UiPanel from '@/Components/UiPanel.vue';
import PageHeader from '@/Components/PageHeader.vue';
import UiCallout from '@/Components/UiCallout.vue';

const props = defineProps({ code: String, request: Object, error: String });

const page = usePage();
const result = computed(() => page.props.flash.status);

const typed = ref(props.code ?? '');
const lookup = () => router.get('/device', { code: typed.value });

const form = useForm({ code: props.code });
const approve = () => form.post('/device/approve');
const deny = () => form.post('/device/deny');

const error = computed(() => form.errors.code ?? props.error);
</script>

<template>
    <Head title="Sign in fw" />

    <AppLayout>
        <div class="flex max-w-2xl flex-col gap-8">
            <PageHeader>
                <template #title>Sign in <code class="font-mono">fw</code></template>
                <template #description>
                    <code class="font-mono">fw login</code> shows a code in your terminal. Check that it matches the
                    code here, then approve it to sign <code class="font-mono">fw</code> in to your account.
                </template>
            </PageHeader>

            <UiCallout v-if="result === 'device-approved'" variant="success" heading="fw is signed in">
                <p>You can close this tab and return to the terminal.</p>
            </UiCallout>

            <UiCallout v-else-if="result === 'device-denied'" heading="Request denied">
                <p><code class="font-mono">fw</code> was not signed in. You can close this tab.</p>
            </UiCallout>

            <UiPanel
                v-else-if="request"
                title="Approve this sign-in?"
                description="Approving creates an API token for this device. You can revoke it on the API tokens page."
            >
                <dl class="grid grid-cols-[auto_1fr] gap-x-6 gap-y-2 text-sm">
                    <dt class="text-slate-500 dark:text-slate-400">Code</dt>
                    <dd class="font-mono font-medium">{{ code }}</dd>
                    <dt class="text-slate-500 dark:text-slate-400">Device</dt>
                    <dd>{{ request.client_name ?? 'fw (no name given)' }}</dd>
                    <dt class="text-slate-500 dark:text-slate-400">Requested</dt>
                    <dd><time :datetime="request.created_at_iso">{{ request.created_at }}</time></dd>
                </dl>

                <p v-if="error" class="text-sm text-red-600 dark:text-red-400">{{ error }}</p>

                <template #actions>
                    <UiButton variant="outline" :disabled="form.processing" @click="deny">Deny</UiButton>
                    <UiButton :disabled="form.processing" @click="approve">Approve</UiButton>
                </template>
            </UiPanel>

            <UiPanel v-else title="Enter the code from your terminal">
                <form class="flex flex-col gap-2" @submit.prevent="lookup">
                    <label for="device-code" class="text-sm font-medium text-slate-800 dark:text-slate-200">Code</label>
                    <div class="flex gap-3">
                        <input
                            id="device-code" v-model="typed" placeholder="ABCD-EFGH" autocomplete="off" autofocus
                            class="w-56 rounded-md border bg-white px-3 py-2 font-mono text-sm uppercase tracking-wider text-slate-900 placeholder-slate-400 focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 dark:bg-slate-900 dark:text-slate-100"
                            :class="error ? 'border-red-500' : 'border-slate-300 dark:border-slate-600'"
                        >
                        <UiButton type="submit" :disabled="!typed">Continue</UiButton>
                    </div>
                    <p v-if="error" class="text-sm text-red-600 dark:text-red-400">{{ error }}</p>
                </form>
            </UiPanel>
        </div>
    </AppLayout>
</template>
