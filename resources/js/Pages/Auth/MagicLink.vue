<script setup>
import { useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import UiButton from '@/Components/UiButton.vue';
import KeepSignedIn from '@/Components/KeepSignedIn.vue';

const props = defineProps({ token: String, email: String, isNewAccount: Boolean });

const form = useForm({ remember: false });
const confirm = () => form.post(`/auth/magic/${props.token}`);
</script>

<template>
    <Head :title="isNewAccount ? 'Account confirmation' : 'Sign in'" />

    <AuthLayout
        :title="isNewAccount ? 'Account confirmation' : 'Sign in'"
        :description="`as ${email}`"
    >
        <form class="flex flex-col gap-4" @submit.prevent="confirm">
            <KeepSignedIn v-model="form.remember" />
            <UiButton type="submit" class="w-full" :disabled="form.processing">
                {{ isNewAccount ? 'Confirm and sign in' : 'Sign in' }}
            </UiButton>
        </form>
    </AuthLayout>
</template>
