<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { ChevronRightIcon } from '@heroicons/vue/20/solid';

/**
 * The default blueprint, picked from a list. Each row's details open on
 * their own and never change the choice. An open row previews the project
 * the blueprint would create as the default, resolved on the server with the
 * form's values, saved or not, for the language picked in the preview.
 */
const props = defineProps({
    blueprints: { type: Array, required: true },
    defaults: { type: Object, required: true }, // the Project defaults form
});
const model = defineModel({ type: String });
const page = usePage();

// The chosen blueprint starts open; any number can stay open to compare
const open = ref(new Set([model.value]));
const toggleOpen = (key) => {
    const next = new Set(open.value);
    next.has(key) ? next.delete(key) : next.add(key);
    open.value = next;
};

// Previews start in your default language, where the blueprint has it.
// Switching one is only for looking: it doesn't change the default.
const languageNames = { r: 'R', python: 'Python', stata: 'Stata' };
const startLanguage = (b) => (b.languages.includes(props.defaults.language) ? props.defaults.language : 'r');
const language = reactive(Object.fromEntries(props.blueprints.map((b) => [b.key, startLanguage(b)])));

// What the server resolves the previews from: every default that changes a new project
const values = computed(() => {
    const d = props.defaults;
    return { package_sets: d.package_sets, skill_sets: d.skill_sets, tools: d.tools, use_renv: d.use_renv, sensitive: d.sensitive, use_git: d.use_git, ai_enabled: d.ai_enabled, hooks: d.hooks };
});
const previews = reactive({});
const loading = reactive({});
const load = async (key) => {
    const lang = language[key];
    const id = `${key}:${lang}:${JSON.stringify(values.value)}`;
    if (previews[key]?.id === id) return;
    loading[key] = true;
    try {
        const response = await fetch('/settings/blueprint/preview', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': page.props.csrf, 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ blueprint: key, language: lang, values: values.value }),
        });
        const data = response.ok ? await response.json() : { available: false, problem: "The preview couldn't be loaded." };
        // A later request may have finished first
        if (`${key}:${language[key]}:${JSON.stringify(values.value)}` === id) previews[key] = { id, ...data };
    } finally {
        loading[key] = false;
    }
};
let timer = null;
watch([open, () => ({ ...language }), values], () => {
    clearTimeout(timer);
    timer = setTimeout(() => open.value.forEach(load), 150);
}, { immediate: true, deep: true });

// The folder tree with connectors, from the preview's flat entries
const treeLines = (entries) => {
    const root = { children: new Map() };
    for (const entry of entries) {
        let node = root;
        const parts = entry.path.split('/');
        parts.forEach((part, i) => {
            if (!node.children.has(part)) node.children.set(part, { name: part, dir: true, children: new Map() });
            node = node.children.get(part);
            if (i === parts.length - 1) Object.assign(node, { dir: entry.type === 'dir', note: entry.note, when: entry.when, later: entry.later });
        });
    }
    const lines = [];
    const sorted = (node) => [...node.children.values()].sort((a, b) => (a.dir === b.dir ? a.name.localeCompare(b.name) : a.dir ? -1 : 1));
    const walk = (node, prefix) => {
        const kids = sorted(node);
        kids.forEach((child, i) => {
            const last = i === kids.length - 1;
            // What a name doesn't say goes in its tooltip
            const title = [child.note && child.note[0].toUpperCase() + child.note.slice(1), child.when && `Only ${child.when}`, child.later && 'Made when first used or rendered'].filter(Boolean).join('. ') || undefined;
            lines.push({ prefix: prefix + (last ? '└── ' : '├── '), name: child.name + (child.dir ? '/' : ''), dir: child.dir, later: !!child.later, title });
            walk(child, prefix + (last ? '    ' : '│   '));
        });
    };
    // A folder holding only later folders is itself made later
    const mark = (node) => {
        node.children.forEach(mark);
        if (node.children.size && [...node.children.values()].every((c) => c.later)) node.later = true;
    };
    root.children.forEach(mark);
    walk(root, '');
    return lines;
};

const hookNames = { data_security: 'Check for secrets', check_sensitive_dirs: 'Check private folders' };
const label = 'text-xs font-semibold uppercase tracking-wider text-slate-500';
const link = 'text-sky-600 hover:underline dark:text-sky-400';
</script>

