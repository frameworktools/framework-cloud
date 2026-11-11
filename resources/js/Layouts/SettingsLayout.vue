<script setup>
import SectionLayout from '@/Layouts/SectionLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';

defineProps({ heading: String, subheading: String, dirty: Boolean });

const items = [
    { label: 'Default blueprint', href: '/settings/blueprint' },
    { label: 'Personal data', href: '/settings/personal-data' },
];
</script>

<!--
    Each settings page is its own form. Its save button goes in the actions
    slot, at the foot of the section sidebar.
-->
<template>
    <SectionLayout heading="Settings" :items="items" narrow :dirty="dirty">
        <PageHeader :title="heading" :description="subheading" />

        <div class="mt-8 flex flex-col gap-8">
            <slot />
        </div>

        <template v-if="$slots.actions" #actions>
            <slot name="actions" />
        </template>
    </SectionLayout>
</template>
