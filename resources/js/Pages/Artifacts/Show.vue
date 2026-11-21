<script setup>
import UiRadioCards from '@/Components/UiRadioCards.vue';
import ShareQrBody from '@/Components/ShareQrBody.vue';
import ShareDialog from '@/Components/ShareDialog.vue';
import { ask } from '@/confirm.js';
import { pageClass } from '@/ui.js';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import UiBreadcrumbs from '@/Components/UiBreadcrumbs.vue';
import UiButton from '@/Components/UiButton.vue';
import UiInput from '@/Components/UiInput.vue';
import UiTextarea from '@/Components/UiTextarea.vue';
import UiSwitch from '@/Components/UiSwitch.vue';
import UiSelect from '@/Components/UiSelect.vue';
import UiTabs from '@/Components/UiTabs.vue';
import UiTable from '@/Components/UiTable.vue';
import UiSection from '@/Components/UiSection.vue';
import UiSeparator from '@/Components/UiSeparator.vue';
import UiLink from '@/Components/UiLink.vue';
import UiCallout from '@/Components/UiCallout.vue';
import { artifactKind } from '@/report-artifacts.js';
import { EyeIcon, ArrowTopRightOnSquareIcon, LockClosedIcon, TrashIcon, CheckIcon, PencilSquareIcon, ChevronDownIcon } from '@heroicons/vue/20/solid';
import { ClipboardDocumentIcon, CodeBracketIcon, Square2StackIcon, Square3Stack3DIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ share: { type: Object, default: null }, artifact: Object, project: Object, versions: Array, usedIn: Array, mergeSources: Array, handle: String, linkedProjects: { type: Array, default: () => [] }, linkableProjects: { type: Array, default: () => [] } });

// Projects this artifact is listed in besides its own
const linking = useForm({ project_id: '' });
const link = () => linking.post(`/artifacts/${props.artifact.id}/projects`, { preserveScroll: true, onSuccess: () => linking.reset() });
const unlink = (linked) => router.delete(`/artifacts/${props.artifact.id}/projects/${linked.id}`, { preserveScroll: true });
const breadcrumbItems = computed(() => {
    const collections = props.usedIn.filter(item => item.type === 'Collection').map(item => ({ label: item.title, href: item.href, icon: 'collection' }));
    return [{ label: 'Artifacts', href: '/published' }, ...(collections.length === 1 ? collections : collections.length ? [{ links: collections }] : [])];
});
const external = computed(() => props.artifact.driver && props.artifact.driver !== 'cloud');
const hasUrl = computed(() => !external.value || !!props.artifact.external_url);
const base = `/published/${props.artifact.id}`;
const kind = computed(() => artifactKind(props.artifact.kind));
// R and Python send models, tests, and data frames as a JSON summary they name themselves
// (model.json); that name means nothing to the person who published, so it isn't shown.
const chosenFile = computed(() => props.artifact.original_filename
    && !(['model', 'test', 'table', 'code_snippet', 'dag'].includes(props.artifact.kind) && /\.json$/i.test(props.artifact.original_filename)));

// The open tab is kept in the address, so a reload or a shared link lands on it
const tabs = computed(() => [
    { label: 'Overview', value: 'overview' },
    { label: 'Versions', value: 'versions', count: props.versions.length },
    { label: 'Settings', value: 'settings' },
]);
const initial = new URLSearchParams(typeof window !== 'undefined' ? window.location.search : '').get('tab');
const tab = ref(tabs.value.some(t => t.value === initial) ? initial : 'overview');
watch(tab, value => {
    const url = new URL(window.location.href);
    if (value === 'overview') url.searchParams.delete('tab'); else url.searchParams.set('tab', value);
    window.history.replaceState(window.history.state, '', url);
});

const size = (bytes) => {
    if (bytes === null || bytes === undefined) return '—';
    const units = ['B', 'KB', 'MB', 'GB'];
    let i = 0;
    while (bytes >= 1024 && i < units.length - 1) { bytes /= 1024; i++; }
    return `${bytes.toFixed(i ? 1 : 0)} ${units[i]}`;
};
const date = (value) => value ? new Date(value).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' }) : '—';
// The share dialog, opened on its Embed view: the link and HTML for other sites
const shareTarget = ref(null);
const openEmbed = () => { shareTarget.value = { type: 'artifact', id: props.artifact.id, title: props.artifact.title, view: 'embed', onSettings: () => { tab.value = 'overview'; } }; };

