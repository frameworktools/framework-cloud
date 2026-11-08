<script setup>
import { ask } from '@/confirm.js';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import UiInput from '@/Components/UiInput.vue';
import UiButton from '@/Components/UiButton.vue';
import UiModal from '@/Components/UiModal.vue';
import UiBreadcrumbs from '@/Components/UiBreadcrumbs.vue';
import ReportArtifactPicker from '@/Components/ReportArtifactPicker.vue';
import { artifactKind } from '@/report-artifacts.js';
import { inputClass, selectClass, textareaClass, pageClass } from '@/ui.js';
import UiSeparator from '@/Components/UiSeparator.vue';
import {
    ArrowTopRightOnSquareIcon, ArrowPathIcon, CheckCircleIcon, ExclamationTriangleIcon, EyeIcon, LockClosedIcon,
    PencilSquareIcon, PlusIcon, TrashIcon, LinkIcon, ClipboardDocumentIcon, ClockIcon,
} from '@heroicons/vue/20/solid';

const props = defineProps({ project: Object, collection: Object, items: Array, artifacts: Array });
const page = usePage();
const base = `/collections/${props.collection.id}`;
// Sharing and the entry dialog show their own errors; the rest appear above the list
const errors = computed(() => editing.value ? {} : Object.fromEntries(Object.entries(page.props.errors || {})
    .filter(([key]) => !['visibility', 'expires_at', 'password', 'remove_password'].includes(key))));
const fieldClass = `mt-2 ${selectClass}`;
const dateClass = `mt-2 ${inputClass}`;
const areaClass = `mt-2 ${textareaClass}`;
const iconButton = 'inline-flex size-8 items-center justify-center rounded-md text-slate-400 hover:bg-slate-100 hover:text-slate-700 focus-visible:outline-2 focus-visible:outline-sky-500 dark:hover:bg-slate-800 dark:hover:text-slate-200';
const artifactFor = item => props.artifacts.find(artifact => artifact.id === item.document_id);
const available = computed(() => props.artifacts.filter(artifact => !props.items.some(item => item.document_id === artifact.id)));
const day = value => new Date(value).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });

// Title and introduction save themselves a moment after you stop typing
const details = useForm({ title: props.collection.title, description: props.collection.description || '' });
const lastSaved = ref(null);
const failed = ref(false);
let timer = null;
function saveDetails() {
    clearTimeout(timer);
    if (!details.title.trim()) return;
    if (details.processing) { timer = setTimeout(saveDetails, 800); return; }
    const snapshot = { ...details.data() };
    details.patch(base, {
        preserveScroll: true, preserveState: true,
        onSuccess: () => { details.defaults(snapshot); lastSaved.value = new Date(); failed.value = false; },
        onError: () => { failed.value = true; },
    });
}
watch(() => [details.title, details.description], () => {
    if (!details.isDirty) return;
    clearTimeout(timer);
    timer = setTimeout(saveDetails, 1200);
});
const now = ref(Date.now());
let clock;
const status = computed(() => {
    if (details.processing) return { text: 'Saving…', icon: ArrowPathIcon, tone: 'text-slate-500 dark:text-slate-400', spin: true };
    if (failed.value) return { text: 'Not saved', icon: ExclamationTriangleIcon, tone: 'text-amber-700 dark:text-amber-400' };
    if (details.isDirty) return { text: details.title.trim() ? 'Unsaved changes' : 'Add a title to save', icon: null, tone: 'text-slate-500 dark:text-slate-400' };
    if (!lastSaved.value) return { text: 'All changes saved', icon: CheckCircleIcon, tone: 'text-slate-500 dark:text-slate-400' };
    const seconds = Math.max(0, Math.round((now.value - lastSaved.value) / 1000));
    return { text: seconds < 20 ? 'Saved' : `Saved ${Math.round(seconds / 60) || 1} min ago`, icon: CheckCircleIcon, tone: 'text-emerald-700 dark:text-emerald-400' };
});

