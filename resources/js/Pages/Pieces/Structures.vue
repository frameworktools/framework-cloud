<script setup>
import RowMenu from '@/Components/RowMenu.vue';
import { ask } from '@/confirm.js';
import { computed, reactive } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { ChevronRightIcon } from '@heroicons/vue/20/solid';
import { PlusIcon } from '@heroicons/vue/24/outline';
import AppLayout from '@/Layouts/AppLayout.vue';
import UiButton from '@/Components/UiButton.vue';
import UiCallout from '@/Components/UiCallout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import ProjectTreeView from '@/Components/ProjectTreeView.vue';
import { group, groups } from '@/library.js';
import { pieceCopy } from '@/pieces.js';

// Each structure as a row that opens to its folders and files and what renders where
const props = defineProps({ structures: Array });

const page = usePage();
const copy = pieceCopy.structure;
const errors = computed(() => Object.values(page.props.errors ?? {}));

// From Framework and Yours always show; Shared by others only when there are some
const sections = computed(() => groups
    .map((g) => ({ ...g, rows: props.structures.filter((s) => group(s) === g.key) }))
    .filter((g) => g.rows.length || g.key !== 'community'));

// Standard starts open; any number can be open to compare
const open = reactive(new Set(props.structures.filter((s) => s.is_system && s.key === 'project').map((s) => s.id)));
const toggle = (id) => (open.has(id) ? open.delete(id) : open.add(id));

const fork = (id) => router.post(`/structures/${id}/fork`);

const label = 'text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400';
const dir = (path) => (path === '.' ? 'the project folder' : `${path}/`);

const removeItem = async (item) => {
    if (await ask(`Delete ${item.name}? This can't be undone.`)) router.delete(`/structures/${item.id}`, { preserveScroll: true });
};
const menuFor = (item) => [
    item.editable && !item.is_system ? { label: 'Edit', href: `/structures/${item.id}/edit` } : { label: 'Copy and edit', onSelect: () => fork(item.id) },
    ...(item.editable && !item.is_system ? [{ label: 'Copy and edit', onSelect: () => fork(item.id) }, { divider: true }, { label: 'Delete', danger: true, onSelect: () => removeItem(item) }] : []),
];
</script>

