<script setup>
// "Remember me for 30 days": checked, the sign-in lasts 30 days (auth.guards.web.remember);
// unchecked, it ends after a couple of hours without use
const model = defineModel({ type: Boolean, default: false });
</script>

<template>
    <label class="flex cursor-pointer items-center gap-2.5 text-sm text-slate-700 dark:text-slate-300">
        <input v-model="model" type="checkbox" name="remember" value="1" class="size-4 shrink-0 rounded border-slate-300 text-sky-700 accent-sky-700 focus-visible:outline-2 focus-visible:outline-sky-500 dark:border-slate-600">
        <span>Remember me for 30 days</span>
    </label>
</template>
