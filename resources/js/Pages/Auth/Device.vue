<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import UiButton from '@/Components/UiButton.vue';
import UiCallout from '@/Components/UiCallout.vue';
import GitHubButton from '@/Components/GitHubButton.vue';

// A signed-out visitor following a computer's sign-in link. Signing in or
// creating an account returns here to approve the request.
const props = defineProps({ code: String, clientName: String, error: String, registrationOpen: Boolean });

const page = usePage();
const pending = computed(() => props.code && !props.error);
</script>

<template>
    <Head title="Computer sign-in" />

    <AuthLayout
        title="Computer sign-in"
        description="A program on your computer asks to connect to Framework Cloud."
    >
        <UiCallout v-if="error" variant="warning" heading="Sign-in request">
            {{ error }}
        </UiCallout>

        <dl v-if="pending" class="grid grid-cols-[auto_1fr] gap-x-6 gap-y-2 border-y border-slate-800/10 py-4 text-sm dark:border-white/15">
            <dt class="text-slate-500 dark:text-slate-400">Code</dt>
            <dd class="font-mono font-medium">{{ code }}</dd>
            <dt class="text-slate-500 dark:text-slate-400">Program</dt>
            <dd>{{ clientName ?? 'Not named' }}</dd>
        </dl>

        <p v-if="!code" class="text-sm text-slate-600 dark:text-slate-300">
            Open the link your computer showed, or sign in and enter the code.
        </p>

        <div class="flex flex-col gap-3">
            <UiButton href="/login" class="w-full justify-center">Sign in</UiButton>
            <UiButton v-if="registrationOpen" href="/register" variant="ghost" class="w-full justify-center">Create account</UiButton>
            <GitHubButton v-if="page.props.githubLogin" label="Continue with GitHub" />
        </div>

        <p class="text-sm text-slate-500 dark:text-slate-400">
            <template v-if="registrationOpen">Accounts are free. </template>
            <template v-else>This server does not accept new accounts. </template>
            After you sign in, you return here to approve the request. The code expires 20 minutes after your computer asked.
        </p>
    </AuthLayout>
</template>