const copied = ref(null);
async function copy(key, url) {
    await navigator.clipboard?.writeText(url);
    copied.value = key;
    setTimeout(() => { copied.value = null; }, 1500);
}

// The preview shows what readers see, sized to its content
const previewUrl = `${props.artifact.private_url}?embed=1`;
// The frame grows to the full height of what it shows and follows it as it changes
// (sorting, paging, a chart drawing), so the page scrolls, never the frame.
let observer = null;
function fit(event) {
    const frame = event.target;
    observer?.disconnect();
    try {
        const doc = frame.contentDocument;
        if (!doc) return;
        // The reader fills its window and scrolls inside <main>, so measure what <main> holds
        const main = doc.querySelector('main') || doc.body;
        const resize = async () => {
            const content = Math.max(main.offsetTop + main.scrollHeight, main === doc.body ? 0 : doc.body.scrollHeight - main.clientHeight + main.scrollHeight);
            // At least the window's height, so the preview takes the space it has; taller content grows the page
            frame.style.height = `${Math.max(content, window.innerHeight - 160, 512)}px`;
            frame.setAttribute('scrolling', 'no');
        };
        resize();
        observer = new ResizeObserver(resize);
        observer.observe(main);
        for (const child of main.children) observer.observe(child);
    } catch { /* a frame from another origin keeps its default height */ }
}
onBeforeUnmount(() => observer?.disconnect());

// Title and description edit in place and save when the field is left
const titleDraft = ref(props.artifact.title);
const titleInput = ref(null);
const descriptionInput = ref(null);
const descriptionDraft = ref(props.artifact.description || '');
const detailStatus = ref('');
function saveDetail(field, value, saved) {
    if (value === saved) return;
    detailStatus.value = 'Saving…';
    router.patch(base, { [field]: value }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => { detailStatus.value = 'Saved'; setTimeout(() => { if (detailStatus.value === 'Saved') detailStatus.value = ''; }, 2000); },
        onError: (errors) => { detailStatus.value = errors[field] || 'Could not save.'; },
    });
}
function saveTitle() {
    const value = titleDraft.value.trim();
    if (value === '') { titleDraft.value = props.artifact.title; return; }
    titleDraft.value = value;
    saveDetail('title', value, props.artifact.title);
}
const saveDescription = () => saveDetail('description', descriptionDraft.value.trim() || null, props.artifact.description || null);

// Embed contents: what other sites receive. The artifact's own page shows everything. Each switch saves as it flips.
const display = ref(Object.fromEntries((props.artifact.display || []).map(option => [option.key, option.value])));
const displayStatus = ref('');
let displayTimer = null;
function saveDisplay(key, value) {
    display.value[key] = value;
    displayStatus.value = 'Saving…';
    router.patch(base, { display: { [key]: value } }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => { displayStatus.value = 'Saved'; },
        onError: (errors) => { display.value[key] = !value; displayStatus.value = errors.display || 'Could not save.'; },
        onFinish: () => { clearTimeout(displayTimer); displayTimer = setTimeout(() => { if (displayStatus.value === 'Saved') displayStatus.value = ''; }, 2000); },
    });
}
// A public artifact's short link is the one to hand out; an unlisted one shares its own link, which can't be guessed
const shareLink = computed(() => props.share?.short_url ?? props.artifact.url);
const embeddable = computed(() => props.artifact.visibility !== 'private' && !props.artifact.password_protected);

// Access: who can open it. Saves as it changes.
const access = ref(props.artifact.visibility ?? (props.artifact.readable ? 'public' : 'unlisted'));
const accessStatus = ref('');
function saveAccess(value) {
    const previous = access.value;
    access.value = value;
    accessStatus.value = 'Saving…';
    router.patch(base, { visibility: value }, {
        preserveScroll: true,
        onSuccess: () => { accessStatus.value = 'Saved'; setTimeout(() => { if (accessStatus.value === 'Saved') accessStatus.value = ''; }, 2000); },
        onError: (errors) => { access.value = previous; accessStatus.value = errors.visibility || 'Could not save.'; },
    });
}

