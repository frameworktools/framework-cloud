<script setup>
import PageHeader from '@/Components/PageHeader.vue';
import UiBadge from '@/Components/UiBadge.vue';
import UiButton from '@/Components/UiButton.vue';
import UiTable from '@/Components/UiTable.vue';
import AgentsLayout from './AgentsLayout.vue';

defineProps({ calls: Object, keepDays: Number });

const outcomes = {
    ok: ['green', 'Done'],
    error: ['red', 'Failed'],
    refused: ['amber', 'Refused'],
    dry_run: ['slate', 'Preview'],
};
</script>

<template>
    <Head title="Agent history" />

    <AgentsLayout>
        <div class="flex flex-col gap-8">
            <div class="flex flex-col gap-6">
                <PageHeader title="Agent history">
                    <template #description>
                        Each action a connected agent took, newest first. Kept for {{ keepDays }} days. Request contents are not stored.
                    </template>
                </PageHeader>
            </div>

            <template v-if="calls.data.length">
                <UiTable label="Agent history">
                    <thead>
                        <tr><th>When</th><th>Agent</th><th>Action</th><th>Project and item</th><th>Result</th></tr>
                    </thead>
                    <tbody>
                        <tr v-for="call in calls.data" :key="call.id">
                            <td><time :datetime="call.at_iso" :title="call.at_iso">{{ call.at }}</time></td>
                            <td class="strong">{{ call.agent ?? 'Removed agent' }}<span v-if="call.ended" class="ms-1.5 text-xs font-normal text-slate-500">({{ call.ended }})</span></td>
                            <td>{{ call.tool }}</td>
                            <td class="font-mono text-xs">{{ call.target ?? '' }}</td>
                            <td class="whitespace-normal">
                                <UiBadge :variant="outcomes[call.outcome]?.[0] ?? 'slate'">{{ outcomes[call.outcome]?.[1] ?? call.outcome }}</UiBadge>
                                <p v-if="call.message" class="mt-1 min-w-48 max-w-xs text-xs">{{ call.message }}</p>
                            </td>
                        </tr>
                    </tbody>
                </UiTable>
                <div v-if="calls.prev_page_url || calls.next_page_url" class="flex items-center justify-between text-sm text-slate-500">
                    <span>{{ calls.from }}–{{ calls.to }} of {{ calls.total }}</span>
                    <div class="flex gap-2">
                        <UiButton v-if="calls.prev_page_url" variant="ghost" size="sm" :href="calls.prev_page_url">Newer</UiButton>
                        <UiButton v-if="calls.next_page_url" variant="ghost" size="sm" :href="calls.next_page_url">Older</UiButton>
                    </div>
                </div>
            </template>
            <p v-else class="text-sm text-slate-500 dark:text-slate-400">No activity yet. When an agent takes an action, it will appear here.</p>
        </div>
    </AgentsLayout>
</template>
