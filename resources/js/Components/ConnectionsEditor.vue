<script setup>
import { computed, useId } from 'vue';
import { PlusIcon, TrashIcon } from '@heroicons/vue/20/solid';
import UiButton from '@/Components/UiButton.vue';
import UiField from '@/Components/UiField.vue';
import UiInput from '@/Components/UiInput.vue';
import UiSelect from '@/Components/UiSelect.vue';
import {
    bucketDrivers, changeDriver, connectionLanguages, databaseDrivers, driverNeeds, envRef, envVar, fields, newConnection, rename, suggested, variables,
} from '@/connections.js';

// The databases and storage buckets a project connects to. Edits form.connections in place.
const props = defineProps({ form: Object });
const f = props.form;
const uid = useId();

const supported = computed(() => connectionLanguages.includes(f.language));
const kinds = [
    { key: 'databases', title: 'Databases', add: 'Add database', drivers: databaseDrivers, empty: 'No databases. Every project has its own framework.db.' },
    { key: 'storage_buckets', title: 'Storage buckets', add: 'Add storage bucket', drivers: bucketDrivers, empty: 'No storage buckets.' },
];
const driverOf = (kind, row) => kind.drivers.find((d) => d.value === row.driver);
const add = (kind) => { f.connections[kind.key] = [...f.connections[kind.key], newConnection(kind.key, f.connections[kind.key])]; };
const remove = (kind, i) => {
    const [gone] = f.connections[kind.key].splice(i, 1);
    const key = kind.key === 'databases' ? 'default_database' : 'default_storage_bucket';
    if (f.connections[key] === gone?.name) f.connections[key] = '';
};
const setName = (kind, i, name) => {
    const row = f.connections[kind.key][i];
    const key = kind.key === 'databases' ? 'default_database' : 'default_storage_bucket';
    if (f.connections[key] === row.name) f.connections[key] = name;
    f.connections[kind.key][i] = rename(row, name);
};
const setDriver = (kind, i, driver) => { f.connections[kind.key][i] = changeDriver(kind.key, f.connections[kind.key][i], driver); };
// A secret is always env("NAME"); the input holds only the name
const secretName = (row, field) => envVar(row[field]) ?? '';
const setSecret = (row, field, name) => { row[field] = name.trim() ? envRef(name.trim()) : ''; };

const error = (kind, i, field) => f.errors[`connections.${kind.key}.${i}.${field}`];
const defaults = computed(() => ({
    databases: [{ value: '', label: 'framework (the project\'s framework.db)' }, ...f.connections.databases.filter((r) => r.name).map((r) => ({ value: r.name, label: r.name }))],
    storage_buckets: [{ value: '', label: 'Not set' }, ...f.connections.storage_buckets.filter((r) => r.name).map((r) => ({ value: r.name, label: r.name }))],
}));
const envNames = computed(() => variables(f.connections));
const generalError = computed(() => f.errors.connections);
</script>

