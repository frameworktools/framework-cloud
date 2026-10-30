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

const methods = computed(() => {
    const list = [page.props.githubSignIn && 'GitHub', passwords.value && 'a password', 'a passkey'].filter(Boolean);
    return `Use ${list.join(', ')}, or a link sent to your email.`;
});

const form = useForm({ email: '', password: '' });
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

    <AuthLayout
        title="Sign in"
        :description="methods"
    >
        <UiCallout v-if="passwordReset" variant="success" heading="Password changed">
            Sign in with your new password.
        </UiCallout>

        <UiCallout v-if="sent" variant="success" heading="Check your email">
            If an account exists for that address, a sign-in link is on its way.
            It works once and expires in 15 minutes.
        </UiCallout>

        <GitHubButton v-if="page.props.githubSignIn" label="Sign in with GitHub" />

        <div v-if="passkeySupported" class="flex flex-col gap-3">
            <form ref="assertionForm" method="post" action="/passkeys/authenticate" class="hidden">
                <input type="hidden" name="_token" :value="page.props.csrf">
                <input ref="assertionInput" type="hidden" name="start_authentication_response">
                <input type="hidden" name="remember" value="1">
            </form>

            <p v-if="passkeyError" class="text-sm text-red-600 dark:text-red-400">{{ passkeyError }}</p>

            <UiButton class="w-full" :variant="passwords ? 'outline' : 'primary'" :disabled="passkeyBusy" @click="signInWithPasskey">
                {{ passkeyBusy ? 'Waiting for your device…' : 'Sign in with a passkey' }}
            </UiButton>

        </div>

        <div v-if="passkeySupported || page.props.githubSignIn" class="flex items-center gap-3 text-xs text-neutral-400">
            <div class="h-px flex-1 bg-neutral-200 dark:bg-neutral-700" />
            or
            <div class="h-px flex-1 bg-neutral-200 dark:bg-neutral-700" />
        </div>

        <form class="flex flex-col gap-4" @submit.prevent="passwords ? signIn() : send()">
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
            <template v-if="passwords">
                <div class="flex flex-col gap-1.5">
                    <UiInput
                        v-model="form.password"
                        label="Password"
                        type="password"
                        autocomplete="current-password"
                        :error="form.errors.password"
                    />
                    <Link href="/forgot-password" class="self-end text-xs text-orange-600 hover:underline dark:text-orange-400">
                        Forgot your password?
                    </Link>
                </div>
                <UiButton type="submit" class="w-full" :disabled="form.processing">Sign in</UiButton>
                <UiButton variant="outline" class="w-full" :disabled="form.processing" @click="send">
                    Email me a sign-in link instead
                </UiButton>
            </template>
            <UiButton v-else type="submit" variant="outline" class="w-full" :disabled="form.processing">
                Email me a sign-in link
            </UiButton>
        </form>

        <p class="text-center text-sm text-neutral-500 dark:text-neutral-400">
            Don't have an account?
            <Link href="/register" class="text-orange-600 hover:underline dark:text-orange-400">Sign up</Link>
        </p>
    </AuthLayout>
</template>
