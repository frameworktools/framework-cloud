<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { CheckCircleIcon, XMarkIcon } from '@heroicons/vue/20/solid';

// Server statuses that no page shows itself. A page that confirms a status in
// place (a "Saved" next to its button, a callout) keeps it out of this list.
const messages = {
    'artifact-published': 'Artifact published.',
    'artifact-merged': 'Versions merged.',
    'document-deleted': 'Artifact taken down.',
    'version-deleted': 'Version deleted.',
    'report-published': 'Report published.',
    'skill-imported': 'Skill imported.',
    'facet-imported': 'Imported.',
    'skill-forked': 'Copy created.',
    'facet-forked': 'Copy created.',
    'blueprint-forked': 'Copy created.',
    'password-removed': 'Password removed. Sign in with email links or passkeys.',
    'github-disconnected': 'GitHub disconnected.',
    'device-denied': 'Request denied.',
};

const page = usePage();
const message = ref('');
let timer = null;

const show = () => {
    const text = messages[page.props.flash?.status];
    if (!text) return;
    message.value = text;
    clearTimeout(timer);
    timer = setTimeout(() => { message.value = ''; }, 6000);
};

let off = null;
onMounted(() => {
    show();
    off = router.on('success', show);
});
onBeforeUnmount(() => { off?.(); clearTimeout(timer); });
</script>

<template>
    <div aria-live="polite" class="pointer-events-none fixed bottom-4 right-4 z-40">
        <div v-if="message" class="pointer-events-auto flex items-center gap-2 rounded-lg border border-slate-800/10 bg-white px-4 py-3 text-sm text-slate-800 shadow-lg dark:border-white/15 dark:bg-slate-900 dark:text-slate-200">
            <CheckCircleIcon class="size-4 text-emerald-600" aria-hidden="true" />
            <span>{{ message }}</span>
            <button type="button" class="ml-2 text-slate-400 hover:text-slate-600" aria-label="Dismiss" @click="message = ''"><XMarkIcon class="size-4" /></button>
        </div>
    </div>
</template>
