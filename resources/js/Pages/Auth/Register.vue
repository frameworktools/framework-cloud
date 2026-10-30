<script setup>
import { computed } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import UiButton from '@/Components/UiButton.vue';
import UiInput from '@/Components/UiInput.vue';
import UiCallout from '@/Components/UiCallout.vue';
import GitHubButton from '@/Components/GitHubButton.vue';

const page = usePage();
const sent = computed(() => page.props.flash.status === 'magic-link-sent');

const form = useForm({ name: '', email: '' });
const send = () => form.post('/auth/magic-link');
</script>

<template>
    <Head title="Create an account" />

    <AuthLayout
        title="Create an account"
        :description="page.props.githubSignIn ? 'Sign up with GitHub or your email address. There is no password to set.' : 'Sign up with your email address. There is no password to set.'"
    >
        <UiCallout v-if="sent" variant="success" heading="Check your email">
            Your confirmation link is on its way. It works once and expires in 15
            minutes. Once you're in, add a passkey in Settings for one-touch sign-in.
        </UiCallout>

        <template v-if="page.props.githubSignIn">
            <GitHubButton label="Sign up with GitHub" />
            <div class="flex items-center gap-3 text-xs text-neutral-400">
                <div class="h-px flex-1 bg-neutral-200 dark:bg-neutral-700" />
                or
                <div class="h-px flex-1 bg-neutral-200 dark:bg-neutral-700" />
            </div>
        </template>

        <form class="flex flex-col gap-4" @submit.prevent="send">
            <UiInput v-model="form.name" label="Name" placeholder="Full name" autocomplete="name" required autofocus :error="form.errors.name" />
            <UiInput
                v-model="form.email" label="Email address" type="email" placeholder="you@example.com" autocomplete="email" required
                description="Use any address, including a personal one. It is only for signing in. You can show a different email on your work, such as your university address, in Settings > Profile."
                :error="form.errors.email"
            />
            <UiButton type="submit" class="w-full" :disabled="form.processing">Send me a sign-in link</UiButton>
        </form>

        <p class="text-center text-sm text-neutral-500 dark:text-neutral-400">
            Already have an account?
            <Link href="/login" class="text-orange-600 hover:underline dark:text-orange-400">Sign in</Link>
        </p>
    </AuthLayout>
</template>
