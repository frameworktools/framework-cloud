<script setup>
import { pageClass } from '@/ui.js';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import UiBreadcrumbs from '@/Components/UiBreadcrumbs.vue';
import UiButton from '@/Components/UiButton.vue';
import UiInput from '@/Components/UiInput.vue';
import UiTextarea from '@/Components/UiTextarea.vue';
import UiSelect from '@/Components/UiSelect.vue';
import UiTabs from '@/Components/UiTabs.vue';
import UiTable from '@/Components/UiTable.vue';
import UiSection from '@/Components/UiSection.vue';
import UiSeparator from '@/Components/UiSeparator.vue';
import UiLink from '@/Components/UiLink.vue';
import UiCallout from '@/Components/UiCallout.vue';
import { artifactKind } from '@/report-artifacts.js';
import { EyeIcon, ArrowTopRightOnSquareIcon, LockClosedIcon, TrashIcon, CheckIcon } from '@heroicons/vue/20/solid';
import { ClipboardDocumentIcon, Square2StackIcon, Square3Stack3DIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ artifact: Object, project: Object, versions: Array, usedIn: Array, mergeSources: Array, handle: String });
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
    && !(['model', 'test', 'table'].includes(props.artifact.kind) && /\.json$/i.test(props.artifact.original_filename)));

// The open tab is kept in the address, so a reload or a shared link lands on it
const tabs = computed(() => [
    { label: 'Overview', value: 'overview' },
    { label: 'Versions', value: 'versions', count: props.versions.length },
    ...(!external.value ? [{ label: 'Sharing', value: 'sharing' }] : []),
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
        const resize = () => {
            const content = main.offsetTop + main.scrollHeight;
            frame.style.height = `${Math.max(160, content, main === doc.body ? 0 : doc.body.scrollHeight - main.clientHeight + main.scrollHeight)}px`;
        };
        resize();
        observer = new ResizeObserver(resize);
        observer.observe(main);
        for (const child of main.children) observer.observe(child);
    } catch { /* a frame from another origin keeps its default height */ }
}
onBeforeUnmount(() => observer?.disconnect());

// Settings
const details = useForm({ title: props.artifact.title, description: props.artifact.description || '' });
const saveDetails = () => details.patch(base, { preserveScroll: true, onSuccess: () => details.defaults() });

