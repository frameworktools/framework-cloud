<script setup>
// Sets window.passkeys; only the passkey pages load it
import '@/passkeys.js';
import { ask } from '@/confirm.js';
import { ref, computed } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import AccountLayout from '@/Layouts/AccountLayout.vue';
import UiPanel from '@/Components/UiPanel.vue';
import UiButton from '@/Components/UiButton.vue';
import UiInput from '@/Components/UiInput.vue';
import UiSwitch from '@/Components/UiSwitch.vue';
import UiSeparator from '@/Components/UiSeparator.vue';

const props = defineProps({ passkeys: Array, hasPassword: Boolean, signInLinks: Boolean });

const page = usePage();
const passwordStatus = computed(() => ({
    'password-updated': 'Password saved.',
    'password-removed': 'Password removed.',
    'sign-in-links-updated': 'Saved.',
})[page.props.flash.status]);
// Ends every other remembered sign-in; this browser stays signed in
const otherDevicesDone = computed(() => page.props.flash.status === 'other-devices-signed-out');
const signOutOthers = async () => {
    if (await ask('Sign out other devices? Every other browser where you stay signed in will need to sign in again.')) {
        router.post('/account/sign-out-other-devices', {}, { preserveScroll: true });
    }
};
const passwordForm = useForm({ current_password: '', password: '', password_confirmation: '' });
const savePassword = () => passwordForm.put('/account/password', { preserveScroll: true, onFinish: () => passwordForm.reset() });
const removePassword = () => passwordForm.delete('/account/password', { preserveScroll: true, onFinish: () => passwordForm.reset() });
// Saves on change. Off, signing in emails nothing; the sign-in page looks the same.
const links = ref(props.signInLinks);
const saveLinks = (on) => {
    links.value = on;
    router.put('/account/sign-in-links', { email_sign_in_links: on }, {
        preserveScroll: true,
        onError: () => { links.value = !on; },
    });
};
const added = computed(() => page.props.flash.status === 'passkey-added');

const supported = ref(window.passkeys?.supported() ?? false);
const busy = ref(false);
const error = ref('');
const name = ref('');

async function addPasskey() {
    error.value = '';
    busy.value = true;
    try {
        // Fetch creation options, then hand them to the authenticator
        const response = await fetch('/account/passkeys/options', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': page.props.csrf,
            },
            credentials: 'same-origin',
        });
        if (!response.ok) throw new Error('Could not start passkey creation.');
        const attestation = await window.passkeys.createWithOptions(await response.json());
        router.post('/account/passkeys', { passkey: attestation, name: name.value }, {
            onFinish: () => { busy.value = false; name.value = ''; },
        });
    } catch (e) {
        busy.value = false;
        if (!window.passkeys.isCancellation(e)) {
            error.value = e.message;
        }
    }
}

const remove = async (id) => {
    if (await ask('Remove this passkey? Devices using it will need email sign-in.')) {
        router.delete(`/account/passkeys/${id}`);
    }
};
</script>

<template>
    <Head title="Sign-in & security" />

    <AccountLayout
        heading="Sign-in & security"
        subheading="Email links, passkeys, and an optional password."
    >
        <UiPanel title="New passkey" description="Give it a name so you can tell your devices apart.">
        <div v-if="supported" class="flex flex-col gap-3">
            <p v-if="error" class="text-sm text-red-600 dark:text-red-400">{{ error }}</p>
            <div class="flex items-end gap-3">
                <div class="w-56"><UiInput v-model="name" label="Name (optional)" placeholder="e.g. MacBook Touch ID" /></div>
                <UiButton :disabled="busy" @click="addPasskey">
                    {{ busy ? 'Waiting for your device…' : 'Add a passkey' }}
                </UiButton>
            </div>
            <span v-if="added" class="text-sm text-slate-500 dark:text-slate-400">Passkey added.</span>
        </div>
        <p v-else class="text-sm text-slate-500 dark:text-slate-400">This browser does not support passkeys.</p>
        </UiPanel>

        <UiPanel title="Passkeys">
        <div class="flex flex-col divide-y divide-slate-200 dark:divide-slate-700">
            <div v-for="passkey in passkeys" :key="passkey.id" class="flex items-center justify-between gap-4 py-3">
                <div>
                    <div class="text-sm font-medium">{{ passkey.name }}</div>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Added {{ passkey.created_at }} · {{ passkey.last_used_at ? `Last used ${passkey.last_used_at}` : 'Never used' }}
                    </p>
                </div>
                <UiButton variant="danger" size="sm" @click="remove(passkey.id)">Remove</UiButton>
            </div>
            <p v-if="!passkeys.length" class="py-3 text-sm text-slate-500 dark:text-slate-400">No passkeys yet.</p>
        </div>
        </UiPanel>
        <UiPanel
            v-if="page.props.passwords" title="Password"
            :description="hasPassword
                ? 'You can sign in with your email address and this password.'
                : 'Sign in with your email address and a password. Passkeys and email links keep working.'"
        >
            <form class="flex flex-col gap-4" @submit.prevent="savePassword">
                <UiInput
                    v-if="hasPassword" v-model="passwordForm.current_password" label="Current password" type="password"
                    autocomplete="current-password" :error="passwordForm.errors.current_password"
                />
                <UiInput
                    v-model="passwordForm.password" :label="hasPassword ? 'New password' : 'Password'" type="password"
                    autocomplete="new-password" description="At least 10 characters." :error="passwordForm.errors.password"
                />
                <UiInput v-model="passwordForm.password_confirmation" label="Repeat the password" type="password" autocomplete="new-password" />
                <div class="flex items-center justify-end gap-3 border-t border-slate-200 pt-4 dark:border-slate-700">
                    <span v-if="passwordStatus" class="text-sm text-slate-500 dark:text-slate-400">{{ passwordStatus }}</span>
                    <UiButton v-if="hasPassword" variant="ghost" :disabled="passwordForm.processing" @click="removePassword">Remove password</UiButton>
                    <UiButton type="submit" :disabled="passwordForm.processing">{{ hasPassword ? 'Change password' : 'Add password' }}</UiButton>
                </div>
            </form>

            <template v-if="hasPassword">
                <UiSeparator />
                <UiSwitch
                    :model-value="links"
                    label="Email me a sign-in link when I sign in"
                    description="Off, no email is sent. After entering your address, choose Use your password instead. This browser then opens to the password next time."
                    @update:model-value="saveLinks"
                />
            </template>
        </UiPanel>

        <UiPanel title="Other devices" description="Sign-ins last a year unless someone says the computer is shared. Sign out everywhere else if you lost a device or signed in on someone else's.">
            <div class="flex flex-wrap items-center gap-3">
                <UiButton variant="ghost" @click="signOutOthers">Sign out other devices</UiButton>
                <p v-if="otherDevicesDone" class="text-sm text-emerald-700 dark:text-emerald-400">Other devices are signed out.</p>
            </div>
        </UiPanel>
    </AccountLayout>
</template>
