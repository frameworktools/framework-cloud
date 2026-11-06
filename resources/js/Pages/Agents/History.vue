<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import UiBadge from '@/Components/UiBadge.vue';
import UiButton from '@/Components/UiButton.vue';
import UiTable from '@/Components/UiTable.vue';
import EmptyState from '@/Components/EmptyState.vue';
import AgentsTabs from './AgentsTabs.vue';
import { ClockIcon } from '@heroicons/vue/24/outline';

defineProps({ calls: Object, keepDays: Number, historyCount: Number });

const outcomes = {
    ok: ['green', 'Done'],
    error: ['red', 'Failed'],
    refused: ['amber', 'Refused'],
    dry_run: ['slate', 'Preview'],
};
</script>

<template>
    <Head title="Agent history" />

    <AppLayout>
        <div class="flex max-w-5xl flex-col gap-8">
            <div class="flex flex-col gap-6">
                <PageHeader title="Agent history">
                    <template #description>
                        Each action a connected agent took, newest first. Kept for {{ keepDays }} days. Request contents are not stored.
                    </template>
                </PageHeader>
                <AgentsTabs :history-count="historyCount" />
            </div>

            <template v-if="calls.data.length">
                <UiTable label="Agent history">
                    <thead>
                        <tr><th>When</th><th>Agent</th><th>Action</th><th>Project and item</th><th>Result</th></tr>
                    </thead>
                    <tbody>
                        <tr v-for="call in calls.data" :key="call.id">
                            <td><time :datetime="call.at_iso" :title="call.at_iso">{{ call.at }}</time></td>
                            <td class="strong">{{ call.agent ?? 'Removed agent' }}<span v-if="call.disconnected" class="ms-1.5 text-xs font-normal text-slate-500">(disconnected)</span></td>
                            <td>{{ call.tool }}</td>
                            <td class="font-mono text-xs">{{ call.target ?? '' }}</td>
                            <td>
                                <span class="inline-flex items-center gap-2" :title="call.message ?? undefined">
                                    <UiBadge :variant="outcomes[call.outcome]?.[0] ?? 'slate'">{{ outcomes[call.outcome]?.[1] ?? call.outcome }}</UiBadge>
                                    <span v-if="call.message" class="max-w-xs truncate text-xs">{{ call.message }}</span>
                                </span>
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
            <EmptyState v-else :icon="ClockIcon" title="No agent activity" description="Actions by connected agents appear here." />
        </div>
    </AppLayout>
</template>
