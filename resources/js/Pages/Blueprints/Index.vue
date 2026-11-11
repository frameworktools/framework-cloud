<script setup>
import RowMenu from '@/Components/RowMenu.vue';
import ShareDialog from '@/Components/ShareDialog.vue';
import { ask } from '@/confirm.js';
import { computed, reactive, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { ChevronRightIcon } from '@heroicons/vue/20/solid';
import { PlusIcon } from '@heroicons/vue/24/outline';
import AppLayout from '@/Layouts/AppLayout.vue';
import UiButton from '@/Components/UiButton.vue';
import UiCallout from '@/Components/UiCallout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import BlueprintPreview from '@/Components/BlueprintPreview.vue';
import { prefetchPreview } from '@/blueprintPreviews.js';
import { group, groups, languageNames } from '@/library.js';

const props = defineProps({
    blueprints: Array, // each with languages, workflows, and combinations: language → workflows it supports
    language: String, // the saved language
    workflows: Object, // each language's preferred workflow
    default_blueprint: String,
});
const page = usePage();

const workflowNames = { quarto: 'Quarto', jupyter: 'Jupyter', scripts: 'Scripts only' };
const workflowKeys = Object.keys(workflowNames);
const fits = (workflow, language) => language !== 'stata' || workflow === 'scripts';

// What's being browsed. It starts at the saved language and its workflow, or
// at what the address names, and never changes a saved default.
const query = new URLSearchParams(typeof window === 'undefined' ? '' : window.location.search);
const language = ref(languageNames[query.get('language')] ? query.get('language') : props.language);
const workflow = ref(null);
const preferred = (lang) => (fits(props.workflows?.[lang], lang) ? props.workflows[lang] : workflowKeys.find((w) => fits(w, lang)));
workflow.value = fits(query.get('workflow'), language.value) && workflowNames[query.get('workflow')] ? query.get('workflow') : preferred(language.value);
const chooseLanguage = (lang) => {
    language.value = lang;
    workflow.value = preferred(lang);
};
watch([language, workflow], () => {
    const url = new URL(window.location.href);
    url.searchParams.set('language', language.value);
    url.searchParams.set('workflow', workflow.value);
    window.history.replaceState(window.history.state, '', url);
});
const workflowChoices = computed(() => workflowKeys.filter((w) => fits(w, language.value)));

const matches = (b, lang = language.value, wf = workflow.value) => (b.combinations[lang] ?? []).includes(wf);

// From Framework and Yours always show; Shared by others only when there are some
const sections = computed(() => groups
    .map((g) => {
        const all = props.blueprints.filter((b) => group(b) === g.key);
        return { ...g, all, rows: all.filter((b) => matches(b)) };
    })
    .filter((g) => g.all.length || g.key !== 'community'));

// Other combinations with something to show, for a section with no match
const elsewhere = (section) => Object.keys(languageNames)
    .flatMap((lang) => workflowKeys.filter((wf) => fits(wf, lang)).map((wf) => ({ lang, wf })))
    .filter(({ lang, wf }) => !(lang === language.value && wf === workflow.value))
    .map((c) => ({ ...c, count: section.all.filter((b) => matches(b, c.lang, c.wf)).length }))
    .filter((c) => c.count)
    // The browsed language first
    .sort((a, b) => (b.lang === language.value) - (a.lang === language.value));
const browse = ({ lang, wf }) => {
    language.value = lang;
    workflow.value = wf;
};

const supportLine = (b) => {
    const langs = b.languages.map((l) => languageNames[l]).join(', ');
    const wfs = b.workflows.filter((w) => b.languages.some((l) => fits(w, l))).map((w) => workflowNames[w]).join(', ');
    return `Languages: ${langs} · Workflows: ${wfs}`;
};

// One blueprint in each section is always open: the one chosen, else your
// default, else the first that matches
const chosen = reactive({});
const openIn = (section) => {
    const ids = section.rows.map((b) => b.id);
    if (ids.includes(chosen[section.key])) return chosen[section.key];
    // Collapsed on purpose: none open until one is chosen
    if (chosen[section.key] === null) return null;
    return section.rows.find(isDefault)?.id ?? section.rows.find((b) => b.handle === props.default_blueprint)?.id ?? ids[0];
};
// Choosing the open one again collapses it
const choose = (section, id) => (chosen[section.key] = openIn(section) === id ? null : id);
// A click on the card's text opens it, unless the click ends a text selection
const pick = (section, id) => window.getSelection()?.toString() || choose(section, id);
const source = (b) => ({ url: `/blueprints/${b.id}/preview`, body: { language: language.value, workflow: workflow.value } });
const prefetch = (b) => { const { url, body } = source(b); prefetchPreview(url, body, page.props.csrf); };

const isDefault = (b) => b.handle === props.default_blueprint && props.workflows?.[language.value] === workflow.value;
const lastDefault = ref(null);
const setDefault = (b) => {
    lastDefault.value = { name: b.name, language: language.value, workflow: workflow.value };
    router.post(`/blueprints/${b.id}/default`, { language: language.value, workflow: workflow.value }, { preserveScroll: true, preserveState: true });
};
const savedDefault = computed(() => page.props.flash.status === 'blueprint-default' && lastDefault.value);

const newProject = (b) => `/projects/create?${new URLSearchParams({ blueprint: b.id, language: language.value, workflow: workflow.value })}`;
const fork = (b) => router.post(`/blueprints/${b.id}/fork`, { language: language.value, workflow: workflow.value });

const shareTarget = ref(null);
const destroy = async (b) => {
    if (await ask(`Delete ${b.name}? Projects already made from it keep their setup.`)) {
        router.delete(`/blueprints/${b.id}`, { preserveScroll: true });
    }
};
const menuFor = (b) => [
    { label: 'New project', href: newProject(b) },
    ...(b.can_be_default && !isDefault(b) ? [{ label: 'Set as default', onSelect: () => setDefault(b) }] : []),
    { label: 'Open', href: `/blueprints/${b.id}` },
    b.editable && !b.is_system ? { label: 'Edit', href: `/blueprints/${b.id}/edit` } : { label: 'Copy and edit', onSelect: () => fork(b) },
    ...(b.editable && !b.is_system ? [{ label: 'Copy and edit', onSelect: () => fork(b) }] : []),
    { divider: true },
    ...(b.public_url ? [{ label: 'Public page', href: b.public_url, external: true }] : []),
    ...(!b.is_system ? [{ label: 'Share', onSelect: () => { shareTarget.value = { type: 'blueprint', id: b.id, title: b.name, settings: b.editable ? `/blueprints/${b.id}/edit` : null }; } }] : []),
    ...(b.editable && !b.is_system ? [{ divider: true }, { label: 'Delete', danger: true, onSelect: () => destroy(b) }] : []),
];

const tab = (on) => on
    ? 'border-sky-600 text-sky-700 dark:border-sky-400 dark:text-sky-300'
    : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-800 dark:text-slate-400 dark:hover:border-slate-600 dark:hover:text-slate-200';
const pill = (on) => on
    ? 'bg-white text-slate-900 shadow-sm ring-1 ring-slate-200 dark:bg-slate-800 dark:text-white dark:ring-slate-700'
    : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white';
</script>

<template>
    <Head title="Blueprints" />

    <AppLayout>
        <div class="flex flex-col gap-8">
            <PageHeader title="Blueprints" description="Starting points for your next project." small>
                <template #actions>
                    <Link href="/blueprints/create"><UiButton><PlusIcon class="size-4" />New blueprint</UiButton></Link>
                </template>
            </PageHeader>

            <UiCallout v-if="savedDefault" variant="success">
                {{ savedDefault.name }} is your default blueprint, and new {{ languageNames[savedDefault.language] }} projects use {{ workflowNames[savedDefault.workflow] }}.
                <Link href="/settings/blueprint" class="font-medium underline">Settings</Link>
            </UiCallout>

            <!-- What to browse: a language, then a workflow it has -->
            <div class="flex flex-col gap-4">
                <div role="tablist" aria-label="Language" class="flex gap-6 border-b border-slate-200 dark:border-slate-800">
                    <button
                        v-for="(name, key) in languageNames" :key="key" type="button" role="tab" :aria-selected="language === key"
                        class="-mb-px border-b-2 px-1 pb-2.5 text-sm font-medium transition" :class="tab(language === key)"
                        @click="chooseLanguage(key)"
                    >
                        {{ name }}
                    </button>
                </div>
                <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                    <div role="radiogroup" aria-label="Notebook workflow" class="inline-flex gap-1 rounded-lg bg-slate-100 p-1 dark:bg-slate-900">
                        <button
                            v-for="wf in workflowChoices" :key="wf" type="button" role="radio" :aria-checked="workflow === wf"
                            class="rounded-md px-3 py-1 text-sm font-medium transition" :class="pill(workflow === wf)"
                            @click="workflow = wf"
                        >
                            {{ workflowNames[wf] }}
                        </button>
                    </div>
                </div>
            </div>

            <section v-for="section in sections" :key="section.key" class="flex flex-col gap-3">
                <h2 class="flex items-baseline gap-2 text-lg font-semibold text-slate-900 dark:text-white">
                    {{ section.label }}
                    <span v-if="section.rows.length" class="text-sm font-normal text-slate-500 dark:text-slate-400">{{ section.rows.length }}</span>
                </h2>

                <!-- Nothing in the collection at all -->
                <p v-if="!section.all.length" class="text-sm text-slate-500 dark:text-slate-400">
                    You haven't made a blueprint yet.
                    <Link href="/blueprints/create" class="font-medium text-sky-700 hover:underline dark:text-sky-400">New blueprint</Link>
                </p>

                <!-- Some, but none for this language and workflow -->
                <div v-else-if="!section.rows.length" class="rounded-lg border border-dashed border-slate-300 px-4 py-4 text-sm dark:border-slate-700">
                    <p class="text-slate-700 dark:text-slate-300">No matching blueprints for {{ languageNames[language] }} with {{ workflowNames[workflow] }}.</p>
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <span class="text-xs text-slate-500 dark:text-slate-400">Try</span>
                        <button
                            v-for="c in elsewhere(section)" :key="`${c.lang}-${c.wf}`" type="button"
                            class="rounded-full border border-slate-300 px-2.5 py-0.5 text-xs text-slate-700 hover:border-sky-500 hover:text-sky-700 dark:border-slate-600 dark:text-slate-300 dark:hover:text-sky-300"
                            @click="browse(c)"
                        >
                            {{ languageNames[c.lang] }}, {{ workflowNames[c.wf] }} ({{ c.count }})
                        </button>
                    </div>
                </div>

                <div v-else class="flex flex-col gap-4">
                    <div
                        v-for="b in section.rows" :key="b.id"
                        class="rounded-xl border bg-white transition dark:bg-slate-900"
                        @pointerenter="prefetch(b)" @focusin="prefetch(b)"
                        :class="openIn(section) === b.id ? 'border-slate-300 shadow-sm dark:border-slate-700' : 'border-slate-200 dark:border-slate-800'"
                    >
                        <div class="flex flex-col gap-3 px-4 py-4 sm:flex-row sm:items-start sm:px-5">
                            <!-- The name and description open the details too; the Details button below is the keyboard target -->
                            <div class="min-w-0 flex-1 cursor-pointer" @click="pick(section, b.id)">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-semibold text-slate-900 dark:text-white">{{ b.name }}</span>
                                    <span v-if="isDefault(b)" class="rounded-full bg-sky-50 px-2 py-0.5 text-xs font-medium text-sky-700 ring-1 ring-sky-200 dark:bg-sky-950/50 dark:text-sky-300 dark:ring-sky-800">Your default</span>
                                </div>
                                <p v-if="b.description" class="mt-0.5 line-clamp-2 text-sm text-slate-600 dark:text-slate-400">{{ b.description }}</p>
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ supportLine(b) }}</p>
                            </div>
                            <div class="flex shrink-0 flex-wrap items-center gap-2">
                                <!-- One split button: New project, and the rest in its menu -->
                                <div class="inline-flex items-stretch divide-x divide-slate-800/10 rounded-md bg-white ring-1 ring-slate-800/15 dark:divide-white/15 dark:bg-transparent dark:ring-white/20">
                                    <Link :href="newProject(b)" class="inline-flex h-8 items-center gap-1.5 rounded-l-md px-3 text-sm font-medium text-slate-800 hover:bg-slate-800/5 focus-visible:outline-2 focus-visible:outline-sky-600 dark:text-slate-100 dark:hover:bg-white/10"><PlusIcon class="size-4 text-slate-500 dark:text-slate-400" aria-hidden="true" />New project</Link>
                                    <RowMenu attached :items="menuFor(b)" :label="`More actions for ${b.name}`" />
                                </div>
                            </div>
                        </div>
                        <div class="px-4 pb-3 sm:px-5" :class="openIn(section) === b.id ? '' : '-mt-2'">
                            <button
                                type="button" :aria-expanded="openIn(section) === b.id" :aria-controls="`blueprint-${b.id}`"
                                class="-ml-1.5 inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-sm text-slate-500 dark:text-slate-400 hover:bg-slate-100 hover:text-slate-800 focus-visible:outline-2 focus-visible:outline-sky-500 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                                @click="choose(section, b.id)"
                            >
                                <ChevronRightIcon class="size-4 transition-transform" :class="openIn(section) === b.id ? 'rotate-90' : ''" />
                                Details for {{ languageNames[language] }} with {{ workflowNames[workflow] }}
                            </button>
                            <div v-if="openIn(section) === b.id" :id="`blueprint-${b.id}`" class="mt-3">
                                <BlueprintPreview :blueprint="b" :language="language" :source="source(b)" />
                            </div>
                        </div>
                    </div>
                </div>
            </section>
            <ShareDialog :target="shareTarget" @close="shareTarget = null" />
        </div>
    </AppLayout>
</template>
