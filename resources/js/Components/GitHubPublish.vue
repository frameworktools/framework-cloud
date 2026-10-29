<script setup>
import { computed } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import UiButton from '@/Components/UiButton.vue';
import UiInput from '@/Components/UiInput.vue';

// No <form> element, so it can sit inside another form
const props = defineProps({ action: String, path: String });

const page = usePage();
const published = computed(() => (page.props.flash.status === 'published' ? page.props.flash.url : null));
const connected = computed(() => page.props.githubSignIn);

const form = useForm({ repo: '' });
const submit = () => form.post(props.action, { preserveScroll: true });
</script>

<template>
    <section v-if="connected" class="flex flex-col gap-2">
        <h2 class="text-sm font-medium">Publish to GitHub</h2>
        <p class="text-sm text-neutral-500">
            Writes <code class="font-mono">{{ path }}</code> to a repository the Framework app can reach, so others can import it.
        </p>
        <div class="flex flex-wrap items-end gap-2">
            <div class="w-72"><UiInput v-model="form.repo" label="Repository" placeholder="owner/name" :error="form.errors.repo" @keydown.enter.prevent="submit" /></div>
            <UiButton variant="outline" :disabled="form.processing || !form.repo" @click="submit">Publish</UiButton>
        </div>
        <p v-if="published" class="text-sm text-green-700 dark:text-green-400">
            Published: <a :href="published" class="underline">{{ published }}</a>
        </p>
    </section>
</template>
