<script setup>
// "Keep me signed in": checked, the sign-in lasts until you sign out; unchecked,
// it ends after a couple of hours without use
const model = defineModel({ type: Boolean, default: false });
</script>

<template>
    <label class="flex cursor-pointer items-start gap-2.5 text-sm text-slate-700 dark:text-slate-300">
        <input v-model="model" type="checkbox" name="remember" value="1" class="mt-0.5 size-4 shrink-0 rounded border-slate-300 text-sky-700 accent-sky-700 focus-visible:outline-2 focus-visible:outline-sky-500 dark:border-slate-600">
        <span>Keep me signed in<span class="block text-xs text-slate-500 dark:text-slate-400">Use this only on your own device.</span></span>
    </label>
</template>
