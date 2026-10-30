<script setup>
import { computed, ref } from 'vue';
import { useForm, usePage, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import UiButton from '@/Components/UiButton.vue';
import UiInput from '@/Components/UiInput.vue';
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
        <div class="flex max-w-xl flex-col gap-6">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Sign in fw</h1>
                <p class="mt-1 text-slate-600 dark:text-slate-400">
                    fw login shows a code in your terminal. Enter it here to sign
                    fw in to your account.
                </p>
            </div>

            <UiCallout v-if="result === 'device-approved'" variant="success" heading="fw is signed in">
                <p>fw is signed in. You can close this tab and return to the terminal.</p>
            </UiCallout>

            <UiCallout v-else-if="result === 'device-denied'" heading="Request denied">
                <p>fw was not signed in. You can close this tab.</p>
            </UiCallout>

            <template v-else-if="request">
                <UiCallout heading="Approve this sign-in?">
                    <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1">
                        <dt class="text-slate-500">Code</dt>
                        <dd class="font-mono">{{ code }}</dd>
                        <dt class="text-slate-500">Device</dt>
                        <dd>{{ request.client_name ?? 'fw (no name given)' }}</dd>
                        <dt class="text-slate-500">Requested</dt>
                        <dd><time :datetime="request.created_at_iso">{{ request.created_at }}</time></dd>
                    </dl>
                    <p>
                        Approving creates an API token for this device. You can revoke
                        it on the API tokens page.
                    </p>
                </UiCallout>

                <p v-if="error" class="text-sm text-red-600 dark:text-red-400">{{ error }}</p>

                <div class="flex gap-3">
                    <UiButton :disabled="form.processing" @click="approve">Approve</UiButton>
                    <UiButton variant="outline" :disabled="form.processing" @click="deny">Deny</UiButton>
                </div>
            </template>

            <form v-else class="flex items-end gap-3" @submit.prevent="lookup">
                <div class="w-64">
                    <UiInput
                        v-model="typed" label="Code" placeholder="ABCD-EFGH"
                        autocomplete="off" autofocus :error="error"
                    />
                </div>
                <UiButton type="submit" :disabled="!typed">Continue</UiButton>
            </form>
        </div>
    </AppLayout>
</template>
