<script setup>
import { ref, computed } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import UiButton from '@/Components/UiButton.vue';
import UiInput from '@/Components/UiInput.vue';
import UiCallout from '@/Components/UiCallout.vue';

const page = usePage();
const sent = computed(() => page.props.flash.status === 'magic-link-sent');

const form = useForm({ email: '' });
const send = () => form.post('/auth/magic-link');

// Passkey sign-in: fetch assertion options, hand to the authenticator,
// post the result as a plain form (the passkeys package expects a
// classic POST, not an Inertia visit).
const passkeySupported = ref(window.passkeys?.supported() ?? false);
const passkeyBusy = ref(false);
const passkeyError = ref('');
const assertionForm = ref(null);
const assertionInput = ref(null);

async function signInWithPasskey() {
    passkeyError.value = '';
    passkeyBusy.value = true;
    try {
        assertionInput.value.value = await window.passkeys.authenticate({
            optionsUrl: '/passkeys/authentication-options',
        });
        assertionForm.value.submit();
    } catch (error) {
        passkeyBusy.value = false;
        if (!window.passkeys.isCancellation(error)) {
            passkeyError.value = error.message;
        }
    }
}
</script>

<template>
    <Head title="Sign in" />

    <AuthLayout title="Sign in" description="Use a passkey, or we'll email you a sign-in link. No passwords here.">
        <UiCallout v-if="sent" variant="success" heading="Check your email">
            If an account exists for that address, a sign-in link is on its way.
            It works once and expires in 15 minutes.
        </UiCallout>

        <div v-if="passkeySupported" class="flex flex-col gap-3">
            <form ref="assertionForm" method="post" action="/passkeys/authenticate" class="hidden">
                <input type="hidden" name="_token" :value="page.props.csrf">
                <input ref="assertionInput" type="hidden" name="start_authentication_response">
                <input type="hidden" name="remember" value="1">
            </form>

            <p v-if="passkeyError" class="text-sm text-red-600 dark:text-red-400">{{ passkeyError }}</p>

            <UiButton class="w-full" :disabled="passkeyBusy" @click="signInWithPasskey">
                {{ passkeyBusy ? 'Waiting for your device…' : 'Sign in with a passkey' }}
            </UiButton>

            <div class="flex items-center gap-3 text-xs text-neutral-400">
                <div class="h-px flex-1 bg-neutral-200 dark:bg-neutral-700" />
                or
                <div class="h-px flex-1 bg-neutral-200 dark:bg-neutral-700" />
            </div>
        </div>

        <form class="flex flex-col gap-4" @submit.prevent="send">
            <UiInput
                v-model="form.email"
                label="Email address"
                type="email"
                placeholder="you@university.edu"
                autocomplete="email"
                required
                autofocus
                :error="form.errors.email"
            />
            <UiButton type="submit" variant="outline" class="w-full" :disabled="form.processing">
                Email me a sign-in link
            </UiButton>
        </form>

        <p class="text-center text-sm text-neutral-500 dark:text-neutral-400">
            Don't have an account?
            <Link href="/register" class="text-orange-600 hover:underline dark:text-orange-400">Sign up</Link>
        </p>
    </AuthLayout>
</template>
