<script setup>
import { useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import UiButton from '@/Components/UiButton.vue';

const props = defineProps({ token: String, email: String, isNewAccount: Boolean });

const form = useForm({});
const confirm = () => form.post(`/auth/magic/${props.token}`);
</script>

<template>
    <Head :title="isNewAccount ? 'Confirm your account' : 'Sign in'" />

    <AuthLayout
        :title="isNewAccount ? 'Confirm your account' : 'Sign in'"
        :description="`as ${email}`"
    >
        <form @submit.prevent="confirm">
            <UiButton type="submit" class="w-full" :disabled="form.processing">
                {{ isNewAccount ? 'Confirm and sign in' : 'Sign in' }}
            </UiButton>
        </form>
    </AuthLayout>
</template>
