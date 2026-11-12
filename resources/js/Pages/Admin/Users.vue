<script setup>
import { computed, ref } from 'vue';
import { MagnifyingGlassIcon } from '@heroicons/vue/24/outline';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import UiBadge from '@/Components/UiBadge.vue';
import UiTable from '@/Components/UiTable.vue';
import { inputClass } from '@/ui.js';

// Everyone with an account on this server, newest first
const props = defineProps({ users: Array, summary: Object });

const search = ref('');
const rows = computed(() => {
    const terms = search.value.trim().toLocaleLowerCase().split(/\s+/).filter(Boolean);
    return props.users.filter((u) => terms.every((t) => `${u.name} ${u.email}`.toLocaleLowerCase().includes(t)));
});
const date = (value) => (value ? new Date(value).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' }) : '—');
const stats = computed(() => [
    { label: 'Accounts', value: props.summary.total },
    { label: 'New this week', value: props.summary.week },
    { label: 'Verified email', value: props.summary.verified },
    { label: 'Finished setup', value: props.summary.setup_done },
]);
</script>

<template>
    <Head title="Users" />
    <AppLayout>
        <div class="flex min-w-0 flex-col gap-7">
            <PageHeader title="Users" description="Everyone with an account on this server, newest first." />
            <dl class="flex flex-wrap gap-x-8 gap-y-4 border-b border-slate-800/10 pb-5 dark:border-white/15">
                <div v-for="stat in stats" :key="stat.label" class="flex items-baseline gap-2 text-sm text-slate-500 dark:text-slate-400">
                    <dt>{{ stat.label }}</dt><dd class="font-mono text-base font-medium text-slate-900 dark:text-white">{{ stat.value }}</dd>
                </div>
            </dl>
            <div class="relative max-w-md">
                <label for="user-search" class="sr-only">Search users</label>
                <MagnifyingGlassIcon class="pointer-events-none absolute top-3 left-3 size-4 text-slate-400" aria-hidden="true" />
                <input id="user-search" v-model="search" type="search" placeholder="Search by name or email" :class="[inputClass, 'pl-9']" />
            </div>
            <UiTable v-if="rows.length" label="Users" class="users-table">
                <thead><tr><th>Name</th><th class="max-md:hidden">Signed up</th><th class="max-lg:hidden">Last active</th><th class="max-sm:hidden">Projects</th><th>Status</th></tr></thead>
                <tbody><tr v-for="user in rows" :key="user.id">
                    <td class="min-w-0">
                        <span class="block truncate font-medium text-slate-900 dark:text-white">{{ user.name }}</span>
                        <a :href="`mailto:${user.email}`" class="block truncate text-xs text-slate-500 hover:underline dark:text-slate-400">{{ user.email }}</a>
                    </td>
                    <td class="max-md:hidden">{{ date(user.created_at) }}</td>
                    <td class="max-lg:hidden">{{ date(user.last_active) }}</td>
                    <td class="max-sm:hidden font-mono">{{ user.projects }}</td>
                    <td><div class="flex flex-wrap gap-1.5">
                        <UiBadge v-if="user.is_admin" variant="sky">Admin</UiBadge>
                        <UiBadge v-if="!user.verified" variant="amber">Unverified</UiBadge>
                        <UiBadge v-if="user.verified && !user.setup_done">Setup not finished</UiBadge>
                        <UiBadge v-if="user.can_publish" variant="green">Publishing</UiBadge>
                    </div></td>
                </tr></tbody>
            </UiTable>
            <p v-else class="py-4 text-sm text-slate-500 dark:text-slate-400">No users match that search.</p>
        </div>
    </AppLayout>
</template>
