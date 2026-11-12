<script setup>
import SharedComputer from '@/Components/SharedComputer.vue';
// Sets window.passkeys; only the passkey pages load it
import '@/passkeys.js';
import { ref, computed, nextTick, onMounted } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import UiButton from '@/Components/UiButton.vue';
import UiInput from '@/Components/UiInput.vue';
import UiCallout from '@/Components/UiCallout.vue';
import GitHubButton from '@/Components/GitHubButton.vue';
import { inputClass } from '@/ui.js';

/*
 * Two steps on one page, as on academic.page. Step one asks for the email
 * address, or offers a passkey or GitHub instead. Continue emails a sign-in
 * link, and the next page says the same thing for every address, so nothing
 * on screen shows whether an account exists or how it signs in.
 *
 * A browser that last signed in with a password opens to the password field
 * instead, as does "Use your password instead". That comes from a cookie in
 * this browser, never from the account. The password and the link each have
 * their own form, so a filled-in password is never posted with a link request.
 */
const props = defineProps({
    email: { type: String, default: '' },
    usePassword: Boolean,
    prefersPassword: Boolean,
});

const page = usePage();
const passwordReset = computed(() => page.props.flash.status === 'password-reset');
const passwords = computed(() => page.props.passwords);

const email = ref(props.email);
const step = ref(passwords.value && props.usePassword && props.email ? 'password' : 'email');
// The server keeps addresses in lowercase
const address = () => email.value.trim().toLowerCase();
const emailInput = ref(null);
const passwordInput = ref(null);
const focus = (input) => nextTick(() => (input.value?.$el?.querySelector?.('input') ?? input.value)?.focus());

const showPassword = () => {
    step.value = 'password';
    focus(passwordInput);
};
const back = () => {
    step.value = 'email';
    focus(emailInput);
};

// A wrong password comes back with its error and stays on step two
// Every sign-in keeps you signed in unless this is a shared computer, said once on step one
const shared = ref(false);
const passwordForm = useForm({ login_step: 'password', email: '', password: '' });
const signIn = () => passwordForm
    .transform((data) => ({ ...data, email: address(), shared_device: shared.value ? 1 : 0 }))
    .post('/login/password', { onFinish: () => passwordForm.reset('password') });

// The link form posts the address and the shared-computer choice. A rejected address goes back to step one.
const linkForm = useForm({ email: '' });
const sendLink = () => linkForm
    .transform(() => ({ email: address(), shared_device: shared.value ? 1 : 0 }))
    .post('/auth/magic-link', { onError: () => back() });

const next = () => (passwords.value && props.prefersPassword ? showPassword() : sendLink());

onMounted(() => {
    if (step.value === 'password') focus(passwordInput);
});

const emailError = computed(() => (step.value === 'email' ? linkForm.errors.email : null));
const passwordError = computed(() => passwordForm.errors.password ?? passwordForm.errors.email);

// Passkey sign-in: fetch assertion options, hand to the authenticator, and
// post the result as a plain form (the passkeys package expects a classic
// POST, not an Inertia visit).
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

const divider = 'flex items-center gap-3';
const dividerLine = 'h-px flex-1 bg-slate-200 dark:bg-slate-800';
const dividerText = 'text-xs font-medium tracking-wide text-slate-500 uppercase dark:text-slate-400';
</script>

