<script setup>
import { ref, computed } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import SettingsLayout from '@/Layouts/SettingsLayout.vue';
import UiButton from '@/Components/UiButton.vue';
import UiInput from '@/Components/UiInput.vue';
import UiSwitch from '@/Components/UiSwitch.vue';
import UiModal from '@/Components/UiModal.vue';

const props = defineProps({ profile: Object, hasPassword: Boolean });

const page = usePage();
const saved = computed(() => page.props.flash.status === 'profile-updated');

const form = useForm({
    name: props.profile.name,
    email: props.profile.email,
    handle: props.profile.handle ?? '',
    profile_public: props.profile.profile_public,
});
const save = () => form.patch('/settings/account');

const passwordStatus = computed(() => ({
    'password-updated': 'Password saved.',
    'password-removed': 'Password removed.',
})[page.props.flash.status]);
const passwordForm = useForm({ current_password: '', password: '', password_confirmation: '' });
const savePassword = () => passwordForm.put('/settings/password', { preserveScroll: true, onFinish: () => passwordForm.reset() });
const removePassword = () => passwordForm.delete('/settings/password', { preserveScroll: true, onFinish: () => passwordForm.reset() });

const showDelete = ref(false);
const deleteForm = useForm({ confirm_email: '' });
const destroy = () => deleteForm.delete('/settings/account');
</script>

<template>
    <Head title="Account" />

    <SettingsLayout heading="Account" subheading="How you sign in, and your public page.">
        <form class="flex flex-col gap-4" @submit.prevent="save">
            <UiInput v-model="form.name" label="Name" required :error="form.errors.name" />
            <UiInput v-model="form.email" label="Email" type="email" required :error="form.errors.email" />
            <UiInput
                v-model="form.handle"
                label="Handle"
                placeholder="e.g. erik"
                description="Your public profile lives at framework.pub/@handle. Treat it as permanent once shared."
                :error="form.errors.handle"
            />
            <UiSwitch
                v-model="form.profile_public"
                label="Public profile"
                description="Show your name and member-since date at /@handle. Off, the page 404s. Requires a handle."
            />
            <div class="flex items-center gap-3">
                <UiButton type="submit" :disabled="form.processing">Save</UiButton>
                <span v-if="saved" class="text-sm text-slate-500">Saved.</span>
            </div>
        </form>

        <div v-if="page.props.passwords" class="mt-6 border-t border-slate-200 pt-6 dark:border-slate-800">
            <h2 class="font-semibold">Password</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                {{ hasPassword
                    ? 'You can sign in with your email address and this password.'
                    : 'Add a password to sign in with your email address and a password, as well as passkeys and email links.' }}
            </p>
            <form class="mt-4 flex flex-col gap-4" @submit.prevent="savePassword">
                <UiInput
                    v-if="hasPassword" v-model="passwordForm.current_password" label="Current password" type="password"
                    autocomplete="current-password" :error="passwordForm.errors.current_password"
                />
                <UiInput
                    v-model="passwordForm.password" :label="hasPassword ? 'New password' : 'Password'" type="password"
                    autocomplete="new-password" description="At least 10 characters." :error="passwordForm.errors.password"
                />
                <UiInput v-model="passwordForm.password_confirmation" label="Repeat the password" type="password" autocomplete="new-password" />
                <div class="flex items-center gap-3">
                    <UiButton type="submit" :disabled="passwordForm.processing">{{ hasPassword ? 'Change password' : 'Add password' }}</UiButton>
                    <UiButton v-if="hasPassword" variant="ghost" :disabled="passwordForm.processing" @click="removePassword">Remove password</UiButton>
                    <span v-if="passwordStatus" class="text-sm text-slate-500">{{ passwordStatus }}</span>
                </div>
            </form>
        </div>

        <div class="mt-6 border-t border-slate-200 pt-6 dark:border-slate-800">
            <h2 class="font-semibold">Delete account</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Deletes your settings, projects, tokens, and published documents.
            </p>
            <UiButton variant="danger" class="mt-3" @click="showDelete = true">Delete account</UiButton>
        </div>

        <UiModal v-model="showDelete" title="Are you sure you want to delete your account?">
            <p class="text-sm text-slate-600 dark:text-slate-400">
                All of your resources will be permanently deleted. Type your account
                email to confirm.
            </p>
            <UiInput v-model="deleteForm.confirm_email" label="Account email" type="email" :error="deleteForm.errors.confirm_email" />
            <div class="flex justify-end gap-2">
                <UiButton variant="outline" @click="showDelete = false">Cancel</UiButton>
                <UiButton variant="danger" :disabled="deleteForm.processing" @click="destroy">Delete account</UiButton>
            </div>
        </UiModal>
    </SettingsLayout>
</template>
