<script setup>
import { onBeforeUnmount, ref, watch } from 'vue';
import { cachedPreview, fetchPreview } from '@/blueprintPreviews.js';
import { usePage } from '@inertiajs/vue3';
import { ChevronRightIcon } from '@heroicons/vue/20/solid';
import ProjectTreeView from '@/Components/ProjectTreeView.vue';
import { agentSummary } from '@/agents.js';

/**
 * What a blueprint creates, for one language: the folder tree and what else
 * the project gets, resolved on the server. Shared by the Blueprints page
 * and guided setup, so both show exactly what creation writes.
 */
const props = defineProps({
    blueprint: { type: Object, required: true }, // { key, handle, version, id }
    language: { type: String, required: true },
    values: { type: Object, default: () => ({}) }, // guided setup's answers, for the workflow
    // Only the folder tree, without the summary beside it
    treeOnly: Boolean,
    compact: Boolean,
    // Where to resolve it instead, as { url, body }: the Blueprints page's own preview
    source: Object,
});
const page = usePage();

const url = () => props.source?.url ?? '/settings/blueprint/preview';
const body = () => props.source?.body ?? { blueprint: props.blueprint.key, language: props.language, values: props.values };

const preview = ref(cachedPreview(url(), body()) ?? null);
const loading = ref(false);
let request = 0;
const load = async () => {
    const id = ++request;
    const cached = cachedPreview(url(), body());
    if (cached) preview.value = cached;
    // A cached preview is rechecked quietly
    loading.value = !cached;
    try {
        const data = await fetchPreview(url(), body(), page.props.csrf);
        // A later request may have finished first
        if (id === request) preview.value = data;
    } catch {
        if (id === request && !cached) preview.value = { available: false, problem: "The preview couldn't be loaded." };
    } finally {
        if (id === request) loading.value = false;
    }
};
let timer = null;
watch([() => props.blueprint.key, () => props.language, () => JSON.stringify(props.values), () => JSON.stringify(props.source ?? null)], (_, old) => {
    clearTimeout(timer);
    // Load at once when opened; wait out typing in the settings form
    if (!old) load();
    else timer = setTimeout(load, props.source ? 0 : 150);
}, { immediate: true });
onBeforeUnmount(() => clearTimeout(timer));

const hookNames = { data_security: 'Check for secrets', check_sensitive_dirs: 'Check private folders', check_notebook_outputs: 'Check notebook outputs' };
const label = 'text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400';
const link = 'text-sky-700 hover:underline dark:text-sky-400';
</script>

