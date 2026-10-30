<script setup>
// One of the server's credentials on the setup page. Set in the environment,
// it is read-only here; a saved secret is never sent back, only "saved".
import UiInput from '@/Components/UiInput.vue';

defineProps({
    field: { type: Object, required: true }, // { label, env, source, secret, value, set }
    error: String,
    placeholder: String,
    multiline: Boolean,
});

const model = defineModel({ type: [String, Number], default: '' });
</script>

<template>
    <div v-if="field.source === 'env'" class="flex flex-col gap-1.5">
        <span class="text-sm font-medium text-neutral-800 dark:text-neutral-200">{{ field.label }}</span>
        <div class="flex flex-wrap items-center gap-2 rounded-md border border-dashed border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700">
            <span v-if="field.secret" class="text-neutral-500">••••••••</span>
            <span v-else class="min-w-0 break-all font-mono text-xs">{{ field.value }}</span>
            <span class="ml-auto rounded bg-neutral-100 px-1.5 py-0.5 font-mono text-xs text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300">{{ field.env }}</span>
        </div>
    </div>

    <div v-else-if="multiline" class="flex flex-col gap-1.5">
        <label class="text-sm font-medium text-neutral-800 dark:text-neutral-200">{{ field.label }}</label>
        <textarea
            v-model="model" rows="4" spellcheck="false"
            :placeholder="field.set ? 'Saved. Leave blank to keep it.' : placeholder"
            class="w-full rounded-md border border-neutral-300 bg-white px-3 py-2 font-mono text-xs text-neutral-900 placeholder-neutral-400 focus:border-orange-500 focus:outline-none focus:ring-1 focus:ring-orange-500 dark:border-neutral-600 dark:bg-neutral-900 dark:text-neutral-100"
        />
        <p class="text-xs text-neutral-500">Or set <span class="font-mono">{{ field.env }}</span>.</p>
        <p v-if="error" class="text-xs text-red-600 dark:text-red-400">{{ error }}</p>
    </div>

    <UiInput
        v-else
        v-model="model"
        :label="field.label"
        :type="field.secret ? 'password' : 'text'"
        :autocomplete="field.secret ? 'new-password' : 'off'"
        :placeholder="field.secret && field.set ? 'Saved. Leave blank to keep it.' : placeholder"
        :description="field.source === 'broken' ? `Saved, but APP_KEY can't read it. Enter it again.` : `Or set ${field.env}.`"
        :error="error"
    />
</template>
