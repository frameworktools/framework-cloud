<script setup>
import { computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import UiButton from '@/Components/UiButton.vue';
import UiCallout from '@/Components/UiCallout.vue';

const page = usePage();
const sent = computed(() => page.props.flash.status === 'verification-link-sent');

const resend = () => router.post('/verify-email/send');
const logout = () => router.post('/logout');
</script>

<template>
    <Head title="Email verification" />

    <AuthLayout
        title="Email verification"
        description="Check your inbox to confirm your new email address."
    >
        <UiCallout v-if="sent" variant="success" heading="Sent">
            A fresh verification link is on its way.
        </UiCallout>

        <UiButton class="w-full" @click="resend">Resend verification email</UiButton>
        <UiButton variant="ghost" class="w-full" @click="logout">Sign out</UiButton>
    </AuthLayout>
</template>