// Sharing applies when you save it, since it changes who can open the link
const localDate = value => {
    const date = new Date(value);
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
};
const inDays = days => localDate(Date.now() + days * 86400000);
const sharing = useForm({
    visibility: props.collection.visibility, expires_on: props.collection.expires_at ? localDate(props.collection.expires_at) : '',
    password: '', remove_password: false,
});
const expiry = ref(props.collection.expires_at ? 'date' : 'never');
function chooseExpiry(choice) {
    expiry.value = choice;
    if (choice === 'never') sharing.expires_on = '';
    else if (choice === 'date') sharing.expires_on ||= inDays(14);
    else sharing.expires_on = inDays(Number(choice));
}
function saveSharing() {
    sharing.transform(data => {
        const result = { visibility: data.visibility, expires_at: data.expires_on ? new Date(`${data.expires_on}T23:59:59`).toISOString() : null };
        if (data.remove_password) result.remove_password = true;
        else if (data.password.trim()) result.password = data.password;
        return result;
    }).patch(base, {
        preserveScroll: true,
        onSuccess: () => { sharing.password = ''; sharing.remove_password = false; sharing.defaults(); },
    });
}
const sharingLabels = { private: 'Private', unlisted: 'Anyone with the link', public: 'Public' };
const visibilityText = {
    private: 'Only you can open the collection.',
    unlisted: 'Anyone with the link can open it. It isn’t listed anywhere.',
    public: 'Anyone can find and open it.',
};
const copied = ref(false);
async function copyLink() {
    await navigator.clipboard?.writeText(props.collection.url);
    copied.value = true;
    setTimeout(() => { copied.value = false; }, 2000);
}

// Whether readers see an entry, and why not
function notice(item) {
    const artifact = artifactFor(item);
    const shared = props.collection.visibility;
    if (!artifact) return { warn: true, text: 'This artifact was deleted. Readers don’t see this entry.' };
    if (item.version_number && !artifact.versions.some(version => version.number === item.version_number)) {
        return { warn: true, text: `Version ${item.version_number} was deleted. Readers see this entry as unavailable.` };
    }
    if (shared === 'public' && artifact.visibility !== 'public') return { warn: true, text: 'Hidden from readers. A public collection shows only public artifacts.' };
    if (shared === 'unlisted' && artifact.visibility === 'unlisted' && !item.share_unlisted) {
        return { warn: true, text: 'Hidden from readers until you share this unlisted artifact.', allow: true };
    }
    if (shared !== 'private' && !['public', 'unlisted'].includes(artifact.visibility)) return { warn: true, text: 'Hidden from readers. Only you can open this artifact.' };
    if (artifact.password_protected) return { warn: false, text: 'Readers also need this artifact’s password.' };
    return null;
}
const update = (item, changes) => router.patch(`${base}/items/${item.id}`, {
    version_number: item.version_number, caption: item.caption, share_unlisted: item.share_unlisted, ...changes,
}, { preserveScroll: true });

// Editing one entry
const editing = ref(null);
const entry = useForm({ version_number: null, caption: '', share_unlisted: false });
function edit(item) {
    entry.defaults({ version_number: item.version_number, caption: item.caption || '', share_unlisted: item.share_unlisted });
    entry.reset();
    entry.clearErrors();
    editing.value = item;
}
const editingArtifact = computed(() => editing.value && artifactFor(editing.value));
function saveEntry() {
    entry.patch(`${base}/items/${editing.value.id}`, { preserveScroll: true, onSuccess: () => { editing.value = null; } });
}
async function remove(item) {
    const artifact = artifactFor(item);
    if (item.caption && !await ask(`Remove “${artifact?.title || 'this artifact'}” and its caption from the collection?`)) return;
    router.delete(`${base}/items/${item.id}`, { preserveScroll: true });
}

// Adding: an unlisted artifact in an unlisted collection needs your go-ahead first
const picking = ref(false);
const consent = ref(null);
function add(id) {
    const artifact = props.artifacts.find(candidate => candidate.id === id);
    if (props.collection.visibility === 'unlisted' && artifact?.visibility === 'unlisted') { consent.value = artifact; return; }
    post(id, false);
}
function post(id, share) {
    router.post(`${base}/items`, { document_id: id, share_unlisted: share }, { preserveScroll: true, onSuccess: () => { consent.value = null; } });
}

// Order: drag an entry by its handle, or focus the handle and use the arrow keys
const order = ref([]);
watch(() => props.items, items => { order.value = items.map(item => ({ ...item })); }, { immediate: true });
function move(from, to, focus = false) {
    if (to < 0 || to >= order.value.length || from === to) return;
    const [item] = order.value.splice(from, 1);
    order.value.splice(to, 0, item);
    router.put(`${base}/order`, { items: order.value.map(entry => entry.id) }, { preserveScroll: true });
    if (focus) nextTick(() => document.querySelector(`[data-entry="${item.id}"] [data-handle]`)?.focus());
}
const draggable = ref(null);
const dragging = ref(null);
const dropAt = ref(null);
function dragOver(event, index) {
    if (dragging.value === null) return;
    const box = event.currentTarget.getBoundingClientRect();
    dropAt.value = event.clientY < box.top + box.height / 2 ? index : index + 1;
}
function drop() {
    if (dragging.value !== null && dropAt.value !== null) move(dragging.value, dropAt.value > dragging.value ? dropAt.value - 1 : dropAt.value);
    endDrag();
}
function endDrag() { dragging.value = null; dropAt.value = null; draggable.value = null; }

