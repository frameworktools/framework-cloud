<script setup>
import { ref, computed } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import SettingsLayout from '@/Layouts/SettingsLayout.vue';
import UiButton from '@/Components/UiButton.vue';
import UiInput from '@/Components/UiInput.vue';
import UiSwitch from '@/Components/UiSwitch.vue';
import UiModal from '@/Components/UiModal.vue';

const props = defineProps({ profile: Object });

const page = usePage();
const saved = computed(() => page.props.flash.status === 'profile-updated');

const form = useForm({
    name: props.profile.name,
    email: props.profile.email,
    handle: props.profile.handle ?? '',
    profile_public: props.profile.profile_public,
});
const save = () => form.patch('/settings/profile');

const showDelete = ref(false);
const deleteForm = useForm({ confirm_email: '' });
const destroy = () => deleteForm.delete('/settings/profile');
</script>

<template>
    <Head title="Profile" />

    <SettingsLayout heading="Profile" subheading="Your account and public presence.">
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
                <span v-if="saved" class="text-sm text-neutral-500">Saved.</span>
            </div>
        </form>

        <div class="mt-6 border-t border-neutral-200 pt-6 dark:border-neutral-800">
            <h2 class="font-semibold">Delete account</h2>
            <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                Deletes your settings, projects, tokens, and published documents.
            </p>
            <UiButton variant="danger" class="mt-3" @click="showDelete = true">Delete account</UiButton>
        </div>

        <UiModal v-model="showDelete" title="Are you sure you want to delete your account?">
            <p class="text-sm text-neutral-600 dark:text-neutral-400">
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