// Sharing
const sharing = useForm({ visibility: props.artifact.visibility ?? (props.artifact.readable ? 'public' : 'unlisted'), password: '', removePassword: false });
const passwordChanging = ref(false);
function saveSharing() {
    sharing.transform(data => {
        const payload = { visibility: data.visibility };
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
function mergeIn() {
    const source = props.mergeSources.find(s => s.id === Number(merge.source_id));
    if (!source || !confirm(`Append the versions of “${source.title}” to “${props.artifact.title}”? The source keeps its own links and versions.`)) return;
    merge.post(`${base}/merge`, { preserveScroll: true, onSuccess: () => merge.reset() });
}
function destroyVersion(version) {
    const last = props.versions.length === 1;
    if (external.value) {
        if (confirm(`Remove version ${version.number} from Framework Cloud? Files at the publishing destination stay available.`)) router.delete(`${base}/versions/${version.number}?then=list`, { preserveScroll: true });
        return;
    }
    if (!confirm(last ? `Delete version ${version.number}? It is the only version, so “${props.artifact.title}” is taken down.` : `Delete version ${version.number}? Its links stop working.`)) return;
    router.delete(`${base}/versions/${version.number}?then=list`, { preserveScroll: true });
}
function destroy() {
    if (external.value) {
        if (confirm(`Remove “${props.artifact.title}” from Framework Cloud? Files at the publishing destination stay available.`)) router.delete(`${base}?then=list`);
        return;
    }
    if (confirm(`Take down “${props.artifact.title}”? Every version and link stops working for everyone.`)) router.delete(`${base}?then=list`);
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
                        <h1 class="break-words text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">{{ artifact.title }}</h1>
                        <p class="mt-1.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-slate-500 dark:text-slate-400">
                            <span>{{ kind.label }}</span><span aria-hidden="true">·</span>
                            <span>{{ sharingText[artifact.visibility] || artifact.visibility }}</span>
                            <LockClosedIcon v-if="artifact.password_protected" class="size-3.5" aria-label="Password protected" />
                            <span aria-hidden="true">·</span>
                            <Link :href="`/projects/${project.slug}/published`" class="inline-flex min-w-0 max-w-full items-center gap-1.5 hover:text-slate-800 hover:underline dark:hover:text-white">
                                <Square3Stack3DIcon class="size-4 shrink-0 text-slate-400" aria-hidden="true" /><span class="truncate">{{ project.name }}</span>
                            </Link>
                        </p>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <UiButton v-if="hasUrl" variant="ghost" @click="copy('share', artifact.url)">
                            <component :is="copied === 'share' ? CheckIcon : ClipboardDocumentIcon" class="text-slate-500 dark:text-slate-400" aria-hidden="true" />{{ copied === 'share' ? 'Copied' : 'Copy link' }}
                        </UiButton>
                        <UiButton v-if="hasUrl" variant="primary" :href="artifact.url" external><ArrowTopRightOnSquareIcon aria-hidden="true" />View</UiButton>
                    </div>
                </header>

                <UiCallout v-if="artifact.merged_into" variant="warning" class="mt-6">
                    This artifact was merged into <UiLink :href="`/artifacts/${artifact.merged_into.id}`">{{ artifact.merged_into.title }}</UiLink>. Its links still work; publish new versions there.
                </UiCallout>

                <UiTabs v-model="tab" :tabs="tabs" label="Artifact sections" class="mt-8" />

                <!-- Overview -->
                <div v-if="tab === 'overview'" class="pt-8">
                    <UiCallout v-if="external" class="mb-6" :heading="artifact.location">Files and access are managed at the publishing destination. Framework Cloud stores publication metadata only.</UiCallout>
                    <p v-if="artifact.description" class="mb-6 max-w-2xl whitespace-pre-line text-sm text-slate-600 dark:text-slate-300">{{ artifact.description }}</p>
                    <dl class="flex flex-wrap gap-x-10 gap-y-4 text-sm">
                        <div><dt class="text-slate-500 dark:text-slate-400">Versions</dt><dd class="mt-0.5 font-medium text-slate-800 dark:text-white">{{ versions.length }}</dd></div>
                        <div v-if="!external"><dt class="text-slate-500 dark:text-slate-400">Views</dt><dd class="mt-0.5 font-medium text-slate-800 dark:text-white">{{ artifact.views?.toLocaleString() ?? 0 }}</dd></div>
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
                    <div v-if="!external" class="-mx-4 mt-8 rounded-[1.375rem] p-1.5 sm:-mx-[30px] shadow-lg ring-1 shadow-slate-900/[0.06] ring-slate-900/10 dark:shadow-black/20 dark:ring-white/10">
                    <div class="overflow-hidden rounded-2xl bg-white ring-1 ring-slate-900/10 dark:bg-slate-950 dark:ring-white/15">
                        <div class="flex items-center justify-between gap-3 border-b border-slate-800/10 py-3 pr-5 pl-4 sm:pl-6 dark:border-white/10">
                            <p class="flex items-center gap-2 text-sm"><EyeIcon class="size-4 text-slate-400" aria-hidden="true" /><span class="font-semibold text-slate-900 dark:text-white">Preview</span><span class="text-slate-500 dark:text-slate-400">Newest version, as readers see it</span></p>
                            <a v-if="hasUrl" :href="artifact.url" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-white">Open<ArrowTopRightOnSquareIcon class="size-4" aria-hidden="true" /></a>
                        </div>
                        <iframe v-if="!external" :src="previewUrl" :title="`Preview of ${artifact.title}`" scrolling="no" class="block h-96 w-full overflow-hidden border-0 bg-white dark:bg-slate-950" @load="fit" />
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
                <form v-else-if="tab === 'sharing' && !external" class="max-w-xl space-y-6 pt-6" @submit.prevent="saveSharing">
                    <UiSelect v-model="sharing.visibility" label="Reader access" :error="sharing.errors.visibility"
                        description="Private artifacts require your sign-in. Unlisted artifacts open to anyone with the link. Public artifacts also get a readable address."
                        :options="[{ value: 'private', label: 'Private, only you' }, { value: 'unlisted', label: 'Anyone with the link' }, { value: 'public', label: 'Public, with a readable link' }]" />
                    <UiSeparator />
                    <section aria-label="Links" class="space-y-3">
                        <h2 class="text-sm font-medium text-slate-900 dark:text-white">Links</h2>
                        <div class="space-y-4">
                            <div>
                                <p class="text-sm font-medium text-slate-800 dark:text-white">Share link</p>
                                <div class="mt-2 flex items-center gap-2">
                                    <code class="min-w-0 flex-1 truncate rounded-lg bg-slate-800/5 px-3 py-2 text-xs text-slate-700 dark:bg-white/10 dark:text-slate-300">{{ artifact.private_url }}</code>
                                    <UiButton variant="ghost" size="sm" @click="copy('private', artifact.private_url)">{{ copied === 'private' ? 'Copied' : 'Copy' }}</UiButton>
                                </div>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-slate-800 dark:text-white">Readable link</p>
                                <div v-if="artifact.readable_url" class="mt-2 flex items-center gap-2">
                                    <code class="min-w-0 flex-1 truncate rounded-lg bg-slate-800/5 px-3 py-2 text-xs text-slate-700 dark:bg-white/10 dark:text-slate-300">{{ artifact.readable_url }}</code>
                                    <UiButton variant="ghost" size="sm" @click="copy('readable', artifact.readable_url)">{{ copied === 'readable' ? 'Copied' : 'Copy' }}</UiButton>
                                </div>
                                <p v-else-if="artifact.readable && !handle" class="mt-1 text-sm text-slate-500">Needs a handle. <UiLink href="/account/account">Set one in your account</UiLink>.</p>
                                <p v-else class="mt-1 text-sm text-slate-500">Turns on when the artifact is public.</p>
                            </div>
                        </div>
                    </section>
                    <UiSeparator />
                    <section aria-label="Password">
                        <h2 v-if="artifact.password_protected" class="mb-3 text-sm font-medium text-slate-900 dark:text-white">Password</h2>
                        <div class="space-y-4">
                            <p v-if="artifact.password_protected && !passwordChanging" class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300"><LockClosedIcon class="size-4 text-slate-400" aria-hidden="true" />Protected with a password</p>
                            <UiInput v-if="passwordChanging || !artifact.password_protected" v-model="sharing.password" type="password" :label="artifact.password_protected ? 'New password' : 'Password'" optional
                                autocomplete="new-password" :description="artifact.password_protected ? 'At least 8 characters. Replacing it signs out readers who entered the old password.' : 'At least 8 characters. Readers will need it to open this artifact.'" :error="sharing.errors.password" />
                            <div v-if="artifact.password_protected" class="flex flex-wrap gap-2">
                                <UiButton v-if="!passwordChanging" variant="ghost" size="sm" @click="passwordChanging = true">Change password</UiButton>
                                <label class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300"><input v-model="sharing.removePassword" type="checkbox" class="rounded border-slate-300">Remove the password</label>
                            </div>
                        </div>
                    </section>
                    <div class="flex items-center gap-3">
                        <UiButton type="submit" :disabled="sharing.processing">{{ sharing.processing ? 'Saving…' : 'Save sharing' }}</UiButton>
                        <span v-if="sharing.recentlySuccessful" class="text-sm text-emerald-700 dark:text-emerald-400">Saved</span>
                    </div>
                </form>

                <!-- Settings -->
                <div v-else class="max-w-2xl space-y-8 pt-8">
                    <form class="space-y-6" @submit.prevent="saveDetails">
                        <UiSection title="Details" description="Readers see the title and description on the artifact’s page." />
                        <UiInput v-model="details.title" label="Title" required :error="details.errors.title" />
                        <UiTextarea v-model="details.description" label="Description" optional :rows="3" placeholder="Artifact description" :error="details.errors.description" />
                        <div class="flex items-center gap-3">
                            <UiButton type="submit" :disabled="details.processing">Save</UiButton>
                            <span v-if="details.recentlySuccessful" class="text-sm text-emerald-700 dark:text-emerald-400">Saved</span>
                        </div>
                    </form>
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
                    <UiSection :title="external ? 'Tracking removal' : 'Artifact deletion'" :description="external ? 'Removes the publication record from Framework Cloud. Files at the destination stay available.' : 'Removes every version. Its links stop working, and collections and reports show it as unavailable.'">
                        <div><UiButton variant="ghost" class="text-red-700! dark:text-red-400!" @click="destroy"><TrashIcon aria-hidden="true" />{{ external ? 'Remove record' : 'Take down artifact' }}</UiButton></div>
                    </UiSection>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
