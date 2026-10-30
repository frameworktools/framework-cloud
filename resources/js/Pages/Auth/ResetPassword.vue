<script setup>
import { useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import UiButton from '@/Components/UiButton.vue';
import UiInput from '@/Components/UiInput.vue';

const props = defineProps({ token: String, email: String });

const form = useForm({ token: props.token, email: props.email, password: '', password_confirmation: '' });
const save = () => form.post('/reset-password', { onFinish: () => form.reset('password', 'password_confirmation') });
</script>

<template>
    <Head title="Choose a new password" />

    <AuthLayout title="Choose a new password" :description="email">
        <form class="flex flex-col gap-4" @submit.prevent="save">
            <UiInput
                v-model="form.password" label="New password" type="password" autocomplete="new-password" required autofocus
                description="At least 10 characters."
                :error="form.errors.password || form.errors.email"
            />
            <UiInput
                v-model="form.password_confirmation" label="Repeat the password" type="password" autocomplete="new-password" required
            />
            <UiButton type="submit" class="w-full" :disabled="form.processing">Save password</UiButton>
        </form>
    </AuthLayout>
</template>
