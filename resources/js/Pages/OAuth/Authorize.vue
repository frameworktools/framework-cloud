<script setup>
import { ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import UiButton from '@/Components/UiButton.vue';

// Consent screen for connecting an AI app to Framework Cloud through the hosted MCP server.
// Passport renders it from GET /oauth/authorize (AppServiceProvider::configurePassport).
// Both buttons submit plain forms: approving redirects to the app's own site, which an
// Inertia request can't follow.
const props = defineProps({
    client: Object,
    user: Object,
    authToken: String,
    csrfToken: String,
    redirectHost: String,
    redirectIsLocal: Boolean,
});

const levels = [
    {
        value: 'full',
        label: 'Full access (recommended)',
        description: 'Read your projects, published results, and reports. Create, edit, and publish reports, and change who can read them.',
    },
    {
        value: 'read',
        label: 'Read only',
        description: 'Read your projects, published results, and reports. It cannot change anything.',
    },
];

const access = ref('full');
const submitting = ref(false);
</script>

<template>
    <Head :title="`Connect ${client.name}`" />

    <AppLayout>
        <div class="flex max-w-2xl flex-col gap-8">
            <PageHeader>
                <template #title>Connect {{ client.name }}</template>
                <template #description>
                    {{ client.name }} is asking to use Framework Cloud as {{ user.name }} ({{ user.email }}).
                    <template v-if="redirectIsLocal">After you approve, you return to the app on this computer.</template>
                    <template v-else-if="redirectHost">After you approve, you return to {{ redirectHost }}.</template>
                </template>
            </PageHeader>

            <form id="approve-form" method="POST" action="/oauth/authorize" class="flex flex-col gap-6" @submit="submitting = true">
                <input type="hidden" name="_token" :value="csrfToken">
                <input type="hidden" name="auth_token" :value="authToken">

                <fieldset>
                    <legend class="text-base font-medium text-slate-900 dark:text-white">Access</legend>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">The app works across all your projects.</p>
                    <div class="mt-4 flex flex-col gap-3">
                        <label
                            v-for="level in levels" :key="level.value"
                            class="flex cursor-pointer gap-3 rounded-lg border border-slate-800/10 p-4 text-sm has-[:checked]:border-sky-700 has-[:checked]:ring-1 has-[:checked]:ring-sky-700 dark:border-white/15 dark:has-[:checked]:border-sky-400 dark:has-[:checked]:ring-sky-400"
                        >
                            <input v-model="access" type="radio" name="access" :value="level.value" class="mt-0.5 accent-sky-700">
                            <span>
                                <span class="block font-medium text-slate-900 dark:text-white">{{ level.label }}</span>
                                <span class="mt-1 block text-slate-500 dark:text-slate-400">{{ level.description }}</span>
                            </span>
                        </label>
                    </div>
                </fieldset>

                <p class="text-sm text-slate-500 dark:text-slate-400">
                    What the app reads, including private projects and drafts, goes to the company that runs it.
                    You can disconnect it at any time under Connected agents.
                </p>
            </form>

            <div class="flex items-center justify-end gap-3">
                <form method="POST" action="/oauth/authorize" @submit="submitting = true">
                    <input type="hidden" name="_token" :value="csrfToken">
                    <input type="hidden" name="_method" value="DELETE">
                    <input type="hidden" name="auth_token" :value="authToken">
                    <UiButton type="submit" variant="ghost" :disabled="submitting">Cancel</UiButton>
                </form>
                <UiButton type="submit" form="approve-form" :disabled="submitting">Connect</UiButton>
            </div>
        </div>
    </AppLayout>
</template>
