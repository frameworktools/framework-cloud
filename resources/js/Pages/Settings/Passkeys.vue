<script setup>
import { ref, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import SettingsLayout from '@/Layouts/SettingsLayout.vue';
import UiButton from '@/Components/UiButton.vue';
import UiInput from '@/Components/UiInput.vue';

defineProps({ passkeys: Array });

const page = usePage();
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
        const response = await fetch('/settings/passkeys/options', {
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
        router.post('/settings/passkeys', { passkey: attestation, name: name.value }, {
            onFinish: () => { busy.value = false; name.value = ''; },
        });
    } catch (e) {
        busy.value = false;
        if (!window.passkeys.isCancellation(e)) {
            error.value = e.message;
        }
    }
}

const remove = (id) => {
    if (confirm('Remove this passkey? Devices using it will need email sign-in.')) {
        router.delete(`/settings/passkeys/${id}`);
    }
};
</script>

<template>
    <Head title="Passkeys" />

    <SettingsLayout
        heading="Passkeys"
        subheading="One-touch sign-in with Touch ID, Face ID, or a security key. Add one per device — this account has no password."
    >
        <div v-if="supported" class="flex flex-col gap-3">
            <p v-if="error" class="text-sm text-red-600 dark:text-red-400">{{ error }}</p>
            <div class="flex items-end gap-3">
                <div class="w-56"><UiInput v-model="name" label="Name (optional)" placeholder="e.g. MacBook Touch ID" /></div>
                <UiButton :disabled="busy" @click="addPasskey">
                    {{ busy ? 'Waiting for your device…' : 'Add a passkey' }}
                </UiButton>
            </div>
            <span v-if="added" class="text-sm text-neutral-500">Passkey added.</span>
        </div>
        <p v-else class="text-sm text-neutral-500">This browser does not support passkeys.</p>

        <div class="flex flex-col divide-y divide-neutral-200 dark:divide-neutral-700">
            <div v-for="passkey in passkeys" :key="passkey.id" class="flex items-center justify-between gap-4 py-3">
                <div>
                    <div class="text-sm font-medium">{{ passkey.name }}</div>
                    <p class="text-xs text-neutral-500">
                        Added {{ passkey.created_at }} · {{ passkey.last_used_at ? `Last used ${passkey.last_used_at}` : 'Never used' }}
                    </p>
                </div>
                <UiButton variant="danger" size="sm" @click="remove(passkey.id)">Remove</UiButton>
            </div>
            <p v-if="!passkeys.length" class="py-3 text-sm text-neutral-500">No passkeys yet.</p>
        </div>
    </SettingsLayout>
</template>