<template>
    <Head title="Sign in" />

    <AuthLayout title="Welcome back." description="Sign in to your Framework Cloud account.">
        <UiCallout v-if="passwordReset" variant="success" heading="Password changed">
            Sign in with your new password.
        </UiCallout>

        <div class="flex flex-col gap-5 rounded-xl border border-slate-200 p-5 sm:p-6 dark:border-slate-800">
            <!-- Step one: who is signing in -->
            <div v-show="step === 'email'" class="flex flex-col gap-5">
                <form class="flex flex-col gap-4" @submit.prevent="next">
                    <UiInput
                        ref="emailInput"
                        v-model="email"
                        label="Email"
                        type="email"
                        name="email"
                        placeholder="jane.smith@example.com"
                        autocomplete="username"
                        required
                        autofocus
                        :error="emailError"
                    />
                    <SharedComputer v-model="shared" />
                    <UiButton type="submit" class="w-full" :disabled="linkForm.processing">Continue</UiButton>
                </form>

                <template v-if="passkeySupported || page.props.githubLogin">
                    <div :class="divider" aria-hidden="true">
                        <span :class="dividerLine" /><span :class="dividerText">or</span><span :class="dividerLine" />
                    </div>

                    <div class="flex flex-col gap-3">
                        <template v-if="passkeySupported">
                            <form ref="assertionForm" method="post" action="/passkeys/authenticate" class="hidden">
                                <input type="hidden" name="_token" :value="page.props.csrf">
                                <input ref="assertionInput" type="hidden" name="start_authentication_response">
                                <input type="hidden" name="remember" :value="shared ? 0 : 1">
                            </form>

                            <UiButton class="w-full" variant="outline" :disabled="passkeyBusy" @click="signInWithPasskey">
                                {{ passkeyBusy ? 'Waiting for your device…' : 'Sign in with a passkey' }}
                            </UiButton>

                            <p v-if="passkeyError" class="text-sm text-red-600 dark:text-red-400">{{ passkeyError }}</p>
                        </template>

                        <GitHubButton v-if="page.props.githubLogin" label="Sign in with GitHub" :shared="shared" />
                    </div>
                </template>
            </div>

            <!-- Step two: how to finish signing in -->
            <div v-show="step === 'password'" class="flex flex-col gap-5">
                <div class="flex items-center justify-between gap-3 rounded-lg bg-slate-50 px-3 py-2 text-sm dark:bg-slate-800/60">
                    <span class="min-w-0 truncate">
                        <span class="text-slate-500 dark:text-slate-400">Signing in as </span>
                        <span class="font-medium text-slate-900 dark:text-white">{{ email }}</span>
                    </span>
                    <button type="button" class="shrink-0 text-sky-700 hover:text-sky-800 dark:text-sky-400 dark:hover:text-sky-300" @click="back">Change</button>
                </div>

                <template v-if="passwords">
                    <form class="flex flex-col gap-4" @submit.prevent="signIn">
                        <input type="hidden" name="login_step" value="password">
                        <!-- Password managers save and fill a password against the username in the same form -->
                        <input type="email" name="email" :value="email" autocomplete="username" readonly tabindex="-1" aria-hidden="true" class="sr-only">

                        <div class="flex flex-col gap-1.5">
                            <div class="flex items-center justify-between">
                                <label for="login-password" class="text-sm font-medium text-slate-900 dark:text-white">Password</label>
                                <Link href="/forgot-password" class="text-sm text-sky-700 hover:text-sky-800 dark:text-sky-400 dark:hover:text-sky-300">Forgot?</Link>
                            </div>
                            <input
                                id="login-password"
                                ref="passwordInput"
                                v-model="passwordForm.password"
                                type="password"
                                name="password"
                                placeholder="Enter your password"
                                autocomplete="current-password"
                                :required="step === 'password'"
                                :aria-invalid="passwordError ? 'true' : undefined"
                                :aria-describedby="passwordError ? 'login-password-error' : undefined"
                                :class="inputClass"
                            >
                            <p v-if="passwordError" id="login-password-error" class="text-sm text-red-600 dark:text-red-400">{{ passwordError }}</p>
                        </div>


                        <UiButton type="submit" class="w-full" :disabled="passwordForm.processing">Sign in</UiButton>
                    </form>
                </template>

                <!-- Its own form, so no password is posted with it -->
                <form class="flex justify-center" @submit.prevent="sendLink">
                    <input type="hidden" name="email" :value="email">
                    <button type="submit" class="text-sm font-medium text-sky-700 hover:text-sky-800 disabled:opacity-50 dark:text-sky-400 dark:hover:text-sky-300" :disabled="linkForm.processing">
                        Email me a sign-in link instead
                    </button>
                </form>
            </div>
        </div>

        <UiButton href="/register" variant="ghost" class="w-full">First time here? Create your account</UiButton>

        <div v-if="page.props.quickLogin" class="flex flex-col gap-2 rounded-lg border border-dashed border-amber-400 p-3">
            <p class="text-xs text-amber-700 dark:text-amber-400">Development only</p>
            <div class="flex gap-2">
                <UiButton class="flex-1" variant="outline" @click="router.post('/login/quick/user')">Sign in as test user</UiButton>
                <UiButton class="flex-1" variant="outline" @click="router.post('/login/quick/admin')">Sign in as admin</UiButton>
            </div>
        </div>
    </AuthLayout>
</template>
