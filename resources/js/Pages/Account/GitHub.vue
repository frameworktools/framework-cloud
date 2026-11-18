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
const installed = computed(() => page.props.flash.status === 'github-installed');
// Where the app is installed, by name, for the message after connecting
const kind = (i) => (i.type === 'Organization' ? 'organization' : 'account');
const places = computed(() => (props.installations ?? []).map((i) => `${kind(i)} ${i.login}`));
// The connected user's own account needs the app too; GitHub doesn't install it there by default
const ownMissing = computed(() => Array.isArray(props.installations) && props.account
    && !props.installations.some((i) => i.login.toLowerCase() === props.account.login.toLowerCase()));
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
            Framework can create repositories only where the app is installed.
            <a v-if="install_url" :href="install_url" class="font-medium underline">Install on another account or organization</a>
        </UiCallout>

        <UiCallout v-if="installed" variant="success" heading="Framework app installed">
            <template v-if="!account">Connect GitHub to create repositories there.</template>
            <template v-else-if="places.length">Framework can create repositories in {{ list(places) }}.</template>
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

            <UiPanel title="Repository access" description="Framework can create repositories only under the accounts and organizations listed here. Install the app on any other account before you use it.">
            <div class="flex flex-col gap-2">
                <p v-if="installations === null" class="text-sm text-slate-500 dark:text-slate-400">GitHub could not be reached. Reload to try again.</p>
                <div v-else class="flex flex-col divide-y divide-slate-200 dark:divide-slate-700">
                    <div v-for="i in installations" :key="i.id" class="flex items-center justify-between py-2 text-sm">
                        <span><span class="font-mono">{{ i.login }}</span> <span class="text-slate-500 dark:text-slate-400">· {{ i.type === 'Organization' ? 'Organization' : 'Account' }}</span></span>
                        <span class="text-xs text-slate-500 dark:text-slate-400">{{ i.repository_selection === 'all' ? 'All repositories' : 'Selected repositories' }}</span>
                    </div>
                    <p v-if="!installations.length" class="py-2 text-sm text-slate-500 dark:text-slate-400">The app isn't installed anywhere yet.</p>
                </div>
                <UiCallout v-if="ownMissing" variant="warning" heading="Personal account">
                    <p>The Framework app is not installed on <span class="font-mono">@{{ account.login }}</span>. Framework cannot create repositories there until you install it.</p>
                    <div v-if="install_url"><UiButton :href="install_url" external variant="outline" size="sm">Install on @{{ account.login }}</UiButton></div>
                </UiCallout>
                <div v-if="install_url"><UiButton :href="install_url" external variant="outline" size="sm">Install on another account or organization</UiButton></div>
            </div>
            </UiPanel>
        </template>
    </AccountLayout>
</template>
