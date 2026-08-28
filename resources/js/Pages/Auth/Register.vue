<script setup>
import { computed } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import UiButton from '@/Components/UiButton.vue';
import UiInput from '@/Components/UiInput.vue';
import UiCallout from '@/Components/UiCallout.vue';

const page = usePage();
const sent = computed(() => page.props.flash.status === 'magic-link-sent');

const form = useForm({ name: '', email: '' });
const send = () => form.post('/auth/magic-link');
</script>

<template>
    <Head title="Create an account" />

    <AuthLayout
        title="Create an account"
        description="We'll email you a link to confirm your address and sign you in — no password to invent."
    >
        <UiCallout v-if="sent" variant="success" heading="Check your email">
            Your confirmation link is on its way. It works once and expires in 15
            minutes. Once you're in, add a passkey in Settings for one-touch sign-in.
        </UiCallout>

        <form class="flex flex-col gap-4" @submit.prevent="send">
            <UiInput v-model="form.name" label="Name" placeholder="Full name" autocomplete="name" required autofocus :error="form.errors.name" />
            <UiInput v-model="form.email" label="Email address" type="email" placeholder="you@university.edu" autocomplete="email" required :error="form.errors.email" />
            <UiButton type="submit" class="w-full" :disabled="form.processing">Send me a sign-in link</UiButton>
        </form>

        <p class="text-center text-sm text-neutral-500 dark:text-neutral-400">
            Already have an account?
            <Link href="/login" class="text-orange-600 hover:underline dark:text-orange-400">Sign in</Link>
        </p>
    </AuthLayout>
</template>
