<script setup>
import { ask } from '@/confirm.js';
import UiPanel from '@/Components/UiPanel.vue';
import { computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AccountLayout from '@/Layouts/AccountLayout.vue';
import UiButton from '@/Components/UiButton.vue';
import UiCallout from '@/Components/UiCallout.vue';
import GitHubButton from '@/Components/GitHubButton.vue';

const props = defineProps({ available: Boolean, account: Object, installations: Array, install_url: String });

const page = usePage();
const error = computed(() => page.props.errors.github);
const connected = computed(() => page.props.flash.status === 'github-connected');
// Where the app is installed, by name, for the message after connecting
const kind = (i) => (i.type === 'Organization' ? 'organization' : 'account');
const places = computed(() => (props.installations ?? []).map((i) => `${kind(i)} ${i.login}`));
const list = (items) => (items.length < 3 ? items.join(' and ') : `${items.slice(0, -1).join(', ')}, and ${items.at(-1)}`);

const disconnect = async () => {
    if (await ask('Disconnect GitHub? Existing repositories stay on GitHub. Scaffold status stops updating until you connect again.')) {
        router.delete('/account/github');
    }
};
</script>

<template>
    <Head title="GitHub" />

    <AccountLayout
        heading="GitHub"
        subheading="Create projects as GitHub repositories, then clone them on any machine."
    >
        <UiCallout v-if="error" variant="danger" heading="GitHub">{{ error }}</UiCallout>
        <UiCallout v-if="connected" variant="success" heading="GitHub connected">
            <template v-if="places.length">Connected to {{ list(places) }}. </template>
            <template v-else-if="account">Connected as @{{ account.login }}. Install the Framework app on your account or an organization to create repositories there. </template>
            Each other organization needs the app installed too.
            <a v-if="install_url" :href="install_url" class="font-medium underline">Install on another organization</a>
        </UiCallout>

        <p v-if="!available" class="text-sm text-slate-500 dark:text-slate-400">GitHub is not set up on this server.</p>

        <UiPanel
            v-else-if="!account" title="GitHub connection"
            description="Framework uses a GitHub App. You choose its accounts, organizations, and repositories."
        >
            <div class="w-60"><GitHubButton label="Connect GitHub" href="/auth/github/redirect" /></div>
        </UiPanel>

        <template v-else>
            <UiPanel title="Account">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <div class="text-sm font-medium">Connected as <a :href="`https://github.com/${account.login}`" class="font-mono hover:underline">@{{ account.login }}</a></div>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Since {{ account.connected_at }}</p>
                    <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">To use a different GitHub account, disconnect, sign in to that account on github.com, and connect again.</p>
                </div>
                <UiButton variant="outline" size="sm" @click="disconnect">Disconnect</UiButton>
            </div>
            </UiPanel>

            <UiPanel title="Repository access" description="Framework creates repositories only where the app is installed. Install it on each account or organization you use.">
            <div class="flex flex-col gap-2">
                <p v-if="installations === null" class="text-sm text-slate-500 dark:text-slate-400">GitHub could not be reached. Reload to try again.</p>
                <div v-else class="flex flex-col divide-y divide-slate-200 dark:divide-slate-700">
                    <div v-for="i in installations" :key="i.id" class="flex items-center justify-between py-2 text-sm">
                        <span><span class="font-mono">{{ i.login }}</span> <span class="text-slate-500 dark:text-slate-400">· {{ i.type === 'Organization' ? 'Organization' : 'Account' }}</span></span>
                        <span class="text-xs text-slate-500 dark:text-slate-400">{{ i.repository_selection === 'all' ? 'All repositories' : 'Selected repositories' }}</span>
                    </div>
                    <p v-if="!installations.length" class="py-2 text-sm text-slate-500 dark:text-slate-400">The app isn't installed anywhere yet.</p>
                </div>
                <div v-if="install_url"><a :href="install_url"><UiButton variant="outline" size="sm">Install on another organization</UiButton></a></div>
            </div>
            </UiPanel>
        </template>
    </AccountLayout>
</template>
