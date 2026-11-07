<script setup>
import { ask } from '@/confirm.js';
import { pageClass } from '@/ui.js';
import { computed, defineAsyncComponent, nextTick, onBeforeUnmount, onMounted, ref, shallowRef, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import UiInput from '@/Components/UiInput.vue';
import UiButton from '@/Components/UiButton.vue';
import UiBreadcrumbs from '@/Components/UiBreadcrumbs.vue';
import ReportArtifactPicker from '@/Components/ReportArtifactPicker.vue';
import ReportToolbar from '@/Components/ReportToolbar.vue';
import { artifactKind } from '@/report-artifacts.js';
import {
    ArrowTopRightOnSquareIcon, ChevronUpIcon, ChevronDownIcon, DocumentDuplicateIcon, TrashIcon, PlusIcon,
    DocumentTextIcon, PhotoIcon, EyeIcon, CheckCircleIcon, ExclamationTriangleIcon, ArrowPathIcon,
} from '@heroicons/vue/20/solid';

const ReportTextEditor = defineAsyncComponent(() => import('@/Components/ReportTextEditor.vue'));
const props = defineProps({ project: Object, report: Object, artifacts: Array, canPublish: Boolean });
const base = `/reports/${props.report.id}`;
const newId = () => globalThis.crypto?.randomUUID?.() || `block-${Date.now().toString(36)}-${Math.random().toString(36).slice(2)}`;
const form = useForm({
    title: props.report.title, description: props.report.description || '', slug: props.report.slug,
    visibility: props.report.visibility, password: '', remove_password: false,
    blocks: (props.report.draft_blocks || []).map(block => ({ ...block, id: block.id || newId() })),
});
const publication = useForm({});
const busy = computed(() => form.processing || publication.processing);
const fieldClass = 'mt-1.5 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100';
const artifactFor = block => props.artifacts.find(artifact => artifact.id === Number(block.document_id));
// The shared toolbar formats the text section that last had focus
const activeEditor = shallowRef(null);
const toolbar = ref(null);
function requestLink(editor) { activeEditor.value = editor; toolbar.value?.openLink(); }
function leaveText(event) {
    if (!event.target.closest('.tiptap, [data-toolbar]')) activeEditor.value = null;
}
const words = computed(() => form.blocks.reduce((total, block) => {
    if (block.type !== 'text') return total;
    const plain = block.text.replace(/<[^>]*>/g, ' ').replace(/&[a-z]+;/g, ' ').trim();
    return total + (plain ? plain.split(/\s+/).length : 0);
}, 0));
// Previews come from this site, so each frame can take the height of what it shows
function fitPreview(event) {
    const frame = event.target;
    try {
        const content = frame.contentDocument?.querySelector('main') || frame.contentDocument?.body;
        if (content) frame.style.height = `${Math.min(720, Math.max(160, content.scrollHeight + 4))}px`;
    } catch { /* a preview from another origin keeps its default height */ }
}
const embedUrl = block => {
    const artifact = artifactFor(block);
    return artifact?.url ? `${artifact.url}${block.version_number ? `/versions/${block.version_number}` : ''}?embed=1` : null;
};

// Sections
const focusBlock = ref(null);
function insertText(at) {
    const block = { id: newId(), type: 'text', format: 'html', text: '' };
    form.blocks.splice(at, 0, block);
    focusBlock.value = block.id;
}
const picking = ref(false);
const pickAt = ref(0);
const replacing = ref(null);
function pickArtifact(at, block = null) { pickAt.value = at; replacing.value = block; picking.value = true; }
function placeArtifact(documentId) {
    if (replacing.value) {
        Object.assign(replacing.value, { document_id: documentId, version_number: null, share_unlisted: false });
        return;
    }
    form.blocks.splice(pickAt.value, 0, { id: newId(), type: 'artifact', document_id: documentId, version_number: null, caption: '', share_unlisted: false });
}
function move(index, to) {
    if (to < 0 || to >= form.blocks.length) return;
    const [block] = form.blocks.splice(index, 1);
    form.blocks.splice(to, 0, block);
    return block;
}
function moveAndKeepFocus(index, to) {
    const block = move(index, to);
    if (block) nextTick(() => document.querySelector(`#block-${CSS.escape(block.id)} [data-handle]`)?.focus());
}
function duplicate(index) { form.blocks.splice(index + 1, 0, { ...JSON.parse(JSON.stringify(form.blocks[index])), id: newId() }); }
async function remove(index) {
    const block = form.blocks[index];
    const hasText = block.type === 'text' && block.text.replace(/<[^>]*>/g, '').trim();
    if (hasText && !await ask('Remove this text section?')) return;
    form.blocks.splice(index, 1);
}

// Drag to reorder, from the handle only so text selection still works
const draggable = ref(null);
const dragging = ref(null);
const dropAt = ref(null);
function dragOver(event, index) {
    if (dragging.value === null) return;
    const box = event.currentTarget.getBoundingClientRect();
    dropAt.value = event.clientY < box.top + box.height / 2 ? index : index + 1;
}
function drop() {
    if (dragging.value !== null && dropAt.value !== null) {
        const to = dropAt.value > dragging.value ? dropAt.value - 1 : dropAt.value;
        if (to !== dragging.value) move(dragging.value, to);
    }
    endDrag();
}
function endDrag() { dragging.value = null; dropAt.value = null; draggable.value = null; }

// The outline in the sidebar
const outline = computed(() => form.blocks.map((block, index) => {
    if (block.type === 'artifact') {
        const artifact = artifactFor(block);
        return { id: block.id, label: artifact ? artifact.title || artifact.slug : 'Missing artifact', kind: artifactKind(artifact?.kind).icon, heading: false };
    }
    const heading = block.text.match(/<h[23][^>]*>(.*?)<\/h[23]>/i);
    const plain = (heading ? heading[1] : block.text).replace(/<[^>]*>/g, ' ').replace(/&nbsp;/g, ' ').replace(/\s+/g, ' ').trim();
    return { id: block.id, label: plain ? (plain.length > 48 ? `${plain.slice(0, 46)}…` : plain) : `Text ${index + 1}`, kind: DocumentTextIcon, heading: Boolean(heading) };
}));
const jump = id => document.getElementById(`block-${id}`)?.scrollIntoView({ behavior: 'smooth', block: 'start' });

// Saving. Drafts save themselves shortly after a change; a new password waits for Save draft.
function payload(data) {
    const result = { title: data.title, description: data.description, slug: data.slug, visibility: data.visibility, blocks: data.blocks };
    if (data.remove_password) result.remove_password = true;
    else if (data.password.trim()) result.password = data.password;
    return result;
}
const lastSaved = ref(null);
const failed = ref(false);
let timer = null;
function save() {
    clearTimeout(timer);
    if (form.processing) { timer = setTimeout(save, 800); return; }
    const snapshot = { ...JSON.parse(JSON.stringify(form.data())), password: '', remove_password: false };
    form.transform(payload).patch(base, {
        preserveScroll: true, preserveState: true,
        onSuccess: () => { form.password = ''; form.remove_password = false; form.defaults(snapshot); lastSaved.value = new Date(); failed.value = false; },
        onError: () => { failed.value = true; },
    });
}
const passwordPending = computed(() => form.password.trim() !== '' || form.remove_password);
watch(() => JSON.stringify(payload({ ...form.data(), password: '', remove_password: false })), () => {
    if (!form.isDirty || passwordPending.value) return;
    clearTimeout(timer);
    timer = setTimeout(save, 1500);
});
function publish() {
    clearTimeout(timer);
    const snapshot = { ...JSON.parse(JSON.stringify(form.data())), password: '', remove_password: false };
    publication.transform(() => payload(form.data())).post(`${base}/publish`, {
        preserveScroll: true,
        onSuccess: () => { form.password = ''; form.remove_password = false; form.defaults(snapshot); lastSaved.value = new Date(); },
    });
}
const now = ref(Date.now());
let clock;
const warnBeforeLeaving = event => { if (form.isDirty) event.preventDefault(); };
onMounted(() => { clock = setInterval(() => { now.value = Date.now(); }, 15000); window.addEventListener('beforeunload', warnBeforeLeaving); });
onBeforeUnmount(() => { clearInterval(clock); clearTimeout(timer); window.removeEventListener('beforeunload', warnBeforeLeaving); });
const status = computed(() => {
    if (publication.processing) return { text: 'Publishing…', icon: ArrowPathIcon, tone: 'text-slate-500 dark:text-slate-400', spin: true };
    if (form.processing) return { text: 'Saving…', icon: ArrowPathIcon, tone: 'text-slate-500 dark:text-slate-400', spin: true };
    if (failed.value) return { text: 'Not saved. Check the messages above.', icon: ExclamationTriangleIcon, tone: 'text-amber-700 dark:text-amber-400' };
    if (form.isDirty) return { text: passwordPending.value ? 'Save to apply the password' : 'Unsaved changes', icon: null, tone: 'text-slate-500 dark:text-slate-400' };
    if (!lastSaved.value) return { text: 'All changes saved', icon: CheckCircleIcon, tone: 'text-slate-500 dark:text-slate-400' };
    const seconds = Math.max(0, Math.round((now.value - lastSaved.value) / 1000));
    return { text: seconds < 20 ? 'Saved' : `Saved ${Math.round(seconds / 60) || 1} min ago`, icon: CheckCircleIcon, tone: 'text-emerald-700 dark:text-emerald-400' };
});
const errors = computed(() => ({ ...form.errors, ...publication.errors }));

async function destroy() { if (await ask('Delete this report and all of its published versions? Its artifacts will remain in the project.')) router.delete(base); }
function date(value) { return value ? new Date(value).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' }) : ''; }
// Text boxes grow with what they hold, from the start and as you type
const fit = el => { el.style.height = 'auto'; el.style.height = `${el.scrollHeight}px`; };
const vAutosize = { mounted: el => { fit(el); el.addEventListener('input', () => fit(el)); }, updated: fit };
const visibilityText = { private: 'Only you can read published versions.', unlisted: 'Anyone with the link can read published versions.', public: 'Anyone can find and read published versions.' };
const controlClass = 'inline-flex size-7 items-center justify-center rounded-md text-slate-400 hover:bg-slate-100 hover:text-slate-700 focus-visible:outline-2 focus-visible:outline-sky-500 disabled:opacity-30 disabled:hover:bg-transparent dark:hover:bg-slate-800 dark:hover:text-slate-200';
</script>

<template>
    <Head :title="report.title" />
    <AppLayout bleed>
        <div class="min-h-full bg-slate-50 dark:bg-slate-950">
            <UiBreadcrumbs sticky :items="[{ label: project.name, href: `/projects/${project.slug}` }, { label: 'Reports', href: `/projects/${project.slug}/reports` }, { label: form.title || 'Untitled report' }]">
                <template #actions>
                        <p class="flex items-center gap-1.5 whitespace-nowrap text-xs" :class="status.tone" role="status" aria-live="polite" :title="status.text">
                            <component :is="status.icon" v-if="status.icon" class="size-4" :class="{ 'animate-spin': status.spin }" aria-hidden="true" /><span class="max-sm:sr-only">{{ status.text }}</span>
                        </p>
                        <UiButton :href="`${base}/preview`" external variant="ghost" size="sm"><EyeIcon aria-hidden="true" />Preview</UiButton>
                        <UiButton variant="ghost" size="sm" :disabled="busy" :save-shortcut="true" @click="save">Save draft</UiButton>
                        <UiButton size="sm" :disabled="busy || !canPublish" :title="canPublish ? 'Publish a new version readers can open' : 'Publishing reports is part of a paid plan'" @click="publish">{{ publication.processing ? 'Publishing…' : 'Publish' }}</UiButton>
                </template>
            </UiBreadcrumbs>

            <div :class="[pageClass, 'grid gap-8 lg:grid-cols-[minmax(0,1fr)_17rem]']">
                <div class="min-w-0">
                    <p v-if="!canPublish" class="mx-auto mb-4 max-w-3xl rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300">You can write and preview drafts. Publishing a report is part of a paid plan.</p>
                    <div v-if="Object.keys(errors).length" role="alert" class="mx-auto mb-4 max-w-3xl rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">
                        <p class="mb-1 font-semibold">Fix these before saving or publishing.</p>
                        <p v-for="(error, key) in errors" :key="key">{{ error }}</p>
                    </div>

                    <section aria-label="Report draft" class="mx-auto max-w-3xl rounded-2xl bg-white px-6 pb-10 shadow-sm ring-1 ring-slate-200 sm:px-14 sm:pb-14 dark:bg-slate-900 dark:ring-slate-800" @focusin="leaveText">
                        <div data-toolbar class="sticky top-0 z-10 sm:top-[calc(2.5rem+1px)] -mx-6 mb-10 rounded-t-2xl border-b border-slate-200 bg-white/95 px-3 py-1.5 backdrop-blur sm:-mx-14 sm:px-5 dark:border-slate-800 dark:bg-slate-900/95">
                            <ReportToolbar ref="toolbar" :editor="activeEditor" />
                        </div>
                        <h1 class="sr-only">Report editor</h1>
                        <label class="sr-only" for="report-title">Report title</label>
                        <textarea id="report-title" v-model="form.title" rows="1" placeholder="Report title" required
                            class="block w-full resize-none overflow-hidden border-0 bg-transparent p-0 text-3xl font-semibold leading-tight tracking-tight text-slate-900 placeholder:text-slate-300 focus:outline-none focus:ring-0 sm:text-4xl dark:text-white dark:placeholder:text-slate-600"
                            v-autosize @keydown.enter.prevent />
                        <label class="sr-only" for="report-introduction">Introduction</label>
                        <textarea id="report-introduction" v-model="form.description" rows="2" placeholder="Introduction (optional)"
                            class="mt-4 block w-full resize-none overflow-hidden border-0 bg-transparent p-0 text-lg leading-relaxed text-slate-600 placeholder:text-slate-300 focus:outline-none focus:ring-0 dark:text-slate-300 dark:placeholder:text-slate-600"
                            v-autosize />

                        <div class="mt-10 border-t border-slate-100 pt-8 dark:border-slate-800">
                            <div v-if="!form.blocks.length" class="rounded-xl border-2 border-dashed border-slate-200 px-6 py-12 text-center dark:border-slate-700">
                                <p class="font-medium text-slate-900 dark:text-white">Start with some text or an artifact</p>
                                <p class="mx-auto mt-1 max-w-sm text-sm text-slate-500 dark:text-slate-400">Write your findings, then place the figures, tables, and models that support them.</p>
                                <div class="mt-5 flex justify-center gap-2">
                                    <UiButton variant="ghost" size="sm" @click="insertText(0)"><DocumentTextIcon class="size-4" aria-hidden="true" />Add text</UiButton>
                                    <UiButton variant="ghost" size="sm" :disabled="!artifacts.length" @click="pickArtifact(0)"><PhotoIcon class="size-4" aria-hidden="true" />Add artifact</UiButton>
                                </div>
                                <p v-if="!artifacts.length" class="mt-3 text-xs text-slate-500 dark:text-slate-400">Publish an artifact to this project to place it in a report.</p>
                            </div>

                            <template v-for="(block, index) in form.blocks" :key="block.id">
                                <!-- Insert between sections -->
                                <div v-if="index > 0" class="group/insert relative flex h-8 items-center justify-center" :class="{ 'h-3': dragging !== null }">
                                    <div v-if="dropAt === index && dragging !== null" class="absolute inset-x-0 top-1/2 h-0.5 -translate-y-1/2 rounded bg-sky-500" />
                                    <div v-else class="absolute inset-x-0 top-1/2 h-px bg-transparent transition-colors group-hover/insert:bg-sky-200 group-focus-within/insert:bg-sky-200 dark:group-hover/insert:bg-sky-900" />
                                    <div v-if="dragging === null" class="relative flex gap-1 opacity-0 transition-opacity group-hover/insert:opacity-100 group-focus-within/insert:opacity-100">
                                        <button type="button" class="inline-flex items-center gap-1 rounded-full border border-slate-200 bg-white px-2.5 py-0.5 text-xs font-medium text-slate-600 shadow-sm hover:border-sky-300 hover:text-sky-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300" :aria-label="`Insert text before section ${index + 1}`" @click="insertText(index)"><PlusIcon class="size-3" aria-hidden="true" />Text</button>
                                        <button type="button" :disabled="!artifacts.length" class="inline-flex items-center gap-1 rounded-full border border-slate-200 bg-white px-2.5 py-0.5 text-xs font-medium text-slate-600 shadow-sm hover:border-sky-300 hover:text-sky-700 disabled:hidden dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300" :aria-label="`Insert an artifact before section ${index + 1}`" @click="pickArtifact(index)"><PlusIcon class="size-3" aria-hidden="true" />Artifact</button>
                                    </div>
                                </div>

                                <article :id="`block-${block.id}`" class="group/block relative scroll-mt-24" :class="{ 'opacity-40': dragging === index }" :aria-label="`Section ${index + 1}, ${block.type === 'text' ? 'text' : 'artifact'}`"
                                    :draggable="draggable === index" @dragstart="dragging = index; $event.dataTransfer.effectAllowed = 'move'" @dragover.prevent="dragOver($event, index)" @drop.prevent="drop" @dragend="endDrag">
                                    <div v-if="dropAt === form.blocks.length && index === form.blocks.length - 1 && dragging !== null" class="absolute inset-x-0 -bottom-2 h-0.5 rounded bg-sky-500" />
                                    <!-- Section controls: a drag handle on the left, actions on the right -->
                                    <div class="absolute -left-11 top-1 hidden flex-col items-center sm:flex">
                                        <button type="button" data-handle :aria-label="`Move section ${index + 1}. Use the up and down arrow keys, or drag.`" title="Drag to move, or use the arrow keys"
                                            class="flex h-8 w-6 cursor-grab items-center justify-center rounded-md text-slate-300 opacity-0 transition-opacity hover:bg-slate-100 hover:text-slate-600 focus-visible:opacity-100 focus-visible:outline-2 focus-visible:outline-sky-500 active:cursor-grabbing group-hover/block:opacity-100 group-focus-within/block:opacity-100 dark:text-slate-600 dark:hover:bg-slate-800 dark:hover:text-slate-300"
                                            @pointerdown="draggable = index" @pointerup="draggable = null"
                                            @keydown.up.prevent="moveAndKeepFocus(index, index - 1)" @keydown.down.prevent="moveAndKeepFocus(index, index + 1)">
                                            <svg viewBox="0 0 12 20" class="h-4 w-3" fill="currentColor" aria-hidden="true"><circle cx="3" cy="4" r="1.5"/><circle cx="9" cy="4" r="1.5"/><circle cx="3" cy="10" r="1.5"/><circle cx="9" cy="10" r="1.5"/><circle cx="3" cy="16" r="1.5"/><circle cx="9" cy="16" r="1.5"/></svg>
                                        </button>
                                    </div>
                                    <div class="mb-1.5 flex items-center justify-end gap-0.5 transition-opacity sm:absolute sm:-right-12 sm:top-0 sm:mb-0 sm:flex-col sm:opacity-0 sm:group-hover/block:opacity-100 sm:group-focus-within/block:opacity-100">
                                        <button type="button" :class="controlClass" :disabled="index === 0" :aria-label="`Move section ${index + 1} up`" title="Move up" @click="move(index, index - 1)"><ChevronUpIcon class="size-4" aria-hidden="true" /></button>
                                        <button type="button" :class="controlClass" :disabled="index === form.blocks.length - 1" :aria-label="`Move section ${index + 1} down`" title="Move down" @click="move(index, index + 1)"><ChevronDownIcon class="size-4" aria-hidden="true" /></button>
                                        <button type="button" :class="controlClass" :disabled="form.blocks.length >= 100" :aria-label="`Duplicate section ${index + 1}`" title="Duplicate" @click="duplicate(index)"><DocumentDuplicateIcon class="size-4" aria-hidden="true" /></button>
                                        <button type="button" :class="[controlClass, 'hover:text-red-700 dark:hover:text-red-400']" :aria-label="`Remove section ${index + 1}`" title="Remove" @click="remove(index)"><TrashIcon class="size-4" aria-hidden="true" /></button>
                                    </div>

                                    <ReportTextEditor v-if="block.type === 'text'" v-model="block.text" :label="`Section ${index + 1} text`" :autofocus="focusBlock === block.id" @focus="activeEditor = $event" @link="requestLink" />

                                    <figure v-else class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900">
                                        <div class="flex flex-wrap items-center gap-3 border-b border-slate-200 bg-slate-50 px-4 py-2.5 dark:border-slate-700 dark:bg-slate-950/40">
                                            <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-sky-100 text-sky-700 dark:bg-sky-950 dark:text-sky-300">
                                                <component :is="artifactKind(artifactFor(block)?.kind).icon" class="size-4" aria-hidden="true" />
                                            </span>
                                            <div class="min-w-0 flex-1">
                                                <p class="truncate text-sm font-medium text-slate-900 dark:text-white">{{ artifactFor(block) ? artifactFor(block).title || artifactFor(block).slug : 'This artifact is no longer available' }}</p>
                                                <p v-if="artifactFor(block)" class="text-xs text-slate-500 dark:text-slate-400">{{ artifactKind(artifactFor(block).kind).label }}</p>
                                            </div>
                                            <label v-if="artifactFor(block)" class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                                                <span class="sr-only sm:not-sr-only">Version</span>
                                                <select v-model="block.version_number" :aria-label="`Version of the artifact in section ${index + 1}`" class="rounded-md border border-slate-300 bg-white py-1 pl-2 pr-7 text-xs text-slate-800 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200">
                                                    <option :value="null">Latest when published</option>
                                                    <option v-for="version in artifactFor(block).versions" :key="version.number" :value="version.number">Version {{ version.number }}</option>
                                                </select>
                                            </label>
                                            <button type="button" class="text-xs font-medium text-sky-700 hover:underline dark:text-sky-400" @click="pickArtifact(index, block)">Change</button>
                                            <a v-if="artifactFor(block)" :href="artifactFor(block).url" target="_blank" rel="noopener" class="text-slate-400 hover:text-slate-700 dark:hover:text-slate-200" title="Open the artifact in a new tab"><ArrowTopRightOnSquareIcon class="size-4" aria-hidden="true" /><span class="sr-only">Open artifact</span></a>
                                        </div>
                                        <iframe v-if="embedUrl(block)" :src="embedUrl(block)" :title="`Preview of ${artifactFor(block).title || artifactFor(block).slug}`" loading="lazy"
                                            class="block h-80 w-full border-0 bg-white dark:bg-slate-950" @load="fitPreview" />
                                        <figcaption class="border-t border-slate-200 px-4 py-3 dark:border-slate-700">
                                            <label class="sr-only" :for="`caption-${block.id}`">Caption for section {{ index + 1 }}</label>
                                            <textarea :id="`caption-${block.id}`" v-model="block.caption" rows="1" placeholder="Caption (optional)"
                                                class="block w-full resize-none overflow-hidden border-0 bg-transparent p-0 text-sm leading-relaxed text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-0 dark:text-slate-300"
                                                v-autosize />
                                        </figcaption>
                                        <div v-if="artifactFor(block) && (artifactFor(block).password_protected || artifactFor(block).visibility === 'unlisted' || (form.visibility === 'public' && artifactFor(block).visibility !== 'public'))"
                                            class="space-y-2 border-t border-slate-200 bg-slate-50 px-4 py-3 text-xs dark:border-slate-700 dark:bg-slate-950/40">
                                            <p v-if="artifactFor(block).password_protected" class="text-slate-600 dark:text-slate-400">Readers will also need this artifact’s password.</p>
                                            <label v-if="artifactFor(block).visibility === 'unlisted'" class="flex items-start gap-2 text-slate-600 dark:text-slate-300"><input v-model="block.share_unlisted" type="checkbox" class="mt-0.5 rounded border-slate-300">Let this report’s readers open this unlisted artifact.</label>
                                            <p v-if="form.visibility === 'public' && artifactFor(block).visibility !== 'public'" class="flex items-start gap-1.5 text-amber-700 dark:text-amber-400"><ExclamationTriangleIcon class="mt-px size-4 shrink-0" aria-hidden="true" />A public report can only include public artifacts. Make this artifact public, or share the report as unlisted.</p>
                                        </div>
                                    </figure>
                                </article>
                            </template>

                            <div v-if="form.blocks.length" class="mt-6 flex flex-wrap items-center gap-2 border-t border-dashed border-slate-200 pt-6 dark:border-slate-700"
                                @dragover.prevent="dragging !== null && (dropAt = form.blocks.length)" @drop.prevent="drop">
                                <UiButton variant="ghost" size="sm" :disabled="form.blocks.length >= 100" @click="insertText(form.blocks.length)"><DocumentTextIcon class="size-4" aria-hidden="true" />Add text</UiButton>
                                <UiButton variant="ghost" size="sm" :disabled="!artifacts.length || form.blocks.length >= 100" @click="pickArtifact(form.blocks.length)"><PhotoIcon class="size-4" aria-hidden="true" />Add artifact</UiButton>
                                <span v-if="form.blocks.length >= 100" class="text-xs text-slate-500 dark:text-slate-400">A report can hold up to 100 sections.</span>
                            </div>
                        </div>
                    </section>
                </div>

                <aside class="space-y-5 lg:sticky lg:top-20 lg:self-start">
                    <section v-if="outline.length" aria-labelledby="outline-title" class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                        <div class="flex items-baseline justify-between gap-2">
                            <h2 id="outline-title" class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Outline</h2>
                            <span class="text-xs tabular-nums text-slate-400">{{ words.toLocaleString() }} {{ words === 1 ? 'word' : 'words' }}</span>
                        </div>
                        <ol class="mt-3 max-h-72 space-y-0.5 overflow-y-auto">
                            <li v-for="item in outline" :key="item.id">
                                <button type="button" class="flex w-full items-center gap-2 rounded-md px-2 py-1 text-left text-sm text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white" @click="jump(item.id)">
                                    <component :is="item.kind" class="size-3.5 shrink-0 text-slate-400" aria-hidden="true" />
                                    <span class="truncate" :class="{ 'font-medium text-slate-900 dark:text-white': item.heading }">{{ item.label }}</span>
                                </button>
                            </li>
                        </ol>
                    </section>

                    <section aria-labelledby="sharing-title" class="space-y-4 rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                        <h2 id="sharing-title" class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Sharing</h2>
                        <label class="block text-sm font-medium text-slate-800 dark:text-slate-200">Reader access
                            <select v-model="form.visibility" :class="fieldClass"><option value="private">Private, only you</option><option value="unlisted">Anyone with the link</option><option value="public">Public</option></select>
                        </label>
                        <p class="-mt-2 text-xs text-slate-500 dark:text-slate-400">{{ visibilityText[form.visibility] }} The change applies to every published version.</p>
                        <UiInput v-model="form.password" type="password" :label="report.password_protected ? 'New password' : 'Password (optional)'" autocomplete="new-password" description="At least 8 characters. Readers enter it to open the report." :error="form.errors.password" />
                        <label v-if="report.password_protected" class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300"><input v-model="form.remove_password" type="checkbox" class="rounded border-slate-300">Remove the password</label>
                        <UiInput v-model="form.slug" label="Short name" description="Used in the report’s address." :error="form.errors.slug" />
                    </section>

                    <section v-if="report.versions?.length" aria-labelledby="versions-title" class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                        <h2 id="versions-title" class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Published versions</h2>
                        <ol class="mt-3 space-y-2">
                            <li v-for="version in report.versions" :key="version.number" class="flex items-baseline justify-between gap-3 text-sm">
                                <a :href="version.url" target="_blank" rel="noopener" class="truncate text-sky-700 hover:underline dark:text-sky-400">Version {{ version.number }}</a>
                                <span class="shrink-0 text-xs text-slate-500 dark:text-slate-400">{{ date(version.created_at) }}</span>
                            </li>
                        </ol>
                    </section>

                    <details class="rounded-xl border border-slate-200 bg-white p-4 text-sm dark:border-slate-800 dark:bg-slate-900">
                        <summary class="cursor-pointer text-slate-500 dark:text-slate-400">Delete report</summary>
                        <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">This removes the draft and every published version. The artifacts stay in the project.</p>
                        <UiButton class="mt-3" variant="danger" size="sm" :disabled="busy" @click="destroy">Delete report</UiButton>
                    </details>
                </aside>
            </div>
        </div>
        <ReportArtifactPicker v-model="picking" :artifacts="artifacts" @choose="placeArtifact" />
    </AppLayout>
</template>
