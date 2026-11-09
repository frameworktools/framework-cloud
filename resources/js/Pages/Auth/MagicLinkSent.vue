<script setup>
import { computed, nextTick, ref } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import UiButton from '@/Components/UiButton.vue';
import { inputClass } from '@/ui.js';

/*
 * After the address is entered at sign-in, as on academic.page. The same for
 * every address: it never says whether an account exists, or whether its
 * owner turned emailed links off in favor of a password. The link is the main way
 * in, so the code form stays folded away until someone asks for it, and opens
 * on its own when a code was just refused.
 */
const page = usePage();
const codeError = computed(() => page.props.errors?.code ?? null);
const open = ref(Boolean(codeError.value));
const codeInput = ref(null);

const form = useForm({ code: '', remember: false });
const submit = () => form
    .transform((data) => ({ ...data, remember: data.remember ? 1 : 0 }))
    .post('/auth/magic-code', { onError: () => { open.value = true; }, onFinish: () => form.reset('code') });

const reveal = () => {
    open.value = true;
    nextTick(() => codeInput.value?.focus());
};
</script>

<template>
    <Head title="Check your email" />

    <AuthLayout title="Check your email" description="If this address has an account, we sent a sign-in link and a six-digit code. The link works once and expires in 15 minutes.">
        <p v-if="!open" class="text-center text-sm">
            <button type="button" class="font-medium text-sky-700 hover:text-sky-800 dark:text-sky-400 dark:hover:text-sky-300" @click="reveal">
                Have the code? Enter it instead
            </button>
        </p>

        <div v-else class="rounded-xl border border-slate-200 p-6 sm:p-8 dark:border-slate-800">
            <form class="flex flex-col gap-5" @submit.prevent="submit">
                <div class="flex flex-col gap-1.5">
                    <label for="sign-in-code" class="text-sm font-medium text-slate-900 dark:text-white">Six-digit code</label>
                    <input
                        id="sign-in-code"
                        ref="codeInput"
                        v-model="form.code"
                        type="text"
                        name="code"
                        inputmode="numeric"
                        autocomplete="one-time-code"
                        placeholder="123456"
                        required
                        :autofocus="Boolean(codeError)"
                        :aria-invalid="codeError ? 'true' : undefined"
                        aria-describedby="sign-in-code-help"
                        :class="inputClass"
                    >
                    <p id="sign-in-code-help" class="text-sm text-slate-500 dark:text-slate-400">
                        Enter the code here if the link will not open, or if you would rather stay in this browser.
                    </p>
                    <p v-if="codeError" class="text-sm text-red-600 dark:text-red-400">{{ codeError }}</p>
                </div>

                <label class="flex cursor-pointer items-center gap-2.5 text-sm text-slate-700 dark:text-slate-300">
                    <input v-model="form.remember" type="checkbox" name="remember" class="size-4 shrink-0 rounded border-slate-300 accent-sky-700 dark:border-slate-600">
                    Remember me for 30 days
                </label>

                <UiButton type="submit" class="w-full" :disabled="form.processing">Sign in</UiButton>
            </form>
        </div>

        <UiButton v-if="page.props.passwords" href="/login?use=password" variant="outline" class="w-full">Use your password instead</UiButton>

        <p class="text-center text-sm text-slate-500 dark:text-slate-400">
            Didn’t receive an email?
            <Link href="/login" class="font-medium text-sky-700 hover:text-sky-800 dark:text-sky-400 dark:hover:text-sky-300">Try again</Link>
        </p>
    </AuthLayout>
</template>
