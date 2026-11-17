<script setup>
// The share dialog a "…" menu opens: the thing's short link and QR code,
// loaded when it opens. Something private must be opened up first: made
// public, or open to anyone with the link (a project gets a public download).
// An artifact also has an Embed view: the link and HTML to show it on another site.
import { computed, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import UiModal from '@/Components/UiModal.vue';
import UiButton from '@/Components/UiButton.vue';
import UiTabs from '@/Components/UiTabs.vue';
import ShareQrBody from '@/Components/ShareQrBody.vue';
import ShareEmbedBody from '@/Components/ShareEmbedBody.vue';

// target: { type: 'artifact' | 'report' | 'collection' | 'blueprint' | 'project', id, title, settings? (href), onSettings? (instead of settings), view?: 'link' | 'embed' }
const props = defineProps({ target: { type: Object, default: null } });
const emit = defineEmits(['close']);
const page = usePage();
const show = ref(false);
const blank = () => ({ loading: true, share: null, reason: null, visibility: null, embed: null, embedReason: null, error: null, saving: false });
const state = ref(blank());
const view = ref('link');
const views = [{ label: 'Link', value: 'link' }, { label: 'Embed', value: 'embed' }];
const embedding = computed(() => props.target?.type === 'artifact' && view.value === 'embed');
const loaded = (data) => ({ share: data.share, reason: data.reason, visibility: data.visibility, embed: data.embed ?? null, embedReason: data.embed_reason ?? null });

const request = async (method, body) => {
    const response = await fetch(`/share/${props.target.type}/${props.target.id}`, {
        method,
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': page.props.csrf, 'X-Requested-With': 'XMLHttpRequest' },
        body: body ? JSON.stringify(body) : undefined,
    });
    const data = await response.json();
    if (!response.ok) throw new Error(Object.values(data.errors ?? {})[0]?.[0] ?? data.message ?? 'That did not work. Try again.');
    return data;
};

watch(() => props.target, async (target) => {
    show.value = Boolean(target);
    if (!target) return;
    state.value = blank();
    view.value = target.type === 'artifact' && target.view === 'embed' ? 'embed' : 'link';
    try {
        const data = await request('GET');
        state.value = { ...state.value, loading: false, ...loaded(data) };
    } catch (e) {
        state.value = { ...state.value, loading: false, error: e.message };
    }
});

const open = async (visibility) => {
    state.value.saving = true;
    state.value.error = null;
    try {
        const data = await request('POST', { visibility });
        state.value = { ...state.value, saving: false, ...loaded(data) };
        // The list shows each thing's sharing; bring it up to date
        router.reload({ preserveScroll: true });
    } catch (e) {
        state.value = { ...state.value, saving: false, error: e.message };
    }
};
watch(show, (open) => { if (!open) emit('close'); });
</script>

<template>
    <UiModal v-model="show" :title="target ? `Share ${target.title}` : 'Share'">
        <UiTabs v-if="target?.type === 'artifact'" v-model="view" :tabs="views" label="Share format" />
        <div :class="target?.type === 'artifact' ? 'mt-2' : 'mt-4'">
            <p v-if="state.loading" class="text-sm text-slate-500 dark:text-slate-400">Loading…</p>
            <ShareEmbedBody v-else-if="embedding && state.embed" :key="state.embed.key" :embed="state.embed" :title="target.title" />
            <ShareQrBody v-else-if="!embedding && state.share" :share="state.share" />
            <div v-else class="flex flex-col items-start gap-4 text-sm text-slate-600 dark:text-slate-300">
                <p>{{ embedding ? state.embedReason : state.reason }}</p>
                <div class="flex flex-wrap gap-2">
                    <template v-if="target?.type === 'project'">
                        <UiButton size="sm" :disabled="state.saving" @click="open('public')">Create public download link</UiButton>
                    </template>
                    <!-- A password-protected artifact is already open; its password is removed in its settings -->
                    <template v-else-if="!embedding || state.visibility === 'private'">
                        <UiButton size="sm" :disabled="state.saving" @click="open('public')">Make public</UiButton>
                        <UiButton size="sm" variant="ghost" :disabled="state.saving" @click="open('unlisted')">Anyone with the link</UiButton>
                    </template>
                    <UiButton v-if="target?.onSettings" variant="ghost" size="sm" @click="target.onSettings(); show = false">Sharing settings</UiButton>
                    <UiButton v-else-if="target?.settings" :href="target.settings" variant="ghost" size="sm">Sharing settings</UiButton>
                </div>
                <p v-if="state.error" class="text-sm text-red-600 dark:text-red-400">{{ state.error }}</p>
            </div>
        </div>
    </UiModal>
</template>