// Password
const sharing = useForm({ visibility: props.artifact.visibility ?? (props.artifact.readable ? 'public' : 'unlisted'), password: '', removePassword: false });
const passwordChanging = ref(false);
async function saveSharing() {
    sharing.transform(data => {
        const payload = {};
        if (data.removePassword) payload.password = null;
        else if (data.password) payload.password = data.password;
        return payload;
    }).patch(base, {
        preserveScroll: true,
        onSuccess: () => { sharing.password = ''; sharing.removePassword = false; passwordChanging.value = false; sharing.defaults(); },
    });
}

// Merging and removing
const merge = useForm({ source_id: '' });
async function mergeIn() {
    const source = props.mergeSources.find(s => s.id === Number(merge.source_id));
    if (!source || !await ask(`Append the versions of “${source.title}” to “${props.artifact.title}”? The source keeps its own links and versions.`)) return;
    merge.post(`${base}/merge`, { preserveScroll: true, onSuccess: () => merge.reset() });
}
async function destroyVersion(version) {
    const last = props.versions.length === 1;
    if (external.value) {
        if (await ask(`Remove version ${version.number} from Framework Cloud? Files at the publishing destination stay available.`)) router.delete(`${base}/versions/${version.number}?then=list`, { preserveScroll: true });
        return;
    }
    if (!await ask(last ? `Delete version ${version.number}? It is the only version, so “${props.artifact.title}” is taken down. You can restore it from the Trash.` : `Delete version ${version.number}? Its links stop working. You can restore it from the Trash.`)) return;
    router.delete(`${base}/versions/${version.number}?then=list`, { preserveScroll: true });
}
async function destroy() {
    if (external.value) {
        if (await ask(`Remove “${props.artifact.title}” from Framework Cloud? Files at the publishing destination stay available.`)) router.delete(`${base}?then=list`);
        return;
    }
    if (await ask(`Take down “${props.artifact.title}”? Every version and link stops working for everyone. You can restore it from the Trash.`)) router.delete(`${base}?then=list`);
}
const sharingText = { unlisted: 'Anyone with the link', public: 'Public', private: 'Private' };
</script>

<template>
    <Head :title="artifact.title" />
    <AppLayout bleed>
        <div class="min-h-full">

            <div :class="pageClass">
                <UiBreadcrumbs inline trailing :items="breadcrumbItems" class="mb-3" />
                <header class="flex flex-wrap items-start gap-x-6 gap-y-4">
                    <div class="min-w-0 flex-1 basis-72">
                        <h1 class="sr-only">{{ artifact.title }}</h1>
                        <label for="artifact-title" class="sr-only">Title</label>
                        <div class="group/title -mx-2 -my-1 flex items-center gap-1">
                        <input id="artifact-title" ref="titleInput" v-model="titleDraft" maxlength="200" :size="Math.max(titleDraft.length, 4)"
                            class="min-w-0 max-w-full rounded-lg [field-sizing:content] border-0 bg-transparent px-2 py-1 text-2xl font-semibold tracking-tight text-slate-900 outline-none hover:bg-slate-800/5 focus:bg-white focus:ring-2 focus:ring-sky-600 dark:text-white dark:hover:bg-white/5 dark:focus:bg-slate-900"
                            @keydown.enter.prevent="$event.target.blur()" @keydown.escape="titleDraft = artifact.title; $event.target.blur()" @blur="saveTitle" />
                        <UiButton variant="subtle" size="xs" square aria-label="Edit the title" class="shrink-0 text-slate-400 hover:text-slate-700 dark:hover:text-slate-200" @click="titleInput.focus(); titleInput.select()"><PencilSquareIcon aria-hidden="true" /></UiButton>
                        </div>
                        <p class="mt-1.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-slate-500 dark:text-slate-400">
                            <span>{{ kind.label }}</span><span aria-hidden="true">·</span>
                            <span>{{ sharingText[artifact.visibility] || artifact.visibility }}</span>
                            <LockClosedIcon v-if="artifact.password_protected" class="size-3.5" aria-label="Password protected" />
                            <span aria-hidden="true">·</span>