<template>
    <p v-if="!supported" class="text-sm text-slate-500 dark:text-slate-400">Stata projects have no database or storage bucket connections. Framework for Stata has no functions that read them.</p>
    <div v-else class="flex max-w-2xl flex-col gap-8">
        <p class="text-sm text-slate-500 dark:text-slate-400">
            Connections go in settings/connections.yml, or settings.yml in flat settings. A value can be literal or env("NAME"), which reads the project's .env. Passwords and keys are always env().
        </p>

        <section v-for="kind in kinds" :key="kind.key" class="flex flex-col gap-4">
            <div class="flex items-center justify-between gap-3">
                <h3 class="text-sm font-medium text-slate-900 dark:text-white">{{ kind.title }}</h3>
                <UiButton variant="ghost" size="sm" @click="add(kind)"><PlusIcon class="-ml-1 mr-1 size-4" />{{ kind.add }}</UiButton>
            </div>
            <p v-if="!form.connections[kind.key].length" class="text-sm text-slate-500 dark:text-slate-400">{{ kind.empty }}</p>
            <div v-else class="flex flex-col divide-y divide-slate-800/10 border-y border-slate-800/10 dark:divide-white/15 dark:border-white/15">
                <div v-for="(row, i) in form.connections[kind.key]" :key="i" class="flex flex-col gap-4 py-5">
                    <div class="grid items-start gap-4 sm:grid-cols-[1fr_1fr_auto]">
                        <UiInput :model-value="row.name" label="Name" placeholder="study_db" :error="error(kind, i, 'name')" @update:model-value="setName(kind, i, $event)" />
                        <UiSelect :model-value="row.driver" label="Driver" :options="kind.drivers.map((d) => ({ value: d.value, label: d.label }))" :error="error(kind, i, 'driver')" @update:model-value="setDriver(kind, i, $event)" />
                        <UiButton variant="subtle" size="md" square class="sm:mt-7" :aria-label="`Remove ${row.name || 'connection'}`" @click="remove(kind, i)"><TrashIcon class="size-4" /></UiButton>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <template v-for="field in driverOf(kind, row)?.fields ?? []" :key="field">
                            <UiField v-if="fields[field].secret" :id="`${uid}-${kind.key}-${i}-${field}`" :label="fields[field].label" :optional="fields[field].optional" :error="error(kind, i, field)">
                                <!-- An input group: env(" and ") are fixed parts of the field, the name goes between -->
                                <div
                                    class="flex h-10 overflow-hidden rounded-lg border border-slate-200 border-b-slate-300/80 bg-white font-mono shadow-xs focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-sky-600 dark:border-white/10 dark:bg-white/5 dark:focus-within:outline-sky-500"
                                    :class="error(kind, i, field) ? 'border-red-500 dark:border-red-500' : ''"
                                >
                                    <span class="flex shrink-0 items-center border-r border-slate-200 bg-slate-50 px-3 text-sm text-slate-500 select-none dark:border-white/10 dark:bg-white/5 dark:text-slate-400" aria-hidden="true">env("</span>
                                    <input
                                        :id="`${uid}-${kind.key}-${i}-${field}`" :value="secretName(row, field)" :placeholder="suggested(row.name, field)"
                                        autocomplete="off" spellcheck="false" :aria-invalid="error(kind, i, field) ? 'true' : undefined"
                                        class="min-w-0 flex-1 bg-transparent px-3 text-base text-slate-800 placeholder:text-slate-400 focus:outline-none sm:text-sm dark:text-slate-200 dark:placeholder:text-slate-500"
                                        @input="setSecret(row, field, $event.target.value)"
                                    >
                                    <span class="flex shrink-0 items-center border-l border-slate-200 bg-slate-50 px-3 text-sm text-slate-500 select-none dark:border-white/10 dark:bg-white/5 dark:text-slate-400" aria-hidden="true">")</span>
                                </div>
                            </UiField>
                            <UiInput
                                v-else v-model="row[field]" :label="fields[field].label" :optional="fields[field].optional"
                                :placeholder="field === 'database' && driverOf(kind, row)?.file ? driverOf(kind, row).file : field === 'port' && row.driver === 'sqlserver' ? '1433' : fields[field].placeholder"
                                :error="error(kind, i, field)"
                            />
                        </template>
                    </div>
                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ driverNeeds[form.language]?.[row.driver] }}</p>
                </div>
            </div>
        </section>

        <div class="grid gap-4 sm:grid-cols-2">
            <UiSelect v-model="form.connections.default_database" label="Default database" :options="defaults.databases" :error="form.errors['connections.default_database']" />
            <UiSelect v-model="form.connections.default_storage_bucket" label="Default storage bucket" :options="defaults.storage_buckets" :error="form.errors['connections.default_storage_bucket']" />
        </div>

        <div v-if="envNames.length" class="flex flex-col gap-2">
            <h3 class="text-sm font-medium text-slate-900 dark:text-white">.env variables</h3>
            <p class="text-sm text-slate-500 dark:text-slate-400">Fill these in the project's .env on each computer. Framework Cloud never stores the values.</p>
            <ul class="flex flex-wrap gap-2 font-mono text-xs text-slate-700 dark:text-slate-300">
                <li v-for="name in envNames" :key="name" class="rounded-md border border-slate-800/10 px-2 py-1 dark:border-white/15">{{ name }}</li>
            </ul>
        </div>
        <p v-if="generalError" class="text-sm text-red-600 dark:text-red-400">{{ generalError }}</p>
    </div>
</template>
