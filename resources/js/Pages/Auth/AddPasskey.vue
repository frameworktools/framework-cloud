<script setup>
import { ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import UiButton from '@/Components/UiButton.vue';

defineProps({ email: String });

const page = usePage();
const supported = ref(window.passkeys?.supported() ?? false);
const busy = ref(false);
const error = ref('');

// Name the passkey after the device, so Settings > Passkeys can tell them apart
function deviceName() {
    const ua = navigator.userAgent;
    if (/iPhone/.test(ua)) return 'iPhone';
    if (/iPad/.test(ua)) return 'iPad';
    if (/Android/.test(ua)) return 'Android';
    if (/Mac/.test(ua)) return 'Mac';
    if (/Windows/.test(ua)) return 'Windows';
    return 'Passkey';
}

async function addPasskey() {
    error.value = '';
    busy.value = true;
    try {
        const response = await fetch('/settings/passkeys/options', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': page.props.csrf,
            },
            credentials: 'same-origin',
        });
        if (!response.ok) throw new Error('Could not start creating a passkey.');
        const attestation = await window.passkeys.createWithOptions(await response.json());
        router.post('/settings/passkeys', { passkey: attestation, name: deviceName(), continue: true }, {
            onError: (errors) => { error.value = errors.passkey ?? 'That passkey could not be saved. Please try again.'; },
            onFinish: () => { busy.value = false; },
        });
    } catch (e) {
        busy.value = false;
        if (!window.passkeys.isCancellation(e)) {
            error.value = e.message;
        }
    }
}

const skip = () => router.post('/welcome/passkey/skip');
</script>

<template>
    <Head title="Add a passkey" />

    <AuthLayout
        title="Sign in faster next time"
        :description="`You're signed in as ${email}. Save a passkey on this device to sign in with Touch ID, Face ID, or a security key instead of waiting for an email.`"
    >
        <template v-if="supported">
            <p v-if="error" class="text-sm text-red-600 dark:text-red-400">{{ error }}</p>

            <UiButton class="w-full" :disabled="busy" @click="addPasskey">
                {{ busy ? 'Waiting for your device…' : 'Save a passkey' }}
            </UiButton>
            <button
                type="button"
                class="self-center text-sm text-sky-700 hover:underline dark:text-sky-400"
                :disabled="busy"
                @click="skip"
            >
                Not now
            </button>
        </template>

        <template v-else>
            <p class="text-center text-sm text-slate-500 dark:text-slate-400">This browser does not support passkeys.</p>
            <UiButton class="w-full" @click="skip">Continue</UiButton>
        </template>

        <p class="text-center text-xs text-slate-500 dark:text-slate-400">
            You can add a passkey for each device you use in Settings &gt; Passkeys.
        </p>
    </AuthLayout>
</template>