<template v-for="(listed, index) in [...(project.is_home ? [] : [project]), ...linkedProjects]" :key="listed.id">
                                <span v-if="index" aria-hidden="true" class="-ml-1">,</span>
                                <Link :href="`/projects/${listed.slug}/published`" class="inline-flex min-w-0 max-w-full items-center gap-1.5 hover:text-slate-800 hover:underline dark:hover:text-white">
                                    <Square3Stack3DIcon v-if="!index" class="size-4 shrink-0 text-slate-400" aria-hidden="true" /><span class="truncate">{{ listed.name }}</span>
                                </Link>
                            </template>
                            <span v-if="project.is_home && !linkedProjects.length">No project</span>
                            <span v-if="detailStatus" aria-live="polite" :class="detailStatus === 'Saved' ? 'text-emerald-700 dark:text-emerald-400' : ''">· {{ detailStatus }}</span>
                        </p>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <UiButton v-if="hasUrl" variant="primary" :href="artifact.url" external><ArrowTopRightOnSquareIcon aria-hidden="true" />View</UiButton>
                    </div>
                </header>

                <UiCallout v-if="artifact.merged_into" variant="warning" class="mt-6">
                    This artifact was merged into <UiLink :href="`/artifacts/${artifact.merged_into.id}`">{{ artifact.merged_into.title }}</UiLink>. Its links still work; publish new versions there.
                </UiCallout>

                <UiTabs v-model="tab" :tabs="tabs" label="Artifact sections" class="mt-8" />

                <!-- Overview -->
                <div v-if="tab === 'overview'" class="pt-8 lg:grid lg:grid-cols-[minmax(0,1fr)_20rem] lg:items-start lg:gap-10">
                    <!-- Sharing is what people come here for, so it sits beside the preview, not behind a tab -->
                    <aside v-if="!external" class="mb-10 space-y-6 lg:sticky lg:top-6 lg:order-2 lg:mb-0" aria-label="Share">
                        <h2 class="text-base font-medium text-slate-900 dark:text-white">Share</h2>
                        <div class="space-y-2">
                            <UiSelect :model-value="access" label="Access" @update:model-value="saveAccess">
                                <option value="private">Private, only you</option>
                                <option value="unlisted">Anyone with the link</option>
                                <option value="public">Public, with a readable link</option>
                            </UiSelect>
                            <p class="text-xs" :class="accessStatus === 'Saved' ? 'text-emerald-700 dark:text-emerald-400' : 'text-slate-500 dark:text-slate-400'" aria-live="polite">
                                <template v-if="accessStatus">{{ accessStatus }}</template>
                                <template v-else-if="artifact.password_protected"><LockClosedIcon class="inline size-3.5 align-[-2px]" aria-hidden="true" /> Password protected. <button type="button" class="underline underline-offset-2" @click="tab = 'settings'">Change it in Settings</button>.</template>
                            </p>
                        </div>
                        <div class="space-y-2">
                            <p class="text-sm font-medium text-slate-800 dark:text-white">Link</p>
                            <div class="flex items-center gap-2">
                                <code class="min-w-0 flex-1 truncate rounded-lg bg-slate-800/5 px-3 py-2 text-xs text-slate-700 dark:bg-white/10 dark:text-slate-300">{{ shareLink }}</code>
                                <UiButton variant="ghost" size="sm" @click="copy('share', shareLink)">{{ copied === 'share' ? 'Copied' : 'Copy' }}</UiButton>
                            </div>
                            <p v-if="access === 'public' && !artifact.readable_url && !handle" class="text-xs text-slate-500 dark:text-slate-400">A readable link needs a handle. <UiLink href="/account/account">Set one</UiLink>.</p>
                        </div>
                        <div class="space-y-2">
                            <p class="text-sm font-medium text-slate-800 dark:text-white">Embed</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ embeddable ? 'Paste the link into a blog or site that takes Framework embeds, or copy the HTML.' : 'Embeds work when anyone with the link can open it and it has no password.' }}</p>
                            <UiButton variant="ghost" size="sm" :disabled="!embeddable" @click="openEmbed"><CodeBracketIcon class="text-slate-500 dark:text-slate-400" aria-hidden="true" />Get embed code</UiButton>
                        </div>
                        <section v-if="artifact.display?.length" class="space-y-4 border-t border-slate-800/10 pt-6 dark:border-white/15" aria-label="Embed contents">
                            <div>
                                <h3 class="text-sm font-medium text-slate-800 dark:text-white">Embed contents</h3>
                                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">What embeds on other sites show. The artifact’s own page shows everything.</p>
                            </div>
                            <UiSwitch v-for="option in artifact.display" :key="option.key" :model-value="display[option.key]" :label="option.label" :description="option.help" @update:model-value="value => saveDisplay(option.key, value)" />
                            <p class="h-4 text-xs" :class="displayStatus === 'Saved' ? 'text-emerald-700 dark:text-emerald-400' : 'text-slate-500 dark:text-slate-400'" aria-live="polite">{{ displayStatus }}</p>
                        </section>
                        <details open class="group border-t border-slate-800/10 pt-4 dark:border-white/15">
                            <summary class="flex cursor-pointer list-none items-center justify-between text-sm font-medium text-slate-800 dark:text-white [&::-webkit-details-marker]:hidden">
                                QR code
                                <ChevronDownIcon class="size-4 text-slate-400 transition-transform group-open:rotate-180" aria-hidden="true" />
                            </summary>
                            <div class="mt-3 space-y-3">
                                <p class="text-xs text-slate-500 dark:text-slate-400">For a slide or handout. The short link is easy to type.</p>
                                <ShareQrBody v-if="share" :share="share" />
                                <p v-else class="text-xs text-slate-500 dark:text-slate-400">Private artifacts have no QR code. Choose Anyone with the link or Public above.</p>
                            </div>
                        </details>
                    </aside>
                    <div class="min-w-0 lg:order-1">
                    <UiCallout v-if="external" class="mb-6" :heading="artifact.location">Files and access are managed at the publishing destination. Framework Cloud stores publication metadata only.</UiCallout>
                    <label for="artifact-description" class="sr-only">Description</label>
                    <div class="-mx-2 mb-6 flex max-w-2xl items-start gap-1">
                    <textarea id="artifact-description" ref="descriptionInput" v-model="descriptionDraft" rows="1" maxlength="10000" placeholder="Add a description"
                        class="block min-w-40 max-w-full resize-none rounded-lg border-0 bg-transparent px-2 py-1 text-sm text-slate-600 outline-none [field-sizing:content] placeholder:text-slate-400 hover:bg-slate-800/5 focus:bg-white focus:ring-2 focus:ring-sky-600 dark:text-slate-300 dark:hover:bg-white/5 dark:focus:bg-slate-900"
                        @blur="saveDescription" />
                    <UiButton variant="subtle" size="xs" square aria-label="Edit the description" class="mt-0.5 shrink-0 text-slate-400 hover:text-slate-700 dark:hover:text-slate-200" @click="descriptionInput.focus()"><PencilSquareIcon aria-hidden="true" /></UiButton>
                    </div>
                    <dl class="flex flex-wrap gap-x-10 gap-y-4 text-sm">
                        <div><dt class="text-slate-500 dark:text-slate-400">Versions</dt><dd class="mt-0.5 font-medium text-slate-800 dark:text-white">{{ versions.length }}</dd></div>
                        <div v-if="!external"><dt class="text-slate-500 dark:text-slate-400">Views</dt><dd class="mt-0.5 font-medium text-slate-800 dark:text-white">{{ artifact.views?.toLocaleString() ?? 0 }}</dd></div>
                        <div v-if="!external"><dt class="text-slate-500 dark:text-slate-400">Downloads</dt><dd class="mt-0.5 font-medium text-slate-800 dark:text-white">{{ artifact.downloads?.toLocaleString() ?? 0 }}</dd></div>
                        <div><dt class="text-slate-500 dark:text-slate-400">Size</dt><dd class="mt-0.5 font-medium text-slate-800 dark:text-white">{{ size(artifact.size_bytes) }}</dd></div>
                        <div v-if="chosenFile" class="min-w-0"><dt class="text-slate-500 dark:text-slate-400">File</dt><dd class="mt-0.5 break-all font-medium text-slate-800 dark:text-white">{{ artifact.original_filename }}</dd></div>
                        <div><dt class="text-slate-500 dark:text-slate-400">First published</dt><dd class="mt-0.5 font-medium text-slate-800 dark:text-white">{{ date(artifact.published_at) }}</dd></div>
                        <div><dt class="text-slate-500 dark:text-slate-400">Updated</dt><dd class="mt-0.5 font-medium text-slate-800 dark:text-white">{{ date(artifact.updated_at) }}</dd></div>
                        <div class="min-w-0"><dt class="text-slate-500 dark:text-slate-400">Used in</dt>
                            <dd class="mt-0.5 text-slate-800 dark:text-white">
                                <template v-if="usedIn.length"><template v-for="(use, index) in usedIn" :key="use.href"><UiLink :href="use.href" class="inline-flex items-center gap-1"><Square2StackIcon v-if="use.type === 'Collection'" class="size-4 text-slate-400" aria-hidden="true" />{{ use.title }}</UiLink><span v-if="index < usedIn.length - 1" class="text-slate-400">, </span></template></template>
                                <span v-else class="text-slate-500 dark:text-slate-400">No collections or reports</span>
                            </dd>
                        </div>
                    </dl>
                    <!-- Two rules with a sliver of page between them, and a light shadow, lift the preview off the page.
                         It reaches out past the column so what it shows lines up with the tabs above. -->
                    <div v-if="!external" class="-mx-4 mt-8 rounded-[1.375rem] p-1.5 sm:-mx-[30px] lg:mx-0 shadow-lg ring-1 shadow-slate-900/[0.06] ring-slate-900/10 dark:shadow-black/20 dark:ring-white/10">
                    <div class="overflow-hidden rounded-2xl bg-white ring-1 ring-slate-900/10 dark:bg-slate-950 dark:ring-white/15">
                        <div class="flex items-center justify-between gap-3 border-b border-slate-800/10 py-3 pr-5 pl-4 sm:pl-6 dark:border-white/10">
                            <p class="flex items-center gap-2 text-sm"><EyeIcon class="size-4 text-slate-400" aria-hidden="true" /><span class="font-semibold text-slate-900 dark:text-white">Preview</span><span class="text-slate-500 dark:text-slate-400">Newest version, as readers see it</span></p>
                            <a v-if="hasUrl" :href="artifact.url" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-white">Open<ArrowTopRightOnSquareIcon class="size-4" aria-hidden="true" /></a>
                        </div>
                        <iframe v-if="!external" :src="previewUrl" :title="`Preview of ${artifact.title}`" class="block h-[calc(100dvh-10rem)] min-h-[32rem] w-full border-0 bg-white dark:bg-slate-950" @load="fit" />
                    </div>
                    </div>
                    </div>
                </div>

                <!-- Versions -->
                <div v-else-if="tab === 'versions'" class="pt-8">
                    <p class="mb-4 max-w-2xl text-sm text-slate-500 dark:text-slate-400">Publishing again with the same name adds a version. The main link always opens the newest one; each version also keeps its own link.</p>
                    <UiTable label="Versions">
                        <thead><tr><th>Version</th><th>Published</th><th class="numeric">Size</th><th class="max-sm:hidden">Source</th><th class="actions"><span class="sr-only">Actions</span></th></tr></thead>
                        <tbody>
                            <tr v-for="(version, index) in versions" :key="version.number">
                                <td class="strong">Version {{ version.number }}<span v-if="index === 0" class="ml-2 rounded-md bg-emerald-50 px-1.5 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-300">Newest</span></td>
                                <td>{{ date(version.created_at) }}</td>
                                <td class="numeric">{{ size(version.size_bytes) }}</td>
                                <td class="max-sm:hidden">{{ version.merged_from ? `Merged from ${version.merged_from.title || 'another artifact'}` : (version.original_filename || '—') }}</td>
                                <td class="actions">
                                    <div class="flex justify-end gap-1">
                                        <UiButton v-if="!external || version.external_url" :href="version.url" external variant="ghost" size="sm" square :aria-label="`View version ${version.number}`" title="View"><ArrowTopRightOnSquareIcon aria-hidden="true" /></UiButton>
                                        <UiButton variant="ghost" size="sm" square :aria-label="`Delete version ${version.number}`" title="Delete this version" class="hover:text-red-700 dark:hover:text-red-400" @click="destroyVersion(version)"><TrashIcon aria-hidden="true" /></UiButton>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </UiTable>
                </div>

                <!-- Sharing -->
                <!-- Settings -->
                <div v-else class="max-w-2xl space-y-8 pt-8">
                    <template v-if="!external">
                        <form class="space-y-6" @submit.prevent="saveSharing">
                            <UiSection title="Password" description="Readers need it to open the artifact. Embeds don't work while it has one." />
                            <p v-if="artifact.password_protected && !passwordChanging" class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300"><LockClosedIcon class="size-4 text-slate-400" aria-hidden="true" />Protected with a password</p>
                            <UiInput v-if="passwordChanging || !artifact.password_protected" v-model="sharing.password" type="password" :label="artifact.password_protected ? 'New password' : 'Password'" optional
                                autocomplete="new-password" :description="artifact.password_protected ? 'At least 8 characters. Replacing it signs out readers who entered the old password.' : 'At least 8 characters.'" :error="sharing.errors.password" />
                            <div v-if="artifact.password_protected" class="flex flex-wrap gap-2">
                                <UiButton v-if="!passwordChanging" variant="ghost" size="sm" @click="passwordChanging = true">Change password</UiButton>
                                <label class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300"><input v-model="sharing.removePassword" type="checkbox" class="rounded border-slate-300">Remove the password</label>
                            </div>
                            <div class="flex items-center gap-3">
                                <UiButton type="submit" :disabled="sharing.processing || (!sharing.password && !sharing.removePassword)">Save password</UiButton>
                                <span v-if="sharing.recentlySuccessful" class="text-sm text-emerald-700 dark:text-emerald-400">Saved</span>
                            </div>
                        </form>
                    </template>
                    <UiSeparator />
                    <section class="space-y-4">
                        <UiSection title="Projects" :description="project.is_home
                            ? 'This artifact has no project. Link it to projects to list it there and use it in their reports and collections.'
                            : `Published from ${project.name}. Link it to other projects to list it there and use it in their reports and collections.`" />
                        <ul v-if="linkedProjects.length" class="divide-y divide-slate-800/10 text-sm dark:divide-white/10">
                            <li v-for="linked in linkedProjects" :key="linked.id" class="flex items-center justify-between gap-3 py-2">
                                <UiLink :href="`/projects/${linked.slug}/published`">{{ linked.name }}</UiLink>
                                <UiButton variant="subtle" size="sm" @click="unlink(linked)">Unlink</UiButton>
                            </li>
                        </ul>
                        <form v-if="linkableProjects.length" class="flex flex-wrap items-end gap-3" @submit.prevent="link">
                            <div class="min-w-56 flex-1">
                                <UiSelect v-model="linking.project_id" label="Project" :error="linking.errors.project_id">
                                    <option value="" disabled>Choose a project</option>
                                    <option v-for="option in linkableProjects" :key="option.id" :value="option.id">{{ option.name }}</option>
                                </UiSelect>
                            </div>
                            <UiButton type="submit" variant="ghost" :disabled="!linking.project_id || linking.processing">Link project</UiButton>
                        </form>
                    </section>
                    <template v-if="!external && mergeSources.length && !artifact.merged_into">
                        <UiSeparator />
                        <form class="space-y-6" @submit.prevent="mergeIn">
                            <UiSection title="Artifact merging" description="Add another artifact’s versions to this history. The source artifact keeps its links." />
                            <UiSelect v-model="merge.source_id" label="Source artifact" :error="merge.errors.source_id">
                                <option value="" disabled>Choose an artifact from {{ project.name }}</option>
                                <option v-for="source in mergeSources" :key="source.id" :value="source.id">{{ source.title }}</option>
                            </UiSelect>
                            <UiButton type="submit" variant="ghost" :disabled="!merge.source_id || merge.processing">Merge versions</UiButton>
                        </form>
                    </template>
                    <UiSeparator />
                    <UiSection :title="external ? 'Tracking removal' : 'Artifact deletion'" :description="external ? 'Removes the publication record from Framework Cloud. Files at the destination stay available.' : 'Moves it to the Trash. Its links stop working, and collections and reports show it as unavailable, until it is restored.'">
                        <div><UiButton variant="ghost" class="text-red-700! dark:text-red-400!" @click="destroy"><TrashIcon aria-hidden="true" />{{ external ? 'Remove record' : 'Take down artifact' }}</UiButton></div>
                    </UiSection>
                </div>
            </div>
        </div>
        <ShareDialog :target="shareTarget" @close="shareTarget = null" />
    </AppLayout>
</template>
