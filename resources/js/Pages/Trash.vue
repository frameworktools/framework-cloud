<script setup>
import { router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import UiButton from '@/Components/UiButton.vue';
import UiTable from '@/Components/UiTable.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { TrashIcon } from '@heroicons/vue/24/outline';

defineProps({ items: Array });
const page = usePage();
const error = computed(() => page.props.errors?.restore);
const labels = { project: 'Project', report: 'Report', skill: 'Skill' };
const restore = (item) => router.post(`/trash/${item.type}/${item.id}/restore`, {}, { preserveScroll: true });
</script>

<template>
    <Head title="Trash" />

    <AppLayout>
        <div class="flex max-w-4xl flex-col gap-8">
            <PageHeader title="Trash">
                <template #description>
                    Projects, reports, and skills deleted by you or a connected agent. Restore one to bring it back.
                    A deleted project's artifacts, reports, and collections stop opening until the project is restored.
                </template>
            </PageHeader>

            <p v-if="error" class="text-sm text-red-600 dark:text-red-400">{{ error }}</p>

            <UiTable v-if="items.length" label="Deleted items">
                <thead><tr><th>Name</th><th>Type</th><th class="max-sm:hidden">Deleted</th><th class="actions"><span class="sr-only">Actions</span></th></tr></thead>
                <tbody>
                    <tr v-for="item in items" :key="`${item.type}-${item.id}`">
                        <td class="strong">{{ item.name }}<span v-if="item.detail" class="ms-2 font-normal text-slate-500">{{ item.detail }}</span></td>
                        <td>{{ labels[item.type] }}</td>
                        <td class="max-sm:hidden"><time :datetime="item.deleted_iso">{{ item.deleted }}</time></td>
                        <td class="actions"><UiButton variant="ghost" size="sm" @click="restore(item)">Restore</UiButton></td>
                    </tr>
                </tbody>
            </UiTable>
            <EmptyState v-else :icon="TrashIcon" title="Trash is empty" description="Deleted projects, reports, and skills appear here." />
        </div>
    </AppLayout>
</template>
