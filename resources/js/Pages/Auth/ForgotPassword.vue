<script setup>
import { computed } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import UiButton from '@/Components/UiButton.vue';
import UiInput from '@/Components/UiInput.vue';
import UiCallout from '@/Components/UiCallout.vue';

const page = usePage();
const sent = computed(() => page.props.flash.status === 'reset-link-sent');

const form = useForm({ email: '' });
const send = () => form.post('/forgot-password');
</script>

<template>
    <Head title="Reset your password" />

    <AuthLayout title="Reset your password" description="We'll email you a link to choose a new one.">
        <UiCallout v-if="sent" variant="success" heading="Check your email">
            If an account exists for that address, a reset link is on its way. It
            expires in 60 minutes.
        </UiCallout>

        <form class="flex flex-col gap-4" @submit.prevent="send">
            <UiInput
                v-model="form.email" label="Email address" type="email" autocomplete="email" required autofocus
                :error="form.errors.email"
            />
            <UiButton type="submit" class="w-full" :disabled="form.processing">Email me a reset link</UiButton>
        </form>

        <p class="text-center text-sm text-neutral-500 dark:text-neutral-400">
            <Link href="/login" class="text-orange-600 hover:underline dark:text-orange-400">Back to sign in</Link>
        </p>
    </AuthLayout>
</template>
