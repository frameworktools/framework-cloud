<script setup>
import { computed } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import SettingsLayout from '@/Layouts/SettingsLayout.vue';
import UiButton from '@/Components/UiButton.vue';
import UiInput from '@/Components/UiInput.vue';
import UiPanel from '@/Components/UiPanel.vue';

const props = defineProps({ author: Object });

const page = usePage();
const saved = computed(() => page.props.flash.status === 'author-updated');

const form = useForm({ ...props.author, affiliations: props.author.affiliations.map((a) => ({ ...a })) });
const save = () => form.patch('/settings/profile', { preserveScroll: true });

const MAX = 5;
const blank = () => ({ name: '', department: '', city: '', country: '' });
const add = () => form.affiliations.push(blank());
const remove = (index) => form.affiliations.splice(index, 1);
const move = (index, by) => {
    const [row] = form.affiliations.splice(index, 1);
    form.affiliations.splice(index + by, 0, row);
};

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
    <Head title="Profile" />

    <SettingsLayout
        heading="Profile"
        subheading="How you appear as an author. Framework puts this in the front matter of new notebooks, reports, and slides."
    >
        <form id="settings-form" class="flex flex-col gap-8" @submit.prevent="save">
            <UiPanel title="You">
                <UiInput v-model="form.name" label="Name" description="As it should appear on a byline." required :error="form.errors.name" />
                <UiInput v-model="form.email" label="Public email" type="email" description="Shown on your work. Your sign-in email under Settings > Account stays private." :error="form.errors.email" />
                <div class="grid gap-4 sm:grid-cols-2">
                    <UiInput v-model="form.orcid" label="ORCID iD" placeholder="0000-0002-1825-0097" :error="form.errors.orcid" />
                    <UiInput v-model="form.url" label="Website" type="url" placeholder="https://" :error="form.errors.url" />
                </div>
            </UiPanel>

            <UiPanel title="Affiliations" description="The first one is your main affiliation.">
                <div
                    v-for="(affiliation, index) in form.affiliations" :key="index"
                    class="flex flex-col gap-3 rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900"
                >
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ index === 0 ? 'Main' : `Affiliation ${index + 1}` }}</span>
                        <div class="flex gap-1">
                            <UiButton v-if="index > 0" variant="ghost" size="sm" @click="move(index, -1)">Move up</UiButton>
                            <UiButton variant="ghost" size="sm" @click="remove(index)">Remove</UiButton>
                        </div>
                    </div>
                    <UiInput v-model="affiliation.name" label="Institution" placeholder="e.g. Johns Hopkins University" :error="form.errors[`affiliations.${index}.name`]" />
                    <UiInput v-model="affiliation.department" label="Department (optional)" />
                    <div class="grid gap-4 sm:grid-cols-2">
                        <UiInput v-model="affiliation.city" label="City (optional)" />
                        <UiInput v-model="affiliation.country" label="Country (optional)" />
                    </div>
                </div>

                <div>
                    <UiButton v-if="form.affiliations.length < MAX" variant="outline" size="sm" @click="add">Add affiliation</UiButton>
                </div>
            </UiPanel>

            <UiPanel title="In Quarto" description="How new notebooks will list you.">
                <pre class="overflow-x-auto rounded-lg border border-slate-200 bg-white p-4 font-mono text-xs leading-5 text-slate-800 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">{{ yaml }}</pre>
            </UiPanel>
        </form>

        <template #actions>
            <UiButton type="submit" form="settings-form" class="w-full" :disabled="form.processing">
                {{ form.processing ? 'Saving…' : 'Save changes' }}
            </UiButton>
            <p v-if="saved" class="text-center text-xs text-slate-500">Saved.</p>
        </template>
    </SettingsLayout>
</template>
