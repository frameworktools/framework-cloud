<script setup>
import { AdjustmentsHorizontalIcon, FingerPrintIcon, KeyIcon, UserCircleIcon } from '@heroicons/vue/24/outline';
import SectionLayout from '@/Layouts/SectionLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import GitHubMark from '@/Components/GitHubMark.vue';

defineProps({ heading: String, subheading: String });

const items = [
    { label: 'Profile', href: '/settings/profile', icon: UserCircleIcon },
    { label: 'Account', href: '/settings/account', icon: KeyIcon },
    { label: 'Framework defaults', href: '/settings/defaults', icon: AdjustmentsHorizontalIcon },
    { label: 'Passkeys', href: '/settings/passkeys', icon: FingerPrintIcon },
    { label: 'GitHub', href: '/settings/github', icon: GitHubMark },
];
</script>

<!--
    Each settings page is its own form. A page that saves puts its button in
    the actions slot, which sits at the foot of the section sidebar.
-->
<template>
    <SectionLayout heading="Settings" :items="items">
        <PageHeader :title="heading" :description="subheading" />

        <div class="mt-8 flex flex-col gap-8">
            <slot />
        </div>

        <template v-if="$slots.actions" #actions>
            <slot name="actions" />
        </template>
    </SectionLayout>
</template>
