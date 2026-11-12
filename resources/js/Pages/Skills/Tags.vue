<script setup>
import { reactive, watch } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import UiButton from '@/Components/UiButton.vue';
import UiInput from '@/Components/UiInput.vue';
import UiTable from '@/Components/UiTable.vue';
import UiLink from '@/Components/UiLink.vue';
import { inputClass } from '@/ui.js';
const props = defineProps({ tags: Array });
const names = reactive(Object.fromEntries(props.tags.map(tag => [tag.id, tag.name])));
watch(() => props.tags, tags => { for (const tag of tags) if (!(tag.id in names)) names[tag.id] = tag.name; });
const form = useForm({ name: '' });
const saving = reactive({});
const errors = reactive({});
const save = tag => {
    saving[tag.id] = true;
    router.put(`/admin/skill-tags/${tag.id}`, { name: names[tag.id] }, {
        preserveScroll: true,
        onSuccess: () => { errors[tag.id] = ''; },
        onError: value => { errors[tag.id] = value.name; },
        onFinish: () => { saving[tag.id] = false; },
    });
};
</script>
<template>
    <Head title="Skill tags" />
    <AppLayout>
        <div class="max-w-2xl">
            <UiLink href="/skills" class="text-sm">Skills</UiLink>
            <PageHeader title="Skill tags" description="Curated tags for all accounts. Renaming preserves skill assignments." class="mt-5" />
            <UiTable label="Curated skill tags" class="mt-6">
                <thead><tr><th>Tag</th><th class="actions"><span class="sr-only">Actions</span></th></tr></thead>
                <tbody><tr v-for="tag in tags" :key="tag.id">
                    <td><label :for="`tag-${tag.id}`" class="sr-only">{{ tag.name }}</label><input :id="`tag-${tag.id}`" v-model="names[tag.id]" placeholder="Tag name" :class="inputClass" maxlength="60" @keydown.enter.prevent="save(tag)" /><p v-if="errors[tag.id]" class="mt-1 whitespace-normal text-xs text-red-600">{{ errors[tag.id] }}</p></td>
                    <td class="actions"><UiButton variant="ghost" size="sm" :disabled="saving[tag.id] || names[tag.id] === tag.name" @click="save(tag)">Save</UiButton></td>
                </tr></tbody>
            </UiTable>
            <form class="mt-8 flex flex-col gap-4 border-t border-slate-800/10 pt-6 dark:border-white/15" @submit.prevent="form.post('/admin/skill-tags', { preserveScroll: true, onSuccess: () => form.reset() })">
                <h2 class="text-base font-medium text-slate-900 dark:text-white">New tag</h2>
                <UiInput v-model="form.name" label="Name" placeholder="Data validation" :error="form.errors.name ?? form.errors.key" maxlength="60" />
                <UiButton type="submit" class="self-start" :disabled="form.processing">Add tag</UiButton>
            </form>
        </div>
    </AppLayout>
</template>
