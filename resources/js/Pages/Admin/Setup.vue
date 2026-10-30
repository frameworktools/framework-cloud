<script setup>
import { computed, ref } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import UiButton from '@/Components/UiButton.vue';
import UiInput from '@/Components/UiInput.vue';
import UiSelect from '@/Components/UiSelect.vue';
import UiCallout from '@/Components/UiCallout.vue';
import CredentialField from '@/Components/CredentialField.vue';

const props = defineProps({
    mode: String,
    fields: Object,
    status: Object,
    documentsDiskFromEnv: Boolean,
    broken: Array,
    github: Object,
    adminEmail: String,
});

const page = usePage();
const flash = computed(() => page.props.flash.status);
const errors = computed(() => page.props.errors);

// One form per group; field names use "__" for the dot in the key
const formFor = (group) => useForm(Object.fromEntries(
    Object.entries(props.fields)
        .filter(([key, f]) => key.startsWith(`${group}.`) && f.source !== 'env')
        .map(([key, f]) => [key.replace('.', '__'), f.secret ? '' : f.value]),
));
const forms = { github: formFor('github'), storage: formFor('storage'), mail: formFor('mail') };
const field = (key) => props.fields[key];
const save = (group) => forms[group].put(`/admin/setup/${group}`, {
    preserveScroll: true,
    onSuccess: () => Object.keys(forms[group].data()).forEach((k) => { if (field(k.replace('__', '.')).secret) forms[group][k] = ''; }),
});
const clear = (group, what) => {
    if (confirm(`Remove the saved ${what}? Values set in the environment stay.`)) {
        router.delete(`/admin/setup/${group}`, { preserveScroll: true });
    }
};
const savedIn = (group) => Object.entries(props.fields).some(([k, f]) => k.startsWith(`${group}.`) && f.source === 'database');
const allEnv = (group) => Object.entries(props.fields).filter(([k]) => k.startsWith(`${group}.`)).every(([, f]) => f.source === 'env');

// GitHub: create the app on GitHub from a manifest, or type in an existing one
const githubOrg = ref('');
const showManualGitHub = ref(false);
const githubFromEnv = computed(() => field('github.client_id').source === 'env');
const githubAction = computed(() => {
    const org = githubOrg.value.trim();
    const base = org ? `https://github.com/organizations/${encodeURIComponent(org)}/settings/apps/new` : 'https://github.com/settings/apps/new';
    return `${base}?state=${encodeURIComponent(props.github.manifest.state)}`;
});
const manifestJson = computed(() => JSON.stringify(props.github.manifest.json));
const installUrl = computed(() => field('github.app_slug').value ? `https://github.com/apps/${field('github.app_slug').value}/installations/new` : null);

// Storage
const testStorage = () => forms.storage.post('/admin/setup/storage/test', { preserveScroll: true });
const usingBucket = computed(() => props.status.storage !== 'local');

// Mail
const mailerOptions = [
    { value: 'smtp', label: 'SMTP (Postmark, Mailgun, Resend, your provider)' },
    { value: 'ses', label: 'Amazon SES' },
    { value: 'log', label: 'Log file only (no email is sent)' },
];
const mailer = computed(() => (field('mail.mailer').source === 'env' ? field('mail.mailer').value : forms.mail.mail__mailer) || 'log');
const mailReady = computed(() => !['log', 'array'].includes(props.status.mail));
const testMail = () => router.post('/admin/setup/mail/test', {}, { preserveScroll: true });

const pill = (ready) => (ready
    ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200'
    : 'bg-neutral-100 text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300');
</script>

