<script setup>
import { computed } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import SettingsLayout from '@/Layouts/SettingsLayout.vue';
import UiButton from '@/Components/UiButton.vue';
import AuthorFields from '@/Components/AuthorFields.vue';
import UiPanel from '@/Components/UiPanel.vue';

const props = defineProps({ author: Object });

const page = usePage();
const saved = computed(() => page.props.flash.status === 'author-updated');

const form = useForm({ ...props.author, affiliations: props.author.affiliations.map((a) => ({ ...a })) });
const save = () => form.patch('/settings/personal-data', { preserveScroll: true });

// How Quarto front matter will show it
const yaml = computed(() => {
    const q = (v) => JSON.stringify(v);
    const lines = ['author:', `  - name: ${q(form.name || 'Your Name')}`];
    if (form.email) lines.push(`    email: ${form.email}`);
    if (form.orcid) lines.push(`    orcid: ${form.orcid}`);
    if (form.url) lines.push(`    url: ${form.url}`);
    const affiliations = form.affiliations.filter((a) => a.name);
    if (affiliations.length) {
        lines.push('    affiliations:');
        for (const a of affiliations) {
            lines.push(`      - name: ${q(a.name)}`);
            for (const key of ['department', 'city', 'country']) {
                if (a[key]) lines.push(`        ${key}: ${q(a[key])}`);
            }
        }
    }
    return lines.join('\n');
});
</script>

<template>
    <Head title="Personal data · Settings" />

    <SettingsLayout
        heading="Personal data"
        subheading="Author details for new projects, notebooks, reports, and slides."
        :dirty="form.isDirty"
    >
        <form id="settings-form" class="flex flex-col gap-8" @submit.prevent="save">
            <AuthorFields :form="form" />

            <UiPanel title="Quarto front matter" description="Author metadata for new notebooks.">
                <pre class="overflow-x-auto rounded-lg border border-slate-200 bg-white p-4 font-mono text-xs leading-5 text-slate-800 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">{{ yaml }}</pre>
            </UiPanel>
        </form>

        <template #actions>
            <UiButton save-shortcut type="submit" form="settings-form" class="w-full" :disabled="form.processing">
                {{ form.processing ? 'Saving…' : 'Save changes' }}
            </UiButton>
            <p v-if="saved" class="text-center text-xs text-slate-500 dark:text-slate-400">Saved.</p>
        </template>
    </SettingsLayout>
</template>
