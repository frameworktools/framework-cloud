<script setup>
import SectionLayout from '@/Layouts/SectionLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';

defineProps({ heading: String, subheading: String });
</script>

<script>
export const accountItems = [
    { label: 'Account details', href: '/account/account' },
    { label: 'Sign-in & security', href: '/account/security' },
    { label: 'Computers & API tokens', href: '/tokens' },
    { label: 'GitHub connection', href: '/account/github' },
];
</script>

<!--
    Each account page is its own form. A page that saves puts its button in
    the actions slot, which sits at the foot of the section sidebar.
-->
<template>
    <SectionLayout heading="Account" :items="accountItems">
        <PageHeader :title="heading" :description="subheading" />

        <div class="mt-8 flex flex-col gap-8">
            <slot />
        </div>

        <template v-if="$slots.actions" #actions>
            <slot name="actions" />
        </template>
    </SectionLayout>
</template>