<template>
    <fieldset>
        <legend class="sr-only">Default blueprint</legend>
        <div class="divide-y divide-slate-200 rounded-lg border border-slate-200 dark:divide-slate-800 dark:border-slate-800">
            <div v-for="b in blueprints" :key="b.key" class="relative">
                <!-- The chosen row's accent: a thin bar at its left edge -->
                <span v-if="model === b.key" class="absolute inset-y-0 left-0 w-0.5 bg-sky-600" aria-hidden="true" />
                <div class="flex items-start gap-3 px-4 py-3">
                    <label class="flex min-w-0 flex-1 cursor-pointer items-start gap-3">
                        <input v-model="model" type="radio" name="project_type" :value="b.key" class="mt-0.5 size-4 shrink-0 accent-sky-600">
                        <span class="flex min-w-0 flex-col gap-0.5">
                            <span class="text-sm font-medium text-slate-900 dark:text-white">{{ b.name }}</span>
                            <span class="text-sm text-slate-600 dark:text-slate-400">{{ b.description }}</span>
                        </span>
                    </label>
                    <button
                        type="button" :aria-expanded="open.has(b.key)" :aria-controls="`blueprint-${b.key}`"
                        class="inline-flex shrink-0 items-center gap-1 rounded px-1.5 py-0.5 text-sm text-slate-500 hover:bg-slate-100 hover:text-slate-800 focus-visible:outline-2 focus-visible:outline-sky-500 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                        @click="toggleOpen(b.key)"
                    >
                        Details
                        <ChevronRightIcon class="size-4 transition-transform" :class="open.has(b.key) ? 'rotate-90' : ''" />
                    </button>
                </div>

                <div v-if="open.has(b.key)" :id="`blueprint-${b.key}`" class="px-4 pb-4 sm:pl-11">
                    <div class="grid gap-x-8 gap-y-5 border-t border-slate-200 pt-4 xl:grid-cols-[minmax(0,1fr)_16rem] dark:border-slate-800">
                        <!-- The language the whole preview is for: folders, packages, skills, and tools all follow it -->
                        <div class="flex w-fit rounded-md border border-slate-300 bg-white p-0.5 text-xs xl:col-span-2 dark:border-slate-700 dark:bg-slate-950" role="group" :aria-label="`Preview language for ${b.name}`">
                            <button
                                v-for="lang in ['r', 'python', 'stata']" :key="lang" type="button"
                                :disabled="!b.languages.includes(lang)" :aria-pressed="language[b.key] === lang"
                                :title="b.languages.includes(lang) ? `Preview as a ${languageNames[lang]} project` : `Not available for ${languageNames[lang]}`"
                                class="rounded px-2 py-0.5 transition disabled:cursor-not-allowed disabled:opacity-40"
                                :class="language[b.key] === lang ? 'bg-slate-200 font-medium text-slate-900 dark:bg-slate-700 dark:text-white' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'"
                                @click="language[b.key] = lang"
                            >{{ languageNames[lang] }}</button>
                        </div>

                        <!-- The project's folders and files -->
                        <div class="min-w-0">
                            <div :class="label" class="mb-3">Project structure</div>
                            <div v-if="!previews[b.key]" class="text-sm text-slate-500">Loading…</div>
                            <p v-else-if="!previews[b.key].available" class="text-sm text-slate-600 dark:text-slate-400">{{ previews[b.key].problem }}</p>
                            <div v-else class="overflow-x-auto" :class="loading[b.key] ? 'opacity-60' : ''">
                                <!-- Line height just over the font size keeps the connectors joined -->
                                <div class="whitespace-pre font-mono text-xs leading-[1.2] text-slate-800 dark:text-slate-200"><div>/</div><div v-for="(line, i) in treeLines(previews[b.key].tree)" :key="i"><span class="text-slate-400 dark:text-slate-600">{{ line.prefix }}</span><span :class="[line.dir ? 'font-medium' : '', line.later ? 'text-slate-400 dark:text-slate-500' : '', line.title ? 'cursor-help' : '']" :title="line.title">{{ line.name }}</span></div></div>
                                <p v-if="previews[b.key].tree.some((e) => e.later)" class="mt-2 text-xs text-slate-500">Lighter folders are made when they're first used or rendered.</p>
                            </div>
                        </div>

                        <!-- What else it gets, most important first -->
                        <div v-if="previews[b.key]?.available" class="flex min-w-0 flex-col gap-6 text-sm text-slate-700 xl:border-l xl:border-slate-200 xl:pl-8 dark:text-slate-300 xl:dark:border-slate-800" :class="loading[b.key] ? 'opacity-60' : ''">
                            <section>
                                <div :class="label">Packages</div>
                                <ul class="mt-2 flex flex-col gap-1">
                                    <li v-for="set in previews[b.key].package_sets" :key="set.id">
                                        <details>
                                            <summary class="cursor-pointer">{{ set.name }} <span v-if="set.from === 'defaults'" class="text-xs text-slate-500" title="From your Project defaults">· your default</span></summary>
                                            <div class="mt-2 pl-4 font-mono text-xs text-slate-600 dark:text-slate-400">{{ set.packages.join(', ') }}</div>
                                        </details>
                                    </li>
                                    <li v-for="p in previews[b.key].other_packages" :key="p.package + p.from"><span class="font-mono text-xs">{{ p.package }}</span> <span class="text-xs text-slate-500">with {{ p.from }}</span></li>
                                    <li v-if="!previews[b.key].package_sets.length && !previews[b.key].other_packages.length" class="text-slate-500">None</li>
                                </ul>
                            </section>

                            <section>
                                <div :class="label">AI agents</div>
                                <div class="mt-2">Setup: {{ previews[b.key].agents ? 'On' : 'Off' }} <span v-if="previews[b.key].settings.agents.from === 'defaults'" class="text-xs text-slate-500" title="From your Project defaults">· your default</span></div>
                            </section>

                            <!-- Each skill on its own line, under the set it comes from -->
                            <section>
                                <div :class="label">Skills</div>
                                <p v-if="!previews[b.key].agents" class="mt-2 text-slate-500">None, with agent setup off</p>
                                <template v-else>
                                    <!-- A set opens to its skills -->
                                    <details v-for="set in previews[b.key].skill_sets" :key="set.id" class="group mt-2">
                                        <summary class="flex cursor-pointer list-none items-center gap-1 [&::-webkit-details-marker]:hidden">
                                            <ChevronRightIcon class="-ml-1 size-4 shrink-0 text-slate-400 transition-transform group-open:rotate-90" />
                                            <span>{{ set.name }}</span>
                                            <span v-if="set.from === 'defaults'" class="text-xs text-slate-500" title="From your Project defaults">· your default</span>
                                        </summary>
                                        <ul class="mb-1 mt-1.5 flex flex-col gap-1 pl-4 text-xs text-slate-600 dark:text-slate-400">
                                            <li v-for="skill in set.skills" :key="skill">{{ skill }}</li>
                                        </ul>
                                    </details>
                                    <ul v-if="previews[b.key].skills.length" class="mt-2 flex flex-col gap-1 pl-4">
                                        <li v-for="skill in previews[b.key].skills" :key="skill">{{ skill }}</li>
                                    </ul>
                                    <p v-if="!previews[b.key].skill_sets.length && !previews[b.key].skills.length" class="mt-2 text-slate-500">None</p>
                                </template>
                            </section>

                            <section class="text-xs text-slate-600 dark:text-slate-400">
                                <div :class="label">Tools</div>
                                <div class="mt-2">
                                    <template v-for="(t, i) in previews[b.key].tools" :key="t.name">{{ i ? ' · ' : '' }}{{ t.name }}</template>
                                    <span v-if="!previews[b.key].tools.length">None</span>
                                </div>
                                <div v-for="name in previews[b.key].tools_off" :key="name" class="mt-0.5 text-slate-500" title="Turned off in your Project defaults"><s>{{ name }}</s> off in your defaults</div>
                            </section>

                            <section>
                                <div :class="label">Git</div>
                                <div class="mt-2">Git: {{ previews[b.key].settings.git.on ? 'On' : 'Off' }} <span v-if="previews[b.key].settings.git.from === 'defaults'" class="text-xs text-slate-500" title="From your Project defaults">· your default</span></div>
                            </section>

                            <!-- Hooks need Git, so they only show with it on -->
                            <section v-if="previews[b.key].settings.git.on">
                                <div :class="label">Git hooks</div>
                                <ul class="mt-2 flex flex-col gap-1">
                                    <li v-for="hook in previews[b.key].settings.hooks.on" :key="hook">{{ hookNames[hook] ?? hook }}</li>
                                    <li v-if="!previews[b.key].settings.hooks.on.length" class="text-slate-500">None</li>
                                </ul>
                            </section>

                            <section>
                                <div :class="label">Sensitive data</div>
                                <div class="mt-2">{{ previews[b.key].settings.sensitive.on ? 'On' : 'Off' }} <span class="text-xs text-slate-500" :title="previews[b.key].settings.sensitive.from === 'defaults' ? 'From your Project defaults' : 'Set by the blueprint'">· {{ previews[b.key].settings.sensitive.from === 'defaults' ? 'your default' : 'blueprint' }}</span></div>
                            </section>
                        </div>

                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 border-t border-slate-200 pt-3 text-xs text-slate-500 xl:col-span-2 dark:border-slate-800">
                            <span class="font-mono">{{ b.handle }} v{{ b.version }}</span>
                            <Link :href="`/blueprints/${b.id}`" :class="link">View the blueprint</Link>
                            <span>Preview with your defaults, including unsaved changes</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </fieldset>
</template>
