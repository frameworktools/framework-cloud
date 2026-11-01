<script setup>
import { computed, ref, watch } from 'vue';
import { ChevronRightIcon } from '@heroicons/vue/20/solid';
import BlueprintPreview from '@/Components/BlueprintPreview.vue';

/**
 * The default blueprint, picked from a list. Each row's details open on
 * their own and never change the choice. An open row previews the project
 * the blueprint would create as the default, resolved on the server with the
 * form's values, saved or not, for the language picked in the preview.
 */
const props = defineProps({
    blueprints: { type: Array, required: true },
    defaults: { type: Object, required: true }, // the Project defaults form
    language: { type: String, required: true }, // the language the page is set to
});
const model = defineModel({ type: String });

// The chosen blueprint starts open; Details opens others alongside it to compare
const open = ref(new Set([model.value]));
// Choosing a blueprint opens it alone; opening one with Details never chooses it
watch(model, (key) => {
    if (key) open.value = new Set([key]);
});
const toggleOpen = (key) => {
    const next = new Set(open.value);
    next.has(key) ? next.delete(key) : next.add(key);
    open.value = next;
};

// Previews are for the page's language; a blueprint without it can't be chosen for it
const languageNames = { r: 'R', python: 'Python', stata: 'Stata' };
const supports = (b) => b.languages.includes(props.language);
const workflowNames = { quarto: 'Quarto', jupyter: 'Jupyter', scripts: 'Scripts only' };
const workflowsFor = (b) => b.workflows.filter((w) => props.language !== 'stata' || w === 'scripts');

// What the server resolves the previews from: every default that changes a new project
const values = computed(() => {
    const d = props.defaults;
    return { ide: d.ide, packages: d.packages, skill_sets: d.skill_sets, tools: d.tools, use_renv: d.use_renv, use_git: d.use_git, ai_enabled: d.ai_enabled, ai_agents: d.ai_agents, hooks: d.hooks, workflows: d.workflows, rmarkdown: d.rmarkdown, quarto: d.quarto, scaffold: d.scaffold };
});
</script>

<template>
    <fieldset>
        <legend class="sr-only">Default blueprint</legend>
        <!-- What the previews are for, such as a language and workflow -->
        <div v-if="$slots.default" class="mb-3"><slot /></div>
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
                            <span class="mt-0.5 text-xs text-slate-500">
                                <template v-if="supports(b)">Workflows: {{ workflowsFor(b).map((w) => workflowNames[w]).join(', ') }}</template>
                                <template v-else>Not available for {{ languageNames[language] }}: new {{ languageNames[language] }} projects use the standard project instead.</template>
                            </span>
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
                    <BlueprintPreview :blueprint="b" :language="language" :values="values" />
                </div>
            </div>
        </div>
    </fieldset>
</template>