async function destroy() {
    if (await ask('Delete this collection? Its link stops working. The artifacts stay in the project.')) router.delete(base);
}
const fit = el => { el.style.height = 'auto'; el.style.height = `${el.scrollHeight}px`; };
const vAutosize = { mounted: el => { fit(el); el.addEventListener('input', () => fit(el)); }, updated: fit };
const warnBeforeLeaving = event => { if (details.isDirty || sharing.isDirty) event.preventDefault(); };
onMounted(() => { clock = setInterval(() => { now.value = Date.now(); }, 15000); window.addEventListener('beforeunload', warnBeforeLeaving); });
onBeforeUnmount(() => { clearInterval(clock); clearTimeout(timer); window.removeEventListener('beforeunload', warnBeforeLeaving); });
</script>

<template>
    <Head :title="details.title || collection.title" />
    <AppLayout bleed>
        <div class="min-h-full">
            <UiBreadcrumbs sticky :items="project.is_home ? [{ label: 'Collections', href: '/collections' }, { label: details.title || 'Untitled collection' }] : [{ label: project.name, href: `/projects/${project.slug}` }, { label: 'Collections', href: `/projects/${project.slug}/collections` }, { label: details.title || 'Untitled collection' }]">
                <template #actions>
                    <p class="flex items-center gap-1.5 whitespace-nowrap text-xs" :class="status.tone" role="status" aria-live="polite" :title="status.text">
                        <component :is="status.icon" v-if="status.icon" class="size-4" :class="{ 'animate-spin': status.spin }" aria-hidden="true" /><span class="max-sm:sr-only">{{ status.text }}</span>
                    </p>
                    <UiButton :href="collection.url" external variant="ghost" size="sm"><EyeIcon aria-hidden="true" />View</UiButton>
                </template>
            </UiBreadcrumbs>

            <div :class="[pageClass, 'grid gap-10 lg:grid-cols-[minmax(0,1fr)_18rem]']">
                <div class="min-w-0">
                    <div v-if="Object.keys(errors).length" role="alert" class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">
                        <p v-for="(error, key) in errors" :key="key">{{ error }}</p>
                    </div>

                    <h1 class="sr-only">Collection editor</h1>
                    <label class="sr-only" for="collection-title">Collection title</label>
                    <textarea id="collection-title" v-model="details.title" rows="1" placeholder="Collection title" required
                        class="block w-full resize-none overflow-hidden border-0 bg-transparent p-0 text-3xl font-semibold leading-tight tracking-tight text-slate-900 placeholder:text-slate-300 focus:outline-none focus:ring-0 dark:text-white dark:placeholder:text-slate-600"
                        v-autosize @keydown.enter.prevent />
                    <label class="sr-only" for="collection-introduction">Introduction</label>
                    <textarea id="collection-introduction" v-model="details.description" rows="1" placeholder="Introduction (optional)"
                        class="mt-3 block w-full resize-none overflow-hidden border-0 bg-transparent p-0 text-base leading-relaxed text-slate-600 placeholder:text-slate-400 focus:outline-none focus:ring-0 dark:text-slate-300 dark:placeholder:text-slate-500"
                        v-autosize />

                    <section class="mt-10" aria-labelledby="entries-title">
                        <div class="flex items-center justify-between gap-3">
                            <h2 id="entries-title" class="text-sm font-semibold text-slate-900 dark:text-white">Artifacts <span class="ml-1 font-normal tabular-nums text-slate-400">{{ order.length }}</span></h2>
                            <UiButton v-if="order.length" variant="ghost" size="sm" :disabled="!available.length" :title="available.length ? null : 'Every artifact in this project is already here'" @click="picking = true"><PlusIcon class="size-4" aria-hidden="true" />Add artifact</UiButton>
                        </div>

                        <div v-if="!order.length" class="mt-3 rounded-xl border-2 border-dashed border-slate-200 px-6 py-12 text-center dark:border-slate-700">
                            <p class="font-medium text-slate-900 dark:text-white">No artifacts yet</p>
                            <p class="mx-auto mt-1 max-w-sm text-sm text-slate-500 dark:text-slate-400">Add the figures, tables, models, and documents you want readers to see together.</p>
                            <UiButton class="mt-5" variant="ghost" size="sm" :disabled="!available.length" @click="picking = true"><PlusIcon class="size-4" aria-hidden="true" />Add artifact</UiButton>
                            <p v-if="!artifacts.length" class="mt-3 text-xs text-slate-500 dark:text-slate-400">Publish an artifact to this project first.</p>
                        </div>

                        <ol v-else class="mt-3 divide-y divide-slate-200 rounded-xl border border-slate-200 bg-white dark:divide-slate-800 dark:border-slate-800 dark:bg-slate-900">
                            <li v-for="(item, index) in order" :key="item.id" :data-entry="item.id"
                                class="group relative flex gap-3 px-3 py-3.5 sm:px-4" :class="{ 'opacity-40': dragging === index }"
                                :draggable="draggable === index" @dragstart="dragging = index; $event.dataTransfer.effectAllowed = 'move'" @dragover.prevent="dragOver($event, index)" @drop.prevent="drop" @dragend="endDrag">
                                <div v-if="dragging !== null && dropAt === index" class="absolute inset-x-0 -top-px h-0.5 bg-sky-500" />
                                <div v-if="dragging !== null && dropAt === order.length && index === order.length - 1" class="absolute inset-x-0 -bottom-px h-0.5 bg-sky-500" />
                                <button type="button" data-handle :aria-label="`Move ${artifactFor(item)?.title || 'entry'}, position ${index + 1} of ${order.length}. Use the up and down arrow keys, or drag.`" title="Drag to reorder, or use the arrow keys"
                                    class="-ml-1 flex w-5 shrink-0 cursor-grab items-center justify-center self-stretch rounded text-slate-300 hover:bg-slate-100 hover:text-slate-500 focus-visible:outline-2 focus-visible:outline-sky-500 active:cursor-grabbing dark:text-slate-600 dark:hover:bg-slate-800"
                                    @pointerdown="draggable = index" @pointerup="draggable = null"
                                    @keydown.up.prevent="move(index, index - 1, true)" @keydown.down.prevent="move(index, index + 1, true)">
                                    <svg viewBox="0 0 12 20" class="h-4 w-3" fill="currentColor" aria-hidden="true"><circle cx="3" cy="4" r="1.5"/><circle cx="9" cy="4" r="1.5"/><circle cx="3" cy="10" r="1.5"/><circle cx="9" cy="10" r="1.5"/><circle cx="3" cy="16" r="1.5"/><circle cx="9" cy="16" r="1.5"/></svg>
                                </button>
                                <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-sky-50 text-sky-700 dark:bg-sky-950 dark:text-sky-300">
                                    <component :is="artifactKind(artifactFor(item)?.kind).icon" class="size-5" aria-hidden="true" />
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="break-words font-medium text-slate-900 sm:truncate dark:text-white">{{ artifactFor(item) ? artifactFor(item).title || artifactFor(item).slug : 'Deleted artifact' }}</p>
                                    <p class="mt-0.5 flex flex-wrap items-center gap-x-1.5 text-xs text-slate-500 dark:text-slate-400">
                                        <template v-if="artifactFor(item)"><span>{{ artifactKind(artifactFor(item).kind).label }}</span><span aria-hidden="true">·</span></template>
                                        <span>{{ item.version_number ? `Version ${item.version_number}` : 'Latest version' }}</span>
                                        <template v-if="artifactFor(item)"><span aria-hidden="true">·</span><span>{{ sharingLabels[artifactFor(item).visibility] ?? artifactFor(item).visibility }}</span></template>
                                        <LockClosedIcon v-if="artifactFor(item)?.password_protected" class="size-3.5" aria-label="Password protected" />
                                    </p>
                                    <p v-if="item.caption" class="mt-1.5 line-clamp-2 whitespace-pre-line text-sm text-slate-600 dark:text-slate-300">{{ item.caption }}</p>
                                    <p v-if="notice(item)" class="mt-1.5 flex items-start gap-1.5 text-xs" :class="notice(item).warn ? 'text-amber-700 dark:text-amber-400' : 'text-slate-500 dark:text-slate-400'">
                                        <ExclamationTriangleIcon v-if="notice(item).warn" class="mt-px size-3.5 shrink-0" aria-hidden="true" />
                                        <span>{{ notice(item).text }}
                                            <button v-if="notice(item).allow" type="button" class="font-medium underline underline-offset-2 hover:text-amber-900 dark:hover:text-amber-200" @click="update(item, { share_unlisted: true })">Share it</button>
                                        </span>
                                    </p>
                                </div>
                                <div class="flex shrink-0 items-start gap-0.5 sm:opacity-60 sm:transition-opacity sm:group-hover:opacity-100 sm:group-focus-within:opacity-100">
                                    <button type="button" :class="iconButton" :aria-label="`Edit ${artifactFor(item)?.title || 'entry'}`" title="Version and caption" @click="edit(item)"><PencilSquareIcon class="size-4" aria-hidden="true" /></button>
                                    <a v-if="artifactFor(item)" :href="artifactFor(item).url + (item.version_number ? `/versions/${item.version_number}` : '')" target="_blank" rel="noopener" :class="iconButton" title="Open the artifact"><ArrowTopRightOnSquareIcon class="size-4" aria-hidden="true" /><span class="sr-only">Open {{ artifactFor(item).title }}</span></a>
                                    <button type="button" :class="[iconButton, 'hover:text-red-700 dark:hover:text-red-400']" :aria-label="`Remove ${artifactFor(item)?.title || 'entry'}`" title="Remove from the collection" @click="remove(item)"><TrashIcon class="size-4" aria-hidden="true" /></button>
                                </div>
                            </li>
                        </ol>
                    </section>
                </div>

                <aside class="min-w-0 space-y-8 lg:sticky lg:top-20 lg:self-start">
                    <form aria-labelledby="sharing-title" class="space-y-4" @submit.prevent="saveSharing">
                        <h2 id="sharing-title" class="text-base font-medium text-slate-900 dark:text-white">Sharing</h2>
                        <label class="block text-sm font-medium text-slate-800 dark:text-slate-200">Reader access
                            <select v-model="sharing.visibility" :class="fieldClass"><option value="private">Private, only you</option><option value="unlisted">Anyone with the link</option><option value="public">Public</option></select>
                        </label>
                        <p class="-mt-2 text-xs text-slate-500 dark:text-slate-400">{{ visibilityText[sharing.visibility] }} Artifacts keep their own passwords.</p>
                        <p v-if="sharing.errors.visibility" class="-mt-2 text-xs text-red-700 dark:text-red-400">{{ sharing.errors.visibility }}</p>

                        <div v-if="sharing.visibility !== 'private'">
                            <label class="block text-sm font-medium text-slate-800 dark:text-slate-200">Link expires
                                <select :value="expiry" :class="fieldClass" @change="chooseExpiry($event.target.value)">
                                    <option value="never">Never</option><option value="1">After 1 day</option><option value="7">After 7 days</option>
                                    <option value="30">After 30 days</option><option value="90">After 90 days</option><option value="date">On a date…</option>
                                </select>
                            </label>
                            <label v-if="expiry === 'date'" class="mt-2 block text-sm"><span class="sr-only">Expiry date</span>
                                <input v-model="sharing.expires_on" type="date" :min="inDays(0)" required :class="dateClass">
                            </label>
                            <p v-if="sharing.expires_on" class="mt-1.5 text-xs text-slate-500 dark:text-slate-400">Readers can open it through the end of {{ day(`${sharing.expires_on}T12:00:00`) }}. You can still open it afterward.</p>
                            <p v-if="sharing.errors.expires_at" class="mt-1.5 text-xs text-red-700 dark:text-red-400">{{ sharing.errors.expires_at }}</p>
                        </div>

                        <UiInput v-model="sharing.password" type="password" :label="collection.protected ? 'New password' : 'Password (optional)'" autocomplete="new-password" description="At least 8 characters." :error="sharing.errors.password" />
                        <label v-if="collection.protected" class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300"><input v-model="sharing.remove_password" type="checkbox" class="rounded border-slate-300">Remove the password</label>
                        <UiButton type="submit" variant="ghost" :disabled="sharing.processing">{{ sharing.processing ? 'Saving…' : 'Save sharing' }}</UiButton>
                    </form>

                    <div v-if="collection.visibility !== 'private'" class="space-y-2 border-t border-slate-800/10 pt-6 dark:border-white/10">
                        <p class="text-sm font-medium text-slate-800 dark:text-white">Collection link</p>
                        <div class="flex items-center gap-1 rounded-lg bg-slate-100 py-1 pl-3 pr-1 dark:bg-slate-800">
                            <a :href="collection.url" target="_blank" rel="noopener" class="min-w-0 flex-1 truncate font-mono text-xs text-slate-700 hover:underline dark:text-slate-300">{{ collection.url.replace(/^https?:\/\//, '') }}</a>
                            <button type="button" class="inline-flex shrink-0 items-center gap-1 rounded-md px-2 py-1 text-xs font-medium text-slate-600 hover:bg-white dark:text-slate-300 dark:hover:bg-slate-700" @click="copyLink"><ClipboardDocumentIcon class="size-3.5" aria-hidden="true" />{{ copied ? 'Copied' : 'Copy' }}</button>
                        </div>
                        <p v-if="collection.expires_at" class="flex items-center gap-1.5 text-xs" :class="collection.expired ? 'text-amber-700 dark:text-amber-400' : 'text-slate-500 dark:text-slate-400'">
                            <ClockIcon class="size-3.5" aria-hidden="true" />{{ collection.expired ? `Expired ${day(collection.expires_at)}. Readers can’t open it.` : `Works until ${day(collection.expires_at)}` }}
                        </p>
                        <p v-if="collection.protected" class="flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400"><LockClosedIcon class="size-3.5" aria-hidden="true" />Password protected</p>
                    </div>

                    <div class="border-t border-slate-800/10 pt-6 dark:border-white/10">
                        <UiButton variant="subtle" size="sm" class="-ml-3 hover:text-red-700! dark:hover:text-red-400!" @click="destroy"><TrashIcon aria-hidden="true" />Delete collection</UiButton>
                    </div>
                </aside>
            </div>
        </div>

        <ReportArtifactPicker v-model="picking" :artifacts="available" @choose="add" />

        <UiModal :model-value="Boolean(consent)" title="Unlisted artifact sharing" @update:model-value="value => { if (!value) consent = null; }">
            <p class="text-sm text-slate-600 dark:text-slate-300">“{{ consent?.title || consent?.slug }}” is unlisted. Readers of this collection will be able to open its link.</p>
            <div class="flex justify-end gap-2">
                <UiButton variant="ghost" size="sm" @click="post(consent.id, false)">Add without sharing</UiButton>
                <UiButton size="sm" @click="post(consent.id, true)">Add and share</UiButton>
            </div>
        </UiModal>

        <UiModal :model-value="Boolean(editing)" :title="editingArtifact ? editingArtifact.title || editingArtifact.slug : 'Edit entry'" @update:model-value="value => { if (!value) editing = null; }">
            <form class="space-y-4" @submit.prevent="saveEntry">
                <label v-if="editingArtifact" class="block text-sm font-medium text-slate-800 dark:text-slate-200">Version
                    <select v-model="entry.version_number" :class="fieldClass">
                        <option :value="null">Always the latest version</option>
                        <option v-for="version in editingArtifact.versions" :key="version.number" :value="version.number">Version {{ version.number }}<template v-if="version.created_at">, {{ day(version.created_at) }}</template></option>
                    </select>
                </label>
                <p v-if="editingArtifact" class="-mt-2 text-xs text-slate-500 dark:text-slate-400">Choose a version to keep showing it after you publish newer ones.</p>
                <label class="block text-sm font-medium text-slate-800 dark:text-slate-200">Caption
                    <textarea v-model="entry.caption" rows="3" placeholder="Caption" :class="areaClass" />
                </label>
                <p v-if="entry.errors.caption || entry.errors.version_number" class="text-xs text-red-700 dark:text-red-400">{{ entry.errors.caption || entry.errors.version_number }}</p>
                <label v-if="editingArtifact?.visibility === 'unlisted'" class="flex items-start gap-2 text-sm text-slate-700 dark:text-slate-300"><input v-model="entry.share_unlisted" type="checkbox" class="mt-0.5 rounded border-slate-300">Let readers of an unlisted collection open this unlisted artifact.</label>
                <p v-if="entry.errors.share_unlisted" class="text-xs text-red-700 dark:text-red-400">{{ entry.errors.share_unlisted }}</p>
                <div class="flex justify-end gap-2 pt-1">
                    <UiButton variant="ghost" size="sm" @click="editing = null">Cancel</UiButton>
                    <UiButton type="submit" size="sm" :disabled="entry.processing">Save</UiButton>
                </div>
            </form>
        </UiModal>
    </AppLayout>
</template>
