<script setup>
import { router, usePage } from '@inertiajs/vue3';
import { ask } from '@/confirm.js';
import { computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import UiButton from '@/Components/UiButton.vue';
import UiTable from '@/Components/UiTable.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { TrashIcon } from '@heroicons/vue/24/outline';

defineProps({ items: Array, keepDays: Number });
const page = usePage();
const error = computed(() => page.props.errors?.restore);
const labels = { project: 'Project', artifact: 'Artifact', version: 'Artifact version', report: 'Report', skill: 'Skill' };
const restore = (item) => router.post(`/trash/${item.type}/${item.id}/restore`, {}, { preserveScroll: true });
const size = (bytes) => bytes >= 1048576 ? `${(bytes / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`;
const forever = async (item) => {
    if (await ask(`Delete “${item.name}”${item.type === 'version' ? ` (${item.detail})` : ''} forever? Its files are removed and it can't be restored.`)) {
        router.delete(`/trash/${item.type}/${item.id}`, { preserveScroll: true });
    }
};
</script>

<template>
    <Head title="Trash" />

    <AppLayout>
        <div class="flex max-w-4xl flex-col gap-8">
            <PageHeader title="Trash">
                <template #description>
                    Projects, artifacts, reports, and skills deleted by you or a connected agent. Restore one to bring it back.
                    A deleted project's artifacts, reports, collections, and tokens stop working until the project is restored.
                    Deleted artifacts keep their files, which count toward storage until you delete them forever.
                    <template v-if="keepDays">Everything here is deleted forever after {{ keepDays }} days.</template>
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
                        <td class="actions">
                            <div class="flex justify-end gap-1">
                                <UiButton v-if="item.type === 'artifact' || item.type === 'version'" variant="subtle" size="sm" :title="item.size_bytes ? `Frees ${size(item.size_bytes)}` : undefined" @click="forever(item)">Delete forever</UiButton>
                                <UiButton variant="ghost" size="sm" @click="restore(item)">Restore</UiButton>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </UiTable>
            <EmptyState v-else :icon="TrashIcon" title="Trash is empty" description="Deleted projects, artifacts, reports, and skills appear here." />
        </div>
    </AppLayout>
</template>