<template>
    <Head :title="copy.title" />

    <AppLayout>
        <div class="flex flex-col gap-8">
            <PageHeader :title="copy.title" :description="copy.description" small>
                <template #actions>
                    <Link href="/structures/create"><UiButton><PlusIcon class="size-4" />New project structure</UiButton></Link>
                </template>
            </PageHeader>

            <UiCallout v-if="errors.length" variant="danger">{{ errors.join(' ') }}</UiCallout>

            <section v-for="section in sections" :key="section.key" class="flex flex-col gap-3">
                <h2 class="flex items-baseline gap-2 text-lg font-semibold text-slate-900 dark:text-white">
                    {{ section.label }}
                    <span v-if="section.rows.length" class="text-sm font-normal text-slate-500 dark:text-slate-400">{{ section.rows.length }}</span>
                </h2>

                <p v-if="!section.rows.length" class="text-sm text-slate-500 dark:text-slate-400">
                    {{ copy.empty }}
                    <Link href="/structures/create" class="font-medium text-sky-700 hover:underline dark:text-sky-400">New project structure</Link>
                </p>

                <div v-else class="divide-y divide-slate-200 rounded-xl border border-slate-200 bg-white dark:divide-slate-800 dark:border-slate-800 dark:bg-slate-900">
                    <div v-for="s in section.rows" :key="s.id">
                        <div class="flex items-start gap-3 px-4 py-3.5 sm:px-5">
                            <button
                                type="button" :aria-expanded="open.has(s.id)" :aria-controls="`structure-${s.id}`"
                                class="flex min-w-0 flex-1 items-start gap-2 rounded text-left focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-500"
                                @click="toggle(s.id)"
                            >
                                <ChevronRightIcon class="mt-0.5 size-5 shrink-0 text-slate-400 transition-transform" :class="open.has(s.id) ? 'rotate-90' : ''" aria-hidden="true" />
                                <span class="min-w-0">
                                    <span class="block font-semibold text-slate-900 dark:text-white">{{ s.name }}</span>
                                    <span v-if="s.description" class="mt-0.5 block text-sm text-slate-600 dark:text-slate-400">{{ s.description }}</span>
                                </span>
                            </button>
                            <Link v-if="s.editable" :href="`/structures/${s.id}/edit`" class="shrink-0"><UiButton size="sm" variant="outline">Edit</UiButton></Link>
                            <UiButton v-else size="sm" variant="outline" class="shrink-0" @click="fork(s.id)">Copy and edit</UiButton>
                            <RowMenu :items="menuFor(s)" :label="`Actions for ${s.name}`" />
                        </div>

                        <div v-if="open.has(s.id)" :id="`structure-${s.id}`" class="px-4 pb-4 sm:px-5 sm:pl-12">
                            <div class="grid gap-x-8 gap-y-5 border-t border-slate-200 pt-4 lg:grid-cols-[minmax(0,1fr)_18rem] dark:border-slate-800">
                                <!-- Folders and core files -->
                                <div class="min-w-0">
                                    <div :class="label" class="mb-2">Folders and files</div>
                                    <ProjectTreeView :entries="s.preview.tree" show-when />
                                    <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">Editor, agent, and Git files and packages come from the blueprint and your defaults. Stata projects get the same folders, with settings.do in place of settings.yml.</p>
                                </div>

                                <!-- What the structure decides -->
                                <div class="flex min-w-0 flex-col gap-5 text-sm text-slate-700 lg:border-l lg:border-slate-200 lg:pl-8 dark:text-slate-300 lg:dark:border-slate-800">
                                    <p v-if="!s.preview.tree.some((e) => e.type === 'dir')" class="text-slate-600 dark:text-slate-400">
                                        No folders. The project starts with its settings file, and you add folders as you need them.
                                    </p>

                                    <section v-if="s.preview.renders.length || s.preview.render_default">
                                        <div :class="label">Rendered documents</div>
                                        <table v-if="s.preview.renders.length || (s.preview.render_default && s.preview.render_default !== '.')" class="mt-1.5 w-full text-left text-xs">
                                            <thead class="text-slate-500 dark:text-slate-400">
                                                <tr class="border-b border-slate-200 dark:border-slate-800">
                                                    <th scope="col" class="py-1 pr-3 font-medium">Source</th>
                                                    <th scope="col" class="py-1 font-medium">Output</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60">
                                                <tr v-for="r in s.preview.renders" :key="r.from">
                                                    <td class="py-1 pr-3 font-mono">{{ r.from }}/</td>
                                                    <td class="py-1 font-mono break-all">{{ r.to }}/</td>
                                                </tr>
                                                <tr v-if="s.preview.render_default && s.preview.render_default !== '.'">
                                                    <td class="py-1 pr-3">Other documents</td>
                                                    <td class="py-1 font-mono break-all">{{ dir(s.preview.render_default) }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                        <p v-if="s.preview.render_default === '.'" class="mt-1.5 text-xs">Documents render next to their source.</p>
                                    </section>

                                    <section v-if="s.preview.tree.length">
                                        <div :class="label">Settings</div>
                                        <p class="mt-1.5 text-xs">
                                            <template v-if="s.preview.settings === 'split'">settings.yml, with each topic in its own file under <span class="font-mono">settings/</span>.</template>
                                            <template v-else>One settings.yml.</template>
                                        </p>
                                    </section>

                                    <section>
                                        <div :class="label">.gitignore template</div>
                                        <p class="mt-1.5 text-xs">Each structure includes rules for private data, credentials, and local files. Copy and edit a structure to change its ignore rules.</p>
                                        <details v-if="s.gitignore_content" class="mt-2">
                                            <summary class="cursor-pointer text-xs underline underline-offset-4">View ignore rules</summary>
                                            <pre class="mt-2 max-h-48 overflow-auto whitespace-pre-wrap break-words font-mono text-xs">{{ s.gitignore_content }}</pre>
                                        </details>
                                    </section>

                                    <section v-if="s.preview.sensitive">
                                        <div :class="label">Private and public data</div>
                                        <p class="mt-1.5 text-xs">
                                            Inputs and outputs each have <span class="font-mono">private/</span> and <span class="font-mono">public/</span> folders.
                                            Rendered notebooks and documents go to private folders.
                                        </p>
                                        <div class="mt-3 text-xs font-medium text-slate-900 dark:text-white">Framework enforces</div>
                                        <ul class="mt-1 list-disc pl-4 text-xs">
                                            <li>Private folders stay out of Git.</li>
                                            <li>Publishing or uploading a private file asks first.</li>
                                        </ul>
                                        <div class="mt-3 text-xs font-medium text-slate-900 dark:text-white">Agents are told</div>
                                        <ul class="mt-1 list-disc pl-4 text-xs">
                                            <li>To follow the sensitive-data skill before working with data.</li>
                                        </ul>
                                        <details class="group mt-3 text-xs">
                                            <summary class="flex cursor-pointer list-none items-center gap-1 text-sky-700 hover:underline dark:text-sky-400 [&::-webkit-details-marker]:hidden">
                                                <ChevronRightIcon class="-ml-1 size-4 transition-transform group-open:rotate-90" />Data protection
                                            </summary>
                                            <div class="mt-2 flex flex-col gap-2 text-slate-600 dark:text-slate-400">
                                                <p>The project's .gitignore excludes every <span class="font-mono">private/</span> folder, such as <template v-for="(p, i) in s.preview.private" :key="p">{{ i ? ' and ' : '' }}<span class="font-mono">{{ p }}/</span></template>, so Git leaves untracked files there out of commits. Files already tracked by Git need to be removed from its index.</p>
                                                <p>R, Python, and fw stop before publishing or uploading any path with "private" in it. Interactive sessions ask for confirmation. Scripts must pass include_private (or --include-private) to go ahead.</p>
                                                <p>Agents get a skill that describes these rules. It is guidance: an agent can still read private files it is pointed to, so keep access to the data itself controlled.</p>
                                            </div>
                                        </details>
                                    </section>
                                </div>

                                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 border-t border-slate-200 pt-3 text-xs text-slate-500 dark:text-slate-400 lg:col-span-2 dark:border-slate-800">
                                    <span class="font-mono">{{ s.handle }} v{{ s.version }}</span>
                                    <span v-if="!s.is_system && !s.editable && s.owner">Shared by {{ s.owner.name ?? s.owner }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