<template>
    <Head title="Server setup" />

    <AppLayout>
        <div class="flex max-w-3xl flex-col gap-8">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Server setup</h1>
                <p class="mt-1 text-neutral-600 dark:text-neutral-400">
                    This server's own credentials: the GitHub App, where published documents are stored, and how email is sent.
                    Fill them in here, or with environment variables. A variable that is set always wins over a value saved here.
                </p>
            </div>

            <UiCallout v-if="broken.length" variant="danger" heading="Some saved values can't be read">
                <p>
                    APP_KEY has changed since they were saved: {{ broken.join(', ') }}. Enter them again, or put the old key in
                    <span class="font-mono">APP_PREVIOUS_KEYS</span>.
                </p>
            </UiCallout>

            <UiCallout v-if="mode === 'self'" heading="Back up your APP_KEY">
                <p>
                    Values saved on this page are encrypted with this server's <span class="font-mono">APP_KEY</span>.
                    Keep a copy of it with your backups. If it changes or is lost, these values can't be read and must be entered again.
                </p>
            </UiCallout>

            <!-- 1. GitHub -->
            <section class="flex flex-col gap-4 rounded-lg border border-neutral-200 p-5 dark:border-neutral-800">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="flex items-center gap-2.5 text-lg font-semibold">
                        <span class="flex size-6 items-center justify-center rounded-full bg-orange-500 text-xs text-white">1</span>
                        GitHub
                    </h2>
                    <span class="rounded-full px-2 py-0.5 text-xs" :class="pill(status.github)">{{ status.github ? 'Ready' : 'Not set up' }}</span>
                </div>
                <p class="text-sm text-neutral-600 dark:text-neutral-400">
                    Lets people sign in with GitHub and create projects as GitHub repositories. Each server needs its own GitHub App.
                    Optional: everything else works without it.
                </p>

                <UiCallout v-if="flash === 'github-created'" variant="success" heading="GitHub App created">
                    <p>Its credentials are saved. Next, install it on the accounts or organizations where projects should go.</p>
                    <a v-if="installUrl" :href="installUrl" target="_blank" class="font-medium text-emerald-700 hover:underline dark:text-emerald-300">Install the app</a>
                </UiCallout>
                <UiCallout v-if="errors.github" variant="danger" heading="GitHub">{{ errors.github }}</UiCallout>

                <template v-if="!githubFromEnv && !field('github.client_id').set">
                    <form method="post" :action="githubAction" class="flex flex-col gap-3 rounded-md bg-neutral-50 p-4 dark:bg-neutral-900/60">
                        <input type="hidden" name="manifest" :value="manifestJson">
                        <div class="text-sm font-medium">Create the app on GitHub</div>
                        <p class="text-sm text-neutral-600 dark:text-neutral-400">
                            GitHub opens with everything filled in: name, permissions, sign-in callback, and webhook. Click
                            <span class="font-medium">Create GitHub App</span> there and you'll come back here with it saved.
                        </p>
                        <div class="max-w-xs">
                            <UiInput v-model="githubOrg" label="Organization (optional)" placeholder="Leave blank for your account" />
                        </div>
                        <div><UiButton type="submit">Create GitHub App</UiButton></div>
                    </form>
                    <button type="button" class="self-start text-sm text-neutral-500 hover:underline" @click="showManualGitHub = !showManualGitHub">
                        {{ showManualGitHub ? 'Hide' : 'Or enter an app you already have' }}
                    </button>
                </template>

                <form
                    v-if="githubFromEnv || field('github.client_id').set || showManualGitHub"
                    class="flex flex-col gap-4" @submit.prevent="save('github')"
                >
                    <div class="grid gap-4 sm:grid-cols-2">
                        <CredentialField v-model="forms.github.github__app_slug" :field="field('github.app_slug')" placeholder="framework-cloud-example" :error="forms.github.errors.github__app_slug" />
                        <CredentialField v-model="forms.github.github__app_id" :field="field('github.app_id')" :error="forms.github.errors.github__app_id" />
                        <CredentialField v-model="forms.github.github__client_id" :field="field('github.client_id')" placeholder="Iv23li..." :error="forms.github.errors.github__client_id" />
                        <CredentialField v-model="forms.github.github__client_secret" :field="field('github.client_secret')" :error="forms.github.errors.github__client_secret" />
                    </div>
                    <CredentialField v-model="forms.github.github__webhook_secret" :field="field('github.webhook_secret')" :error="forms.github.errors.github__webhook_secret" />
                    <CredentialField v-model="forms.github.github__private_key" :field="field('github.private_key')" multiline placeholder="-----BEGIN RSA PRIVATE KEY-----" :error="forms.github.errors.github__private_key" />

                    <dl class="grid gap-x-3 gap-y-1 text-xs sm:grid-cols-[auto_1fr]">
                        <dt class="text-neutral-500">Sign-in callback</dt><dd class="break-all font-mono">{{ github.callback_url }}</dd>
                        <dt class="text-neutral-500">Webhook</dt><dd class="break-all font-mono">{{ github.webhook_url }}</dd>
                    </dl>

                    <div v-if="!allEnv('github')" class="flex flex-wrap items-center gap-3">
                        <UiButton type="submit" :disabled="forms.github.processing">Save</UiButton>
                        <a v-if="installUrl" :href="installUrl" target="_blank"><UiButton variant="outline">Install the app</UiButton></a>
                        <UiButton v-if="savedIn('github')" variant="ghost" @click="clear('github', 'GitHub App')">Remove saved app</UiButton>
                        <span v-if="flash === 'github-saved'" class="text-sm text-neutral-500">Saved.</span>
                    </div>
                </form>
            </section>

            <!-- 2. Storage -->
            <section class="flex flex-col gap-4 rounded-lg border border-neutral-200 p-5 dark:border-neutral-800">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="flex items-center gap-2.5 text-lg font-semibold">
                        <span class="flex size-6 items-center justify-center rounded-full bg-orange-500 text-xs text-white">2</span>
                        Published documents
                    </h2>
                    <span class="rounded-full px-2 py-0.5 text-xs" :class="pill(usingBucket)">
                        {{ usingBucket ? `Bucket (${status.storage})` : "This server's disk" }}
                    </span>
                </div>
                <p class="text-sm text-neutral-600 dark:text-neutral-400">
                    Where notebooks published with <span class="font-mono">framework::publish_notebook()</span> are stored. Without a bucket they go on this
                    server's disk, which is fine for a small group. Any S3-compatible bucket works: Amazon S3, Cloudflare R2, MinIO, or Backblaze B2.
                </p>
                <p v-if="documentsDiskFromEnv" class="text-sm text-neutral-600 dark:text-neutral-400">
                    <span class="font-mono">FW_DOCUMENTS_DISK</span> is set, so the environment picks the disk. A bucket saved here is used only if it names <span class="font-mono">bucket</span>.
                </p>

                <UiCallout v-if="flash === 'storage-tested'" variant="success" heading="The bucket works">A test file was written, read back, and deleted.</UiCallout>
                <UiCallout v-if="errors.storage" variant="danger" heading="Storage">{{ errors.storage }}</UiCallout>

                <form class="flex flex-col gap-4" @submit.prevent="save('storage')">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <CredentialField v-model="forms.storage.storage__bucket" :field="field('storage.bucket')" placeholder="framework-documents" :error="forms.storage.errors.storage__bucket" />
                        <CredentialField v-model="forms.storage.storage__region" :field="field('storage.region')" placeholder="auto, or us-east-1" :error="forms.storage.errors.storage__region" />
                    </div>
                    <CredentialField
                        v-model="forms.storage.storage__endpoint" :field="field('storage.endpoint')"
                        placeholder="https://<account>.r2.cloudflarestorage.com (blank for Amazon S3)" :error="forms.storage.errors.storage__endpoint"
                    />
                    <div class="grid gap-4 sm:grid-cols-2">
                        <CredentialField v-model="forms.storage.storage__key" :field="field('storage.key')" :error="forms.storage.errors.storage__key" />
                        <CredentialField v-model="forms.storage.storage__secret" :field="field('storage.secret')" :error="forms.storage.errors.storage__secret" />
                    </div>
                    <CredentialField
                        v-model="forms.storage.storage__public_url" :field="field('storage.public_url')"
                        placeholder="https://files.example.org (optional)" :error="forms.storage.errors.storage__public_url"
                    />
                    <p class="-mt-2 text-xs text-neutral-500">
                        Optional: a public domain attached to the bucket. Documents then load from there, a separate site from this one, which keeps
                        scripts in published pages away from people's sign-ins. Without it, this server streams them.
                    </p>

                    <div v-if="!allEnv('storage')" class="flex flex-wrap items-center gap-3">
                        <UiButton type="submit" :disabled="forms.storage.processing">Save</UiButton>
                        <UiButton variant="outline" :disabled="forms.storage.processing" @click="testStorage">Test connection</UiButton>
                        <UiButton v-if="savedIn('storage')" variant="ghost" @click="clear('storage', 'bucket')">Remove saved bucket</UiButton>
                        <span v-if="flash === 'storage-saved'" class="text-sm text-neutral-500">Saved.</span>
                    </div>
                </form>
            </section>

            <!-- 3. Mail -->
            <section class="flex flex-col gap-4 rounded-lg border border-neutral-200 p-5 dark:border-neutral-800">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="flex items-center gap-2.5 text-lg font-semibold">
                        <span class="flex size-6 items-center justify-center rounded-full bg-orange-500 text-xs text-white">3</span>
                        Email
                    </h2>
                    <span class="rounded-full px-2 py-0.5 text-xs" :class="pill(mailReady)">{{ mailReady ? `Sending with ${status.mail}` : 'Not sending' }}</span>
                </div>
                <p class="text-sm text-neutral-600 dark:text-neutral-400">
                    Sign-in links go out by email, so people can't sign in until this works (unless they use GitHub or a passkey).
                </p>

                <UiCallout v-if="flash === 'mail-tested'" variant="success" heading="Test email sent">
                    Sent to {{ adminEmail }}. If it doesn't arrive in a few minutes, check spam and the service's sending log.
                </UiCallout>
                <UiCallout v-if="errors.mail" variant="danger" heading="Email">{{ errors.mail }}</UiCallout>

                <form class="flex flex-col gap-4" @submit.prevent="save('mail')">
                    <div v-if="field('mail.mailer').source === 'env'"><CredentialField :field="field('mail.mailer')" /></div>
                    <UiSelect v-else v-model="forms.mail.mail__mailer" label="Service" :options="mailerOptions" :error="forms.mail.errors.mail__mailer" />

                    <div class="grid gap-4 sm:grid-cols-2">
                        <CredentialField v-model="forms.mail.mail__from_address" :field="field('mail.from_address')" placeholder="noreply@example.org" :error="forms.mail.errors.mail__from_address" />
                        <CredentialField v-model="forms.mail.mail__from_name" :field="field('mail.from_name')" placeholder="Framework Cloud" :error="forms.mail.errors.mail__from_name" />
                    </div>

                    <div v-if="mailer === 'smtp'" class="grid gap-4 sm:grid-cols-2">
                        <CredentialField v-model="forms.mail.mail__smtp_host" :field="field('mail.smtp_host')" placeholder="smtp.postmarkapp.com" :error="forms.mail.errors.mail__smtp_host" />
                        <CredentialField v-model="forms.mail.mail__smtp_port" :field="field('mail.smtp_port')" placeholder="587" :error="forms.mail.errors.mail__smtp_port" />
                        <CredentialField v-model="forms.mail.mail__smtp_username" :field="field('mail.smtp_username')" :error="forms.mail.errors.mail__smtp_username" />
                        <CredentialField v-model="forms.mail.mail__smtp_password" :field="field('mail.smtp_password')" :error="forms.mail.errors.mail__smtp_password" />
                    </div>
                    <div v-if="mailer === 'ses'" class="grid gap-4 sm:grid-cols-2">
                        <CredentialField v-model="forms.mail.mail__ses_key" :field="field('mail.ses_key')" :error="forms.mail.errors.mail__ses_key" />
                        <CredentialField v-model="forms.mail.mail__ses_secret" :field="field('mail.ses_secret')" :error="forms.mail.errors.mail__ses_secret" />
                        <CredentialField v-model="forms.mail.mail__ses_region" :field="field('mail.ses_region')" placeholder="us-east-1" :error="forms.mail.errors.mail__ses_region" />
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <UiButton v-if="!allEnv('mail')" type="submit" :disabled="forms.mail.processing">Save</UiButton>
                        <UiButton variant="outline" :disabled="!mailReady" @click="testMail">Send a test email</UiButton>
                        <UiButton v-if="savedIn('mail')" variant="ghost" @click="clear('mail', 'email settings')">Remove saved settings</UiButton>
                        <span v-if="flash === 'mail-saved'" class="text-sm text-neutral-500">Saved. Send a test email to check it.</span>
                    </div>
                </form>
            </section>
        </div>
    </AppLayout>
</template>
