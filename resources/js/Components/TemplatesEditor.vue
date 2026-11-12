<script setup>
import { computed } from 'vue';
import UiButton from '@/Components/UiButton.vue';
import UiTextarea from '@/Components/UiTextarea.vue';

// The templates make_notebook(), make_script(), and make_revealjs() start
// new files from, as files in the project's stubs/ folder. Empty uses
// Framework's own. Edits form.templates in place.
const props = defineProps({ form: Object, catalog: Object });
const f = props.form;

// The notebook format sets the notebook template's file; scripts have no notebooks or slides
const format = computed(() => (f.workflow === 'scripts' ? null : f.notebook_format ?? (f.workflow === 'jupyter' ? 'jupyter' : 'quarto')));
const documents = computed(() => ['quarto', 'rmarkdown'].includes(format.value));
const defaults = computed(() => props.catalog.templates?.[f.language] ?? {});
const kinds = computed(() => [
    {
        key: 'notebook', title: 'Notebook template', fn: 'make_notebook()',
        path: format.value === 'rmarkdown' && f.language === 'r' ? 'stubs/notebook-default.Rmd' : 'stubs/notebook-default.qmd',
        shown: documents.value, framework: defaults.value.notebook?.[format.value] ?? defaults.value.notebook?.quarto ?? '',
    },
    {
        key: 'script', title: 'Script template', fn: 'make_script()',
        path: f.language === 'python' ? 'stubs/script-default.py' : 'stubs/script-default.R',
        shown: true, framework: defaults.value.script ?? '',
    },
    {
        key: 'slides', title: 'Slides template', fn: 'make_revealjs()',
        path: 'stubs/notebook-revealjs.qmd', shown: documents.value, framework: defaults.value.slides ?? '',
    },
].filter((kind) => kind.shown));
const uses = (kind) => (f.templates[kind.key] ?? '').trim() !== '';
const error = (kind) => f.errors[`templates.${kind.key}`];
</script>

<template>
    <p v-if="form.language === 'stata'" class="text-sm text-slate-500 dark:text-slate-400">Stata projects have no templates.</p>
    <div v-else class="flex max-w-3xl flex-col gap-8">
        <p v-if="format === 'jupyter'" class="text-sm text-slate-500 dark:text-slate-400">Jupyter notebooks start from Framework's template. The script template applies.</p>
        <section v-for="kind in kinds" :key="kind.key" class="flex flex-col gap-3">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <h3 class="text-sm font-medium text-slate-900 dark:text-white">{{ kind.title }}</h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        <code class="font-mono text-xs">{{ kind.fn }}</code> starts from <code class="font-mono text-xs">{{ kind.path }}</code>.
                        {{ uses(kind) ? 'This template replaces Framework\'s.' : 'Empty uses Framework\'s template.' }}
                    </p>
                </div>
                <UiButton v-if="!uses(kind)" size="sm" variant="ghost" @click="form.templates[kind.key] = kind.framework">Copy Framework's template</UiButton>
                <UiButton v-else size="sm" variant="ghost" @click="form.templates[kind.key] = ''">Use Framework's template</UiButton>
            </div>
            <UiTextarea v-model="form.templates[kind.key]" code :rows="uses(kind) ? 14 : 4" :aria-label="kind.title" :placeholder="kind.framework.split('\n').slice(0, 4).join('\n')" :error="error(kind)" />
        </section>
        <p class="text-sm text-slate-500 dark:text-slate-400">
            Templates can use <code class="font-mono text-xs" v-pre>{{ title }}</code>, <code class="font-mono text-xs" v-pre>{{ filename }}</code>, <code class="font-mono text-xs" v-pre>{{ date }}</code>, <code class="font-mono text-xs" v-pre>{{ author }}</code>, <code class="font-mono text-xs" v-pre>{{ email }}</code>, and <code class="font-mono text-xs" v-pre>{{ affiliation }}</code>. Each new file fills them in.
        </p>
    </div>
</template>