<template>
    <div v-if="treeOnly" :class="loading ? 'opacity-60' : ''">
        <div v-if="!preview" class="text-sm text-slate-500 dark:text-slate-400">Loading…</div>
        <p v-else-if="!preview.available" class="text-sm text-slate-600 dark:text-slate-400">{{ preview.problem }}</p>
        <ProjectTreeView v-else :entries="preview.tree" />
    </div>
    <section v-else-if="compact" aria-label="Starting blueprint preview" :aria-busy="loading" class="flex min-w-0 flex-col gap-6" :class="loading ? 'opacity-60' : ''">
        <div>
            <h2 class="text-base font-medium text-slate-900 dark:text-white">{{ blueprint.name }}</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ blueprint.description }}</p>
        </div>
        <p v-if="!preview" role="status" class="text-sm text-slate-500 dark:text-slate-400">Loading preview…</p>
        <p v-else-if="!preview.available" role="alert" class="text-sm text-slate-600 dark:text-slate-400">{{ preview.problem }}</p>
        <template v-else>
            <dl class="grid grid-cols-[auto_minmax(0,1fr)] gap-x-6 gap-y-3 text-sm">
                <dt class="text-slate-500 dark:text-slate-400">Notebook format</dt><dd class="text-slate-800 dark:text-slate-200">{{ { quarto: 'Quarto', jupyter: 'Jupyter', scripts: 'Scripts' }[preview.workflow] ?? preview.workflow }}</dd>
                <dt class="text-slate-500 dark:text-slate-400">Packages</dt><dd class="text-slate-800 dark:text-slate-200">{{ preview.packages.map(p => p.name).join(', ') || 'None' }}</dd>
                <dt class="text-slate-500 dark:text-slate-400">AI skills</dt><dd class="text-slate-800 dark:text-slate-200">{{ [...preview.skill_sets.map(s => s.name), ...preview.skills].join(', ') || 'None' }}</dd>
                <dt class="text-slate-500 dark:text-slate-400">Git</dt><dd class="text-slate-800 dark:text-slate-200">{{ preview.settings.git.on ? 'On' : 'Off' }}</dd>
                <dt class="text-slate-500 dark:text-slate-400">Sensitive data</dt><dd class="text-slate-800 dark:text-slate-200">{{ preview.settings.sensitive.on ? 'On' : 'Off' }}</dd>
            </dl>
            <div class="border-t border-slate-800/10 pt-6 dark:border-white/15">
                <h3 class="mb-3 text-sm font-medium text-slate-900 dark:text-white">Project structure</h3>
                <ProjectTreeView :entries="preview.tree" />
            </div>
        </template>
    </section>
    <div v-else class="grid gap-x-8 gap-y-5 border-t border-slate-200 pt-4 xl:grid-cols-[minmax(0,1fr)_16rem] dark:border-slate-800">
        <!-- What the resolver set aside, such as a workflow the blueprint doesn't support -->
        <ul v-if="preview?.notices?.length" class="flex flex-col gap-1 text-sm text-amber-800 xl:col-span-2 dark:text-amber-300">
            <li v-for="n in preview.notices" :key="n">{{ n }}</li>
        </ul>

        <!-- The project's folders and files -->
        <div class="min-w-0">
            <div :class="label" class="mb-2">Project structure</div>
            <div v-if="!preview" class="text-sm text-slate-500 dark:text-slate-400">Loading…</div>
            <p v-else-if="!preview.available" class="text-sm text-slate-600 dark:text-slate-400">{{ preview.problem }}</p>
            <ProjectTreeView v-else :entries="preview.tree" :class="loading ? 'opacity-60' : ''" />
        </div>

        <!-- What else it gets, most important first -->
        <div v-if="preview?.available" class="flex min-w-0 flex-col gap-6 text-sm text-slate-700 xl:border-l xl:border-slate-200 xl:pl-8 dark:text-slate-300 xl:dark:border-slate-800" :class="loading ? 'opacity-60' : ''">
            <section>
                <div :class="label">Packages</div>
                <ul class="mt-1 flex flex-col gap-1">
                    <li v-for="p in preview.packages" :key="p.name" class="flex flex-wrap items-baseline gap-x-1.5">
                        <span class="font-mono text-xs">{{ p.name }}</span>
                        <span v-if="p.from" class="text-xs text-slate-500 dark:text-slate-400" :title="`From ${p.from}`">with {{ p.from }}</span>
                    </li>
                    <li v-if="!preview.packages.length" class="text-slate-500 dark:text-slate-400">None</li>
                </ul>
            </section>

            <section>
                <div :class="label">AI agents</div>
                <div class="mt-1">{{ agentSummary(preview.agents, preview.agent_keys, preview.agent_other) }}</div>
            </section>

            <!-- Each skill on its own line, under the set it comes from -->
            <section>
                <div :class="label">Skills</div>
                <p v-if="!preview.agents" class="mt-1 text-slate-500 dark:text-slate-400">None, with agent setup off</p>
                <template v-else>
                    <!-- A set opens to its skills -->
                    <details v-for="set in preview.skill_sets" :key="set.id" class="group mt-2 first-of-type:mt-1">
                        <summary class="flex cursor-pointer list-none items-center gap-1 [&::-webkit-details-marker]:hidden">
                            <ChevronRightIcon class="-ml-1 size-4 shrink-0 text-slate-400 transition-transform group-open:rotate-90" />
                            <span>{{ set.name }}</span>
                        </summary>
                        <ul class="mb-1 mt-1.5 flex flex-col gap-1 pl-4 text-xs text-slate-600 dark:text-slate-400">
                            <li v-for="skill in set.skills" :key="skill">{{ skill }}</li>
                        </ul>
                    </details>
                    <ul v-if="preview.skills.length" class="mt-2 flex flex-col gap-1 pl-4">
                        <li v-for="skill in preview.skills" :key="skill">{{ skill }}</li>
                    </ul>
                    <p v-if="!preview.skill_sets.length && !preview.skills.length" class="mt-1 text-slate-500 dark:text-slate-400">None</p>
                </template>
            </section>

            <section class="text-xs text-slate-600 dark:text-slate-400">
                <div :class="label">Tools</div>
                <div class="mt-1">
                    <template v-for="(t, i) in preview.tools" :key="t.name">{{ i ? ' · ' : '' }}{{ t.name }}</template>
                    <span v-if="!preview.tools.length">None</span>
                </div>
            </section>

            <section>
                <div :class="label">Git</div>
                <div class="mt-1">{{ preview.settings.git.on ? 'On' : 'Off' }}</div>
            </section>

            <!-- Hooks need Git, so they only show with it on -->
            <section v-if="preview.settings.git.on">
                <div :class="label">Data safety hooks</div>
                <ul class="mt-1 flex flex-col gap-1">
                    <li v-for="hook in preview.settings.hooks.on" :key="hook">{{ hookNames[hook] ?? hook }}</li>
                    <li v-if="!preview.settings.hooks.on.length" class="text-slate-500 dark:text-slate-400">None</li>
                </ul>
            </section>

            <section>
                <div :class="label">Sensitive data</div>
                <div class="mt-1">{{ preview.settings.sensitive.on ? 'On' : 'Off' }}</div>
            </section>
        </div>

        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 border-t border-slate-200 pt-3 text-xs text-slate-500 dark:text-slate-400 xl:col-span-2 dark:border-slate-800">
            <span class="font-mono">{{ blueprint.handle }} v{{ blueprint.version }}</span>
            <Link :href="`/blueprints/${blueprint.id}`" :class="link">View the blueprint</Link>
        </div>
    </div>
</template>
