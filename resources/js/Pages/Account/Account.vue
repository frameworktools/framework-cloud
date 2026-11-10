<script setup>
import { ref, computed } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import AccountLayout from '@/Layouts/AccountLayout.vue';
import UiButton from '@/Components/UiButton.vue';
import UiInput from '@/Components/UiInput.vue';
import UiSwitch from '@/Components/UiSwitch.vue';
import UiModal from '@/Components/UiModal.vue';
import UiPanel from '@/Components/UiPanel.vue';

const props = defineProps({ profile: Object });

const page = usePage();
const host = computed(() => String(page.props.appUrl ?? '').replace(/^https?:\/\//, '').replace(/\/$/, ''));
const saved = computed(() => page.props.flash.status === 'profile-updated');

const form = useForm({
    name: props.profile.name,
    email: props.profile.email,
    handle: props.profile.handle ?? '',
    profile_public: props.profile.profile_public,
});
const save = () => form.patch('/account/account', { preserveScroll: true });


const showDelete = ref(false);
const deleteForm = useForm({ confirm_email: '' });
// Keep the dialog open on an error, so the message shows where the email was typed
const destroy = () => deleteForm.delete('/account/account', { preserveScroll: true, preserveState: true });
</script>

<template>
    <Head title="Account" />

    <AccountLayout heading="Account" subheading="Your name, sign-in email, and public page.">
        <UiPanel title="Details">
        <form id="account-form" class="flex flex-col gap-4" @submit.prevent="save">
            <UiInput v-model="form.name" label="Name" required :error="form.errors.name" />
            <UiInput v-model="form.email" label="Sign-in email" type="email" required description="Private address for sign-in and account messages." :error="form.errors.email" />
            <UiInput
                v-model="form.handle"
                label="Handle"
                placeholder="e.g. erik"
                :description="`Your public profile lives at ${host}/@${form.handle || 'handle'}. Treat it as permanent once shared.`"
                :error="form.errors.handle"
            />
            <UiSwitch
                v-model="form.profile_public"
                label="Public profile"
                description="Shows your name and the date you joined at /@handle. When this is off, nobody can see the page. It needs a handle."
            />
        </form>
            <template #actions>
                <span v-if="saved" class="self-center text-sm text-slate-500 dark:text-slate-400">Saved.</span>
                <UiButton save-shortcut type="submit" form="account-form" :disabled="form.processing">Save changes</UiButton>
            </template>
        </UiPanel>

        <UiPanel title="Account deletion" description="Deletes your settings, projects, tokens, and published artifacts, collections, and reports.">
            <template #actions>
                <UiButton variant="danger" @click="showDelete = true">Delete account</UiButton>
            </template>
        </UiPanel>

        <UiModal v-model="showDelete" title="Account deletion">
            <p class="text-sm text-slate-600 dark:text-slate-400">
                Your projects, artifacts, collections, reports, settings, and tokens
                are deleted for good. Type your account email to confirm.
            </p>
            <UiInput v-model="deleteForm.confirm_email" label="Account email" type="email" :error="deleteForm.errors.confirm_email" />
            <div class="flex justify-end gap-2">
                <UiButton variant="outline" @click="showDelete = false">Cancel</UiButton>
                <UiButton variant="danger" :disabled="deleteForm.processing" @click="destroy">Delete account</UiButton>
            </div>
        </UiModal>
    </AccountLayout>
</template>
