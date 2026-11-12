<script setup>
import { computed } from 'vue';
import CopyBlock from '@/Components/CopyBlock.vue';
import InfoPopover from '@/Components/InfoPopover.vue';
import CollapsibleSection from '@/Components/CollapsibleSection.vue';

// Ways to publish results from a project, in its language. The section folds,
// and remembers that on the account.
const props = defineProps({
    language: { type: String, required: true }, // r | python | stata | fw
    remember: { type: String, required: true }, // the preference key for collapsing
    heading: { type: String, default: 'Publish results' },
});

const visibility = 'New artifacts are unlisted: only people with the link can see them. Set visibility to public for a readable link.';
const examples = computed(() => ({
    r: [
        { label: 'Rendered notebook', cmd: 'framework::publish("outputs/notebooks/analysis.html", slug = "analysis")', info: `Publishes a rendered HTML notebook to Framework Cloud. ${visibility}` },
        { label: 'Figure', cmd: 'framework::publish(plot, slug = "main-figure", title = "Main figure")', info: 'Publishes a ggplot or base R plot object as a figure. A saved PNG or SVG file works too.' },
        { label: 'Table', cmd: 'framework::publish(results, slug = "results-table")', info: 'Publishes a data frame as a table that readers can sort and download.' },
        { label: 'Model', cmd: 'framework::publish(model, slug = "main-model")', info: 'Publishes an lm or glm model with its estimates, ready to place in a report.' },
        { label: 'GitHub Pages', cmd: 'framework::publish("outputs/notebooks/analysis.html", to = "github")', info: 'Publishes to the project\'s GitHub Pages site instead of Framework Cloud.' },
    ],
    python: [
        { label: 'Rendered notebook', cmd: 'import framework\nframework.publish("outputs/notebooks/analysis.html", slug="analysis")', info: `Publishes a rendered HTML notebook to Framework Cloud. ${visibility}` },
        { label: 'Figure', cmd: 'framework.publish(fig, slug="main-figure", title="Main figure")', info: 'Publishes a matplotlib or seaborn figure. A saved PNG or SVG file works too.' },
        { label: 'Table', cmd: 'framework.publish(results, slug="results-table")', info: 'Publishes a pandas DataFrame as a table that readers can sort and download.' },
        { label: 'Model', cmd: 'framework.publish(model, slug="main-model")', info: 'Publishes fitted model results with their estimates, ready to place in a report.' },
        { label: 'GitHub Pages', cmd: 'framework.publish("outputs/notebooks/analysis.html", to="github")', info: 'Publishes to the project\'s GitHub Pages site instead of Framework Cloud.' },
    ],
    stata: [
        { label: 'Figure', cmd: 'fw_publish outputs/figures/main.png, slug(main-figure) title("Main figure")', info: `Publishes a saved graph. ${visibility}` },
        { label: 'Table', cmd: 'fw_publish outputs/tables/results.csv, slug(results-table)', info: 'Publishes a saved table that readers can sort and download.' },
        { label: 'Estimates', cmd: 'regress price mpg weight\nfw_publish, estimates slug(main-model)', info: 'Publishes the results of the last estimation command, ready to place in a report.' },
        { label: 'GitHub Pages', cmd: 'fw_publish outputs/docs/report.html, to(github)', info: 'Publishes to the project\'s GitHub Pages site instead of Framework Cloud.' },
    ],
    fw: [
        { label: 'Rendered notebook', cmd: 'fw publish outputs/notebooks/analysis.html --slug analysis', info: `Run it inside the project folder. ${visibility}` },
        { label: 'Public link', cmd: 'fw publish outputs/notebooks/analysis.html --visibility public', info: 'Publishes with a public, readable link.' },
        { label: 'GitHub Pages', cmd: 'fw publish outputs/notebooks/analysis.html --to github', info: 'Publishes to the project\'s GitHub Pages site instead of Framework Cloud.' },
    ],
}[props.language] ?? []));
const highlighting = { r: 'r', python: 'python', stata: 'stata', fw: 'bash' };
</script>

<template>
    <CollapsibleSection v-if="examples.length" :title="heading" :remember="remember">
        <div class="grid gap-x-6 gap-y-5 lg:grid-cols-2">
            <div v-for="example in examples" :key="`${language}-${example.label}`" class="min-w-0">
                <h3 class="mb-2 flex items-center gap-1.5 text-sm font-medium text-slate-800 dark:text-slate-200">
                    {{ example.label }}
                    <InfoPopover :label="example.label">{{ example.info }}</InfoPopover>
                </h3>
                <CopyBlock :value="example.cmd" :label="`publish ${example.label.toLowerCase()}`" :language="highlighting[language]" />
            </div>
        </div>
    </CollapsibleSection>
</template>
