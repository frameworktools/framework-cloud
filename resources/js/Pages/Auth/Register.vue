<script setup>
import { computed } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import UiButton from '@/Components/UiButton.vue';
import UiInput from '@/Components/UiInput.vue';
import UiCallout from '@/Components/UiCallout.vue';
import GitHubButton from '@/Components/GitHubButton.vue';

const page = usePage();

const form = useForm({ name: '', email: '' });
const send = () => form.post('/auth/magic-link');
</script>

<template>
    <Head title="Account registration" />

    <AuthLayout
        title="Account registration"
        :description="page.props.githubLogin ? 'Sign up with GitHub or your email address. There is no password to set.' : 'Sign up with your email address. There is no password to set.'"
    >

        <template v-if="page.props.githubLogin">
            <GitHubButton label="Sign up with GitHub" />
            <div class="flex items-center gap-3 text-xs text-slate-400">
                <div class="h-px flex-1 bg-slate-200 dark:bg-slate-700" />
                or
                <div class="h-px flex-1 bg-slate-200 dark:bg-slate-700" />
            </div>
        </template>

        <form class="flex flex-col gap-4" @submit.prevent="send">
            <UiInput v-model="form.name" label="Name" placeholder="Full name" autocomplete="name" required autofocus :error="form.errors.name" />
            <UiInput
                v-model="form.email" label="Email address" type="email" placeholder="you@example.com" autocomplete="email" required
                description="Sign-in address. Your author profile can use a different public email."
                :error="form.errors.email"
            />
            <UiButton type="submit" class="w-full" :disabled="form.processing">Email me a sign-in link</UiButton>
        </form>

        <p class="text-center text-sm text-slate-500 dark:text-slate-400">
            Already have an account?
            <Link href="/login" class="text-sky-700 hover:underline dark:text-sky-400">Sign in</Link>
        </p>
    </AuthLayout>
</template>
