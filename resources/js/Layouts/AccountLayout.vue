<script setup>
import { FingerPrintIcon, KeyIcon, UserCircleIcon, IdentificationIcon } from '@heroicons/vue/24/outline';
import SectionLayout from '@/Layouts/SectionLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import GitHubMark from '@/Components/GitHubMark.vue';

defineProps({ heading: String, subheading: String });
</script>

<script>
export const accountItems = [
    { label: 'Profile', href: '/account/profile', icon: UserCircleIcon },
    { label: 'Account details', href: '/account/account', icon: IdentificationIcon },
    { label: 'Passkeys', href: '/account/passkeys', icon: FingerPrintIcon },
    { label: 'API tokens', href: '/tokens', icon: KeyIcon },
    { label: 'GitHub', href: '/account/github', icon: GitHubMark },
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
