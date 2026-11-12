<script setup>
import UiInput from '@/Components/UiInput.vue';
import UiSelect from '@/Components/UiSelect.vue';
import UiButton from '@/Components/UiButton.vue';
import UiSection from '@/Components/UiSection.vue';
import UiSeparator from '@/Components/UiSeparator.vue';
import { countryOptions } from '@/countries.js';

defineProps({ form: { type: Object, required: true } });
const blank = () => ({ name: '', department: '', city: '', country: '' });
</script>

<template>
    <UiSection title="Author details" description="Used in new projects, notebooks, reports, and slides.">
        <UiInput v-model="form.name" label="Name" placeholder="Full name for bylines" autocomplete="name" required :error="form.errors.name" />
        <UiInput v-model="form.email" label="Public email (optional)" type="email" placeholder="you@example.org" description="Shown on your work. Your sign-in email stays private unless you enter it here." :error="form.errors.email" />
        <div class="grid gap-6 sm:grid-cols-2">
            <UiInput v-model="form.url" label="Website (optional)" type="url" placeholder="https://example.org" :error="form.errors.url" />
            <UiInput v-model="form.orcid" label="ORCID iD (optional)" placeholder="0000-0002-1825-0097" :error="form.errors.orcid" />
        </div>
    </UiSection>
    <UiSeparator />
    <UiSection title="Affiliations" description="We can use this in notebooks/reports you publish.">
        <div v-for="(affiliation, index) in form.affiliations" :key="index" class="flex flex-col gap-4 border-b border-slate-800/10 pb-6 dark:border-white/15">
            <div class="flex items-center justify-between gap-3">
                <h3 class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ index === 0 ? 'Main affiliation' : `Affiliation ${index + 1}` }}</h3>
                <div class="flex gap-1">
                    <UiButton v-if="index > 0" size="sm" variant="ghost" @click="form.affiliations.splice(index - 1, 0, ...form.affiliations.splice(index, 1))">Move up</UiButton>
                    <UiButton size="sm" variant="ghost" @click="form.affiliations.splice(index, 1)">Remove</UiButton>
                </div>
            </div>
            <UiInput v-model="affiliation.name" label="Institution" placeholder="Example University" required :error="form.errors[`affiliations.${index}.name`]" />
            <UiInput v-model="affiliation.department" label="Department (optional)" placeholder="Department of Epidemiology" :error="form.errors[`affiliations.${index}.department`]" />
            <div class="grid gap-6 sm:grid-cols-2">
                <UiInput v-model="affiliation.city" label="City (optional)" placeholder="Boston" :error="form.errors[`affiliations.${index}.city`]" />
                <UiSelect v-model="affiliation.country" label="Country (optional)" :options="countryOptions(affiliation.country)" :error="form.errors[`affiliations.${index}.country`]" />
            </div>
        </div>
        <p v-if="form.errors.affiliations" role="alert" class="text-sm text-red-600">{{ form.errors.affiliations }}</p>
        <div><UiButton v-if="form.affiliations.length < 5" variant="ghost" size="sm" @click="form.affiliations.push(blank())">Add affiliation</UiButton></div>
    </UiSection>
</template>
