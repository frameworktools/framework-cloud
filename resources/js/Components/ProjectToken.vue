<script setup>
// A freshly issued project token and the command that sets the project up
// with it, as the Computers section and Overview's Local setup show them.
import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import CopyBlock from '@/Components/CopyBlock.vue';
import UiButton from '@/Components/UiButton.vue';
import { clientUrl, useServerFlag, useSetupCommand } from '@/cloud.js';

const props = defineProps({ project: Object, token: String, language: String });

const page = usePage();
const setupCommand = useSetupCommand();
const serverFlag = useServerFlag();

// The address setup commands name (FW_CLIENT_URL or APP_URL), checked from
// this browser: a computer that can't reach it can't set the project up
const serverUrl = computed(() => clientUrl(page));
const viewingOrigin = typeof window !== 'undefined' ? window.location.origin : '';
const viewingElsewhere = computed(() => viewingOrigin !== serverUrl.value);
const reachable = ref(null);
const checkServer = async () => {
    reachable.value = 'checking';
    try {
        await fetch(`${serverUrl.value}/up`, { mode: 'no-cors', cache: 'no-store' });
        reachable.value = 'yes';
    } catch {
        reachable.value = 'no';
    }
};

const setupWhere = computed(() => ({
    r: 'Paste it into the R console.',
    python: 'Paste it into Python.',
    stata: 'Run it in a terminal. Paste the token when asked.',
}[props.language] ?? 'Paste it into the R console.'));
// R, Python, and fw (Stata) all ask before creating the folder
const setupFolder = 'It asks where to create the project folder. Press Enter for the current folder.';
</script>

<template>
    <div class="flex min-w-0 flex-col gap-6">
        <div>
            <h3 class="text-sm font-medium text-slate-900 dark:text-white">Project token</h3>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Copy it now. It is shown only once.</p>
            <CopyBlock :value="token" label="project token" kind="value" class="mt-3" />
        </div>
        <div>
            <h3 class="text-sm font-medium text-slate-900 dark:text-white">Setup command</h3>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ setupWhere }}</p>
            <CopyBlock :value="setupCommand(language, token)" label="setup command" class="mt-3" />
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ setupFolder }}</p>
        </div>
        <details class="text-sm text-slate-500 dark:text-slate-400">
            <summary class="cursor-pointer select-none hover:text-slate-800 dark:hover:text-slate-200">Connection details</summary>
            <div class="mt-3 flex flex-col gap-2">
                <p v-if="language === 'python'">Python connects to the server you signed in to with <code class="font-mono text-xs">cloud_login()</code>. That should be {{ serverUrl }}.</p>
                <p v-else>The command connects to {{ serverUrl }}.</p>
                <p v-if="viewingElsewhere">This page is open at {{ viewingOrigin }}. Check that your computer can reach the server.</p>
                <p v-if="language === 'stata'">After <code class="font-mono text-xs">fw login</code>, <code class="font-mono text-xs">fw new --from {{ project.slug }}{{ serverFlag }}</code> needs no token.</p>
                <UiButton variant="ghost" size="sm" class="self-start" :disabled="reachable === 'checking'" @click="checkServer">{{ reachable === 'checking' ? 'Checking…' : 'Check browser connection' }}</UiButton>
                <p v-if="reachable === 'yes'">This browser reached {{ serverUrl }}.</p>
                <p v-else-if="reachable === 'no'" class="text-red-700 dark:text-red-300">This browser could not reach {{ serverUrl }}. Ask whoever runs this server to set FW_CLIENT_URL to an address your computer can reach.</p>
            </div>
        </details>
    </div>
</template>
