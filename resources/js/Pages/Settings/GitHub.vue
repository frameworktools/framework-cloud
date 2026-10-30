<script setup>
import { computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import SettingsLayout from '@/Layouts/SettingsLayout.vue';
import UiButton from '@/Components/UiButton.vue';
import UiCallout from '@/Components/UiCallout.vue';
import GitHubButton from '@/Components/GitHubButton.vue';

defineProps({ available: Boolean, account: Object, installations: Array, install_url: String });

const page = usePage();
const error = computed(() => page.props.errors.github);
const connected = computed(() => page.props.flash.status === 'github-connected');

const disconnect = () => {
    if (confirm('Disconnect GitHub? Existing repositories stay on GitHub. Scaffold status stops updating until you connect again.')) {
        router.delete('/settings/github');
    }
};
</script>

<template>
    <Head title="GitHub" />

    <SettingsLayout
        heading="GitHub"
        subheading="Create projects as GitHub repositories, then clone them on any machine."
    >
        <UiCallout v-if="error" variant="danger" heading="GitHub">{{ error }}</UiCallout>
        <UiCallout v-if="connected" variant="success" heading="GitHub connected">
            Install the Framework app on each account or organization where you want new repositories.
        </UiCallout>

        <p v-if="!available" class="text-sm text-slate-500">GitHub is not set up on this server.</p>

        <template v-else-if="!account">
            <p class="text-sm text-slate-600 dark:text-slate-400">
                Framework uses a GitHub App. You choose which accounts and organizations it can reach,
                and it can only work in repositories it creates or that you give it.
            </p>
            <div class="w-60"><GitHubButton label="Connect GitHub" /></div>
        </template>

        <template v-else>
            <div class="flex items-center justify-between gap-4">
                <div>
                    <div class="text-sm font-medium">Connected as <a :href="`https://github.com/${account.login}`" class="font-mono hover:underline">@{{ account.login }}</a></div>
                    <p class="text-xs text-slate-500">Since {{ account.connected_at }}</p>
                </div>
                <UiButton variant="outline" size="sm" @click="disconnect">Disconnect</UiButton>
            </div>

            <div class="flex flex-col gap-2">
                <h2 class="text-sm font-semibold">Where Framework can create repositories</h2>
                <p v-if="installations === null" class="text-sm text-slate-500">GitHub could not be reached. Reload to try again.</p>
                <div v-else class="flex flex-col divide-y divide-slate-200 dark:divide-slate-700">
                    <div v-for="i in installations" :key="i.id" class="flex items-center justify-between py-2 text-sm">
                        <span class="font-mono">{{ i.login }}</span>
                        <span class="text-xs text-slate-500">
                            {{ i.type === 'Organization' ? 'Organization' : 'Your account' }} ·
                            {{ i.repository_selection === 'all' ? 'all repositories' : 'selected repositories' }}
                        </span>
                    </div>
                    <p v-if="!installations.length" class="py-2 text-sm text-slate-500">The app isn't installed anywhere yet.</p>
                </div>
                <a v-if="install_url" :href="install_url" class="text-sm text-sky-600 hover:underline dark:text-sky-400">
                    Install on another account or organization
                </a>
            </div>
        </template>
    </SettingsLayout>
</template>
