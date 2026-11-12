<script setup>
// A project's repository: the scaffold's status, or, without a repository,
// the Create on GitHub form. Overview's GitHub box and the GitHub & Pages
// section both use it.
import { computed, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import CodeChip from '@/Components/CodeChip.vue';
import UiBadge from '@/Components/UiBadge.vue';
import UiButton from '@/Components/UiButton.vue';
import UiInput from '@/Components/UiInput.vue';
import UiLink from '@/Components/UiLink.vue';
import UiSelect from '@/Components/UiSelect.vue';
import UiSwitch from '@/Components/UiSwitch.vue';
import { statusLabel, statusVariant } from '@/projects.js';

// setup: the Create on GitHub form's needs, null when the project has a repository.
// collapsed: show a button that opens the form instead of the form itself.
const props = defineProps({ project: Object, setup: Object, collapsed: Boolean });
const emit = defineEmits(['open']);

const github = computed(() => props.project.github);
const busy = computed(() => ['pending', 'running'].includes(github.value?.status));
const canCreate = computed(() => !github.value?.repo && !busy.value && !!props.setup);

const refresh = () => router.post(`/projects/${props.project.id}/scaffold/refresh`, {}, { preserveScroll: true });
const retry = () => router.post(`/projects/${props.project.id}/scaffold/retry`, {}, { preserveScroll: true });

const ownerOptions = computed(() => (props.setup?.owners ?? []).map((o) => ({ value: o.login, label: o.login })));
const form = useForm({ github: { owner: props.setup?.owners?.[0]?.login ?? '', name: props.project.slug, private: true } });
watch(() => props.setup?.owners, (owners) => { if (!form.github.owner && owners?.length) form.github.owner = owners[0].login; });
const sensitive = computed(() => !!props.setup?.sensitive);
watch(sensitive, (value) => { if (value) form.github.private = true; }, { immediate: true });
const error = computed(() => form.errors['github.enabled'] ?? form.errors.github);
const submit = () => form.post(`/projects/${props.project.id}/scaffold`, { preserveScroll: true });

const open = ref(!props.collapsed);
const show = () => { open.value = true; emit('open'); };
</script>

<template>
    <div class="flex min-w-0 flex-col gap-4 text-sm">
        <div v-if="github?.repo || github?.status" class="flex flex-col gap-3">
            <div class="flex flex-wrap items-center gap-2">
                <UiBadge v-if="github.status" :variant="statusVariant[github.status]">{{ statusLabel[github.status] }}</UiBadge>
                <UiLink v-if="github.url" :href="github.url" external class="font-mono text-xs">{{ github.repo }}</UiLink>
                <span v-if="github.private" class="text-xs text-slate-500 dark:text-slate-400">private</span>
                <UiLink v-if="github.run_url" :href="github.run_url" external class="text-xs">Run log</UiLink>
                <UiButton v-if="github.status === 'running' && github.repo" variant="ghost" size="sm" @click="refresh">Check status</UiButton>
                <UiButton v-if="github.status === 'failed' && github.url" variant="ghost" size="sm" @click="retry">Retry</UiButton>
            </div>
            <p v-if="github.error" class="text-red-600 dark:text-red-400">{{ github.error }}</p>
            <div v-if="github.status === 'done'" class="min-w-0"><CodeChip>git clone {{ github.url }}.git</CodeChip></div>
        </div>

        <template v-if="canCreate">
            <p v-if="!setup.enabled" class="text-slate-600 dark:text-slate-400">This server is not set up to create GitHub repositories.</p>
            <p v-else-if="setup.unavailable" class="text-slate-600 dark:text-slate-400">{{ setup.unavailable }}</p>
            <p v-else-if="!setup.login" class="text-slate-600 dark:text-slate-400"><UiLink href="/account/github">Connect GitHub</UiLink> first.</p>
            <p v-else-if="setup.owners === null" class="text-slate-600 dark:text-slate-400">GitHub could not be reached. Reload to try again.</p>
            <p v-else-if="!ownerOptions.length" class="text-slate-600 dark:text-slate-400">
                <UiLink :href="setup.install_url" external>Install the Framework app</UiLink>
                on your account or an organization, then reload this page.
            </p>
            <div v-else-if="!open"><UiButton variant="ghost" size="sm" @click="show">{{ github?.status === 'failed' ? 'Try again' : 'Create on GitHub' }}</UiButton></div>
            <form v-else class="flex flex-col gap-4" @submit.prevent="submit">
                <div class="flex flex-wrap items-end gap-2">
                    <div class="w-44"><UiSelect v-model="form.github.owner" label="Owner" :options="ownerOptions" :error="form.errors['github.owner']" /></div>
                    <span class="pb-2 text-slate-400">/</span>
                    <div class="min-w-48 flex-1"><UiInput v-model="form.github.name" label="Repository" :error="form.errors['github.name']" /></div>
                </div>
                <UiSwitch
                    v-model="form.github.private" label="Private repository" :disabled="sensitive"
                    :description="sensitive ? 'Required: this project handles restricted data.' : 'Only you and people you add can see it.'"
                />
                <p v-if="form.errors['github.private']" class="text-red-600 dark:text-red-400">{{ form.errors['github.private'] }}</p>
                <p v-if="error" class="text-red-600 dark:text-red-400">{{ error }}</p>
                <div><UiButton type="submit" variant="primary" size="sm" :disabled="form.processing">{{ form.processing ? 'Creating…' : 'Create on GitHub' }}</UiButton></div>
            </form>
        </template>
    </div>
</template>
