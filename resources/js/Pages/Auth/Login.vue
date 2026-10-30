<script setup>
import { ref, computed } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import UiButton from '@/Components/UiButton.vue';
import UiInput from '@/Components/UiInput.vue';
import UiCallout from '@/Components/UiCallout.vue';
import GitHubButton from '@/Components/GitHubButton.vue';

const page = usePage();
const sent = computed(() => page.props.flash.status === 'magic-link-sent');
const passwordReset = computed(() => page.props.flash.status === 'password-reset');
const passwords = computed(() => page.props.passwords);

const form = useForm({ email: '', password: '' });

// A sign-in link is the default. People who use a password can switch, and
// this browser remembers the choice.
const MODE_KEY = 'sign-in-with-password';
const remembered = () => { try { return localStorage.getItem(MODE_KEY) === '1'; } catch { return false; } };
const usePassword = ref(passwords.value && remembered());
const setUsePassword = (value) => {
    usePassword.value = value;
    form.clearErrors();
    try { value ? localStorage.setItem(MODE_KEY, '1') : localStorage.removeItem(MODE_KEY); } catch { /* storage unavailable */ }
};

const description = computed(() => (usePassword.value
    ? 'Sign in with your email address and password.'
    : "We'll email you a link to sign in."));
const send = () => form.transform((data) => ({ email: data.email })).post('/auth/magic-link');
const signIn = () => form.transform((data) => data).post('/login/password', { onFinish: () => form.reset('password') });

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

    <AuthLayout title="Sign in" :description="description">
        <UiCallout v-if="passwordReset" variant="success" heading="Password changed">
            Sign in with your new password.
        </UiCallout>

        <UiCallout v-if="sent" variant="success" heading="Check your email">
            If an account exists for that address, a sign-in link is on its way.
            It works once and expires in 15 minutes.
        </UiCallout>

        <form class="flex flex-col gap-4" @submit.prevent="usePassword ? signIn() : send()">
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
            <div v-if="usePassword" class="flex flex-col gap-1.5">
                <UiInput
                    v-model="form.password"
                    label="Password"
                    type="password"
                    autocomplete="current-password"
                    required
                    :error="form.errors.password"
                />
                <Link href="/forgot-password" class="self-end text-xs text-sky-700 hover:underline dark:text-sky-400">
                    Forgot your password?
                </Link>
            </div>
            <UiButton type="submit" class="w-full" :disabled="form.processing">
                {{ usePassword ? 'Sign in' : 'Email me a sign-in link' }}
            </UiButton>
            <button
                v-if="passwords"
                type="button"
                class="self-center text-sm text-sky-700 hover:underline dark:text-sky-400"
                @click="setUsePassword(!usePassword)"
            >
                {{ usePassword ? 'Email me a sign-in link instead' : 'Use a password instead' }}
            </button>
        </form>

        <template v-if="passkeySupported || page.props.githubSignIn">
            <div class="flex items-center gap-3 text-xs text-slate-400">
                <div class="h-px flex-1 bg-slate-200 dark:bg-slate-700" />
                or
                <div class="h-px flex-1 bg-slate-200 dark:bg-slate-700" />
            </div>

            <div class="flex flex-col gap-3">
                <GitHubButton v-if="page.props.githubSignIn" label="Sign in with GitHub" />

                <template v-if="passkeySupported">
                    <form ref="assertionForm" method="post" action="/passkeys/authenticate" class="hidden">
                        <input type="hidden" name="_token" :value="page.props.csrf">
                        <input ref="assertionInput" type="hidden" name="start_authentication_response">
                        <input type="hidden" name="remember" value="1">
                    </form>

                    <p v-if="passkeyError" class="text-sm text-red-600 dark:text-red-400">{{ passkeyError }}</p>

                    <UiButton class="w-full" variant="outline" :disabled="passkeyBusy" @click="signInWithPasskey">
                        {{ passkeyBusy ? 'Waiting for your device…' : 'Sign in with a passkey' }}
                    </UiButton>
                </template>
            </div>
        </template>

        <p class="text-center text-sm text-slate-500 dark:text-slate-400">
            Don't have an account?
            <Link href="/register" class="text-sky-700 hover:underline dark:text-sky-400">Sign up</Link>
        </p>
    </AuthLayout>
</template>
