<script setup>
import { ask } from '@/confirm.js';
import { computed, ref } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import SectionLayout from '@/Layouts/SectionLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import UiPanel from '@/Components/UiPanel.vue';
import UiButton from '@/Components/UiButton.vue';
import UiInput from '@/Components/UiInput.vue';
import UiSelect from '@/Components/UiSelect.vue';
import UiCallout from '@/Components/UiCallout.vue';
import CredentialField from '@/Components/CredentialField.vue';

const props = defineProps({
    steps: Array,
    needs: Array,
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
const forms = { github: formFor('github'), storage: formFor('storage'), mail: formFor('mail'), agents: formFor('agents') };
// The select shows the current state whether it was saved here or is the default
if (!['true', 'false'].includes(forms.agents.agents__enabled)) forms.agents.agents__enabled = props.status.agents ? 'true' : 'false';
const field = (key) => props.fields[key];
const save = (group) => forms[group].put(`/admin/setup/${group}`, {
    preserveScroll: true,
    onSuccess: () => Object.keys(forms[group].data()).forEach((k) => { if (field(k.replace('__', '.')).secret) forms[group][k] = ''; }),
});
const clear = async (group, what) => {
    if (await ask(`Remove the saved ${what}? Values set in the environment stay.`)) {
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
const installUrl = computed(() => field('github.app_slug').value ? `https://github.com/apps/${field('github.app_slug').value}/installations/select_target` : null);

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

// Getting started for the admin's own account, while anything is left
const stepCopy = {
    profile: { title: 'Personal data', text: 'Your name and affiliation go on the notebooks and reports you create.', href: '/settings/personal-data' },
    machine: { title: 'Computer connection', text: 'Make a token, then run cloud_login() once in R or Python, fw_login in Stata, or fw login in a terminal.', href: '/tokens' },
    github: { title: 'GitHub connection', text: 'Create projects as GitHub repositories, ready to clone.', href: '/account/github' },
    project: { title: 'New project', text: 'Pick a blueprint and set it up on GitHub or your computer.', href: '/projects/create' },
};
const remaining = computed(() => props.steps.filter((s) => !s.done).length);
const needCopy = { mail: { label: 'email', anchor: 'mail' }, github: { label: 'GitHub', anchor: 'github' } };

const items = [
    ...(props.steps.some((s) => !s.done) ? [{ label: 'Account setup', anchor: 'start' }] : []),
    { label: 'GitHub App', anchor: 'github' },
    { label: 'Storage', anchor: 'storage' },
    { label: 'Email', anchor: 'mail' },
];

const pill = (ready) => (ready
    ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200'
    : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300');
</script>

<template>
    <Head title="Server setup" />

    <!-- Three independent forms, so each panel keeps its own Save button -->
    <SectionLayout heading="Server administration" :items="items">
        <PageHeader
            title="Server setup"
            description="GitHub, artifact storage, and email credentials. Environment variables override saved values."
        />

        <div class="mt-8 flex flex-col gap-8">
            <p v-if="needs.length" class="rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:border-amber-700/60 dark:bg-amber-950/40 dark:text-amber-200">
                This server still needs
                <template v-for="(need, i) in needs" :key="need"><template v-if="i"> and </template><a :href="`#${needCopy[need].anchor}`" class="font-medium underline">{{ needCopy[need].label }}</a></template>
                set up.
            </p>

            <UiPanel v-if="remaining" id="start" title="Account setup" :description="`${steps.length - remaining} of ${steps.length} done`" class="scroll-mt-6">
                <ol class="-mx-1 flex flex-col divide-y divide-slate-200 dark:divide-slate-800">
                    <li v-for="(step, index) in steps" :key="step.key">
                        <Link :href="stepCopy[step.key].href" class="flex items-start gap-3 rounded-md px-1 py-3 hover:bg-slate-50 dark:hover:bg-slate-800/50">
                            <span class="w-5 shrink-0 text-sm text-slate-500 dark:text-slate-400">{{ step.done ? '✓' : index + 1 }}</span>
                            <span class="flex min-w-0 flex-col">
                                <span class="text-sm font-medium" :class="step.done ? 'text-slate-500 dark:text-slate-400 line-through' : 'text-slate-900 dark:text-white'">{{ stepCopy[step.key].title }}</span>
                                <span v-if="!step.done" class="text-sm text-slate-600 dark:text-slate-400">{{ stepCopy[step.key].text }}</span>
                            </span>
                        </Link>
                    </li>
                </ol>
            </UiPanel>

            <UiCallout v-if="broken.length" variant="danger" heading="Unreadable credentials">
                <p>
                    APP_KEY has changed since they were saved: {{ broken.join(', ') }}. Enter them again, or put the old key in
                    <span class="font-mono">APP_PREVIOUS_KEYS</span>.
                </p>
            </UiCallout>

            <UiCallout v-if="mode === 'self'" heading="Application key backup">
                <p>
                    Values saved on this page are encrypted with this server's <span class="font-mono">APP_KEY</span>.
                    Keep a copy of it with your backups. If it changes or is lost, these values can't be read and must be entered again.
                </p>
            </UiCallout>

            <!-- GitHub -->
            <UiPanel id="github" title="GitHub" class="scroll-mt-6">
                <template #header>
                    <span class="rounded-full px-2 py-0.5 text-xs" :class="pill(status.github)">{{ status.github ? 'Ready' : 'Not set up' }}</span>
                </template>
                <p class="-mt-4 text-sm text-slate-600 dark:text-slate-400">
                    Lets people sign in with GitHub and create projects as GitHub repositories. Each server needs its own GitHub App.
                    Optional: everything else works without it.
                </p>

                <UiCallout v-if="flash === 'github-created'" variant="success" heading="GitHub App created">
                    <p>Its credentials are saved. Next, install it on the accounts or organizations where projects should go.</p>
                    <a v-if="installUrl" :href="installUrl" target="_blank" class="font-medium text-emerald-700 hover:underline dark:text-emerald-300">Install the app</a>
                </UiCallout>
                <UiCallout v-if="errors.github" variant="danger" heading="GitHub">{{ errors.github }}</UiCallout>

                <template v-if="!githubFromEnv && !field('github.client_id').set">
                    <form
                        method="post" :action="githubAction"
                        class="flex flex-col gap-3 rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900"
                    >
                        <input type="hidden" name="manifest" :value="manifestJson">
                        <div class="text-sm font-medium">Create the app on GitHub</div>
                        <p class="text-sm text-slate-600 dark:text-slate-400">
                            GitHub opens with everything filled in: name, permissions, sign-in callback, and webhook. Click
                            <span class="font-medium">Create GitHub App</span> there and you'll come back here with it saved.
                        </p>
                        <div class="max-w-xs">
                            <UiInput v-model="githubOrg" label="Organization (optional)" placeholder="Leave blank for your account" />
                        </div>
                        <div><UiButton type="submit">Create GitHub App</UiButton></div>
                    </form>
                    <button type="button" class="self-start text-sm text-slate-500 dark:text-slate-400 hover:underline" @click="showManualGitHub = !showManualGitHub">
                        {{ showManualGitHub ? 'Hide' : 'Or enter an app you already have' }}
                    </button>
                </template>

                <form
                    v-if="githubFromEnv || field('github.client_id').set || showManualGitHub"
                    id="github-form" class="flex flex-col gap-4" @submit.prevent="save('github')"
                >
                    <div class="grid gap-4 sm:grid-cols-2">
                        <CredentialField v-model="forms.github.github__app_slug" :field="field('github.app_slug')" placeholder="framework-cloud-example" :error="forms.github.errors.github__app_slug" />
                        <CredentialField v-model="forms.github.github__app_id" :field="field('github.app_id')" placeholder="123456" :error="forms.github.errors.github__app_id" />
                        <CredentialField v-model="forms.github.github__client_id" :field="field('github.client_id')" placeholder="Iv23li..." :error="forms.github.errors.github__client_id" />
                        <CredentialField v-model="forms.github.github__client_secret" :field="field('github.client_secret')" placeholder="Paste the GitHub client secret" :error="forms.github.errors.github__client_secret" />
                    </div>
                    <CredentialField v-model="forms.github.github__webhook_secret" :field="field('github.webhook_secret')" placeholder="Paste the webhook secret" :error="forms.github.errors.github__webhook_secret" />
                    <CredentialField v-model="forms.github.github__private_key" :field="field('github.private_key')" multiline placeholder="-----BEGIN RSA PRIVATE KEY-----" :error="forms.github.errors.github__private_key" />

                    <dl class="grid gap-x-3 gap-y-1 text-xs sm:grid-cols-[auto_1fr]">
                        <dt class="text-slate-500 dark:text-slate-400">Sign-in callback</dt><dd class="break-all font-mono">{{ github.callback_url }}</dd>
                        <dt class="text-slate-500 dark:text-slate-400">Webhook</dt><dd class="break-all font-mono">{{ github.webhook_url }}</dd>
                    </dl>
                </form>

                <template v-if="(githubFromEnv || field('github.client_id').set || showManualGitHub) && !allEnv('github')" #actions>
                    <div class="flex flex-wrap items-center justify-end gap-2">
                        <span v-if="flash === 'github-saved'" class="text-sm text-slate-500 dark:text-slate-400">Saved.</span>
                        <UiButton v-if="savedIn('github')" variant="ghost" @click="clear('github', 'GitHub App')">Remove saved app</UiButton>
                        <a v-if="installUrl" :href="installUrl" target="_blank"><UiButton variant="outline">Install the app</UiButton></a>
                        <UiButton type="submit" form="github-form" :disabled="forms.github.processing">Save</UiButton>
                    </div>
                </template>
            </UiPanel>

            <!-- Storage -->
            <UiPanel id="storage" title="Artifact storage" class="scroll-mt-6">
                <template #header>
                    <span class="rounded-full px-2 py-0.5 text-xs" :class="pill(usingBucket)">
                        {{ usingBucket ? `Bucket (${status.storage})` : "This server's disk" }}
                    </span>
                </template>
                <div class="-mt-4 flex flex-col gap-2 text-sm text-slate-600 dark:text-slate-400">
                    <p>
                        Published artifacts use this server’s disk by default. For shared storage, use an S3-compatible bucket:
                        Amazon S3, Cloudflare R2, MinIO, or Backblaze B2.
                    </p>
                    <p v-if="documentsDiskFromEnv">
                        <span class="font-mono">FW_DOCUMENTS_DISK</span> is set, so the environment picks the disk. A bucket saved here is used only if it names <span class="font-mono">bucket</span>.
                    </p>
                </div>

                <UiCallout v-if="flash === 'storage-tested'" variant="success" heading="Storage verified">A test file was written, read back, and deleted.</UiCallout>
                <UiCallout v-if="errors.storage" variant="danger" heading="Storage">{{ errors.storage }}</UiCallout>

                <form id="storage-form" class="flex flex-col gap-4" @submit.prevent="save('storage')">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <CredentialField v-model="forms.storage.storage__bucket" :field="field('storage.bucket')" placeholder="framework-documents" :error="forms.storage.errors.storage__bucket" />
                        <CredentialField v-model="forms.storage.storage__region" :field="field('storage.region')" placeholder="auto, or us-east-1" :error="forms.storage.errors.storage__region" />
                    </div>
                    <CredentialField
                        v-model="forms.storage.storage__endpoint" :field="field('storage.endpoint')"
                        placeholder="https://<account>.r2.cloudflarestorage.com (blank for Amazon S3)" :error="forms.storage.errors.storage__endpoint"
                    />
                    <div class="grid gap-4 sm:grid-cols-2">
                        <CredentialField v-model="forms.storage.storage__key" :field="field('storage.key')" placeholder="Access key ID" :error="forms.storage.errors.storage__key" />
                        <CredentialField v-model="forms.storage.storage__secret" :field="field('storage.secret')" placeholder="Paste the secret access key" :error="forms.storage.errors.storage__secret" />
                    </div>
                    <CredentialField
                        v-model="forms.storage.storage__public_url" :field="field('storage.public_url')"
                        placeholder="https://files.example.org (optional)" :error="forms.storage.errors.storage__public_url"
                    />
                    <p class="-mt-2 text-xs text-slate-500 dark:text-slate-400">
                        Optional: a public domain attached to the bucket. Artifacts then load from there, a separate site from this one, which keeps
                        scripts in published pages away from people's sign-ins. Without it, this server streams them.
                    </p>
                </form>

                <template v-if="!allEnv('storage')" #actions>
                    <div class="flex flex-wrap items-center justify-end gap-2">
                        <span v-if="flash === 'storage-saved'" class="text-sm text-slate-500 dark:text-slate-400">Saved.</span>
                        <UiButton v-if="savedIn('storage')" variant="ghost" @click="clear('storage', 'bucket')">Remove saved bucket</UiButton>
                        <UiButton variant="outline" :disabled="forms.storage.processing" @click="testStorage">Test connection</UiButton>
                        <UiButton type="submit" form="storage-form" :disabled="forms.storage.processing">Save</UiButton>
                    </div>
                </template>
            </UiPanel>

            <!-- Mail -->
            <UiPanel id="mail" title="Email" class="scroll-mt-6">
                <template #header>
                    <span class="rounded-full px-2 py-0.5 text-xs" :class="pill(mailReady)">{{ mailReady ? `Sending with ${status.mail}` : 'Not sending' }}</span>
                </template>
                <p class="-mt-4 text-sm text-slate-600 dark:text-slate-400">
                    Sign-in links go out by email, so people can't sign in until this works (unless they use GitHub or a passkey).
                </p>

                <UiCallout v-if="flash === 'mail-tested'" variant="success" heading="Test email sent">
                    Sent to {{ adminEmail }}. If it doesn't arrive in a few minutes, check spam and the service's sending log.
                </UiCallout>
                <UiCallout v-if="errors.mail" variant="danger" heading="Email">{{ errors.mail }}</UiCallout>

                <form id="mail-form" class="flex flex-col gap-4" @submit.prevent="save('mail')">
                    <div v-if="field('mail.mailer').source === 'env'"><CredentialField :field="field('mail.mailer')" /></div>
                    <UiSelect v-else v-model="forms.mail.mail__mailer" label="Service" :options="mailerOptions" :error="forms.mail.errors.mail__mailer" />

                    <div class="grid gap-4 sm:grid-cols-2">
                        <CredentialField v-model="forms.mail.mail__from_address" :field="field('mail.from_address')" placeholder="noreply@example.org" :error="forms.mail.errors.mail__from_address" />
                        <CredentialField v-model="forms.mail.mail__from_name" :field="field('mail.from_name')" placeholder="Framework Cloud" :error="forms.mail.errors.mail__from_name" />
                    </div>

                    <div v-if="mailer === 'smtp'" class="grid gap-4 sm:grid-cols-2">
                        <CredentialField v-model="forms.mail.mail__smtp_host" :field="field('mail.smtp_host')" placeholder="smtp.postmarkapp.com" :error="forms.mail.errors.mail__smtp_host" />
                        <CredentialField v-model="forms.mail.mail__smtp_port" :field="field('mail.smtp_port')" placeholder="587" :error="forms.mail.errors.mail__smtp_port" />
                        <CredentialField v-model="forms.mail.mail__smtp_username" :field="field('mail.smtp_username')" placeholder="Username from your mail provider" :error="forms.mail.errors.mail__smtp_username" />
                        <CredentialField v-model="forms.mail.mail__smtp_password" :field="field('mail.smtp_password')" placeholder="Password from your mail provider" :error="forms.mail.errors.mail__smtp_password" />
                    </div>
                    <div v-if="mailer === 'ses'" class="grid gap-4 sm:grid-cols-2">
                        <CredentialField v-model="forms.mail.mail__ses_key" :field="field('mail.ses_key')" placeholder="AWS access key ID" :error="forms.mail.errors.mail__ses_key" />
                        <CredentialField v-model="forms.mail.mail__ses_secret" :field="field('mail.ses_secret')" placeholder="Paste the AWS secret access key" :error="forms.mail.errors.mail__ses_secret" />
                        <CredentialField v-model="forms.mail.mail__ses_region" :field="field('mail.ses_region')" placeholder="us-east-1" :error="forms.mail.errors.mail__ses_region" />
                    </div>
                </form>

                <template #actions>
                    <div class="flex flex-wrap items-center justify-end gap-2">
                        <span v-if="flash === 'mail-saved'" class="text-sm text-slate-500 dark:text-slate-400">Saved. Send a test email to check it.</span>
                        <UiButton v-if="savedIn('mail')" variant="ghost" @click="clear('mail', 'email settings')">Remove saved settings</UiButton>
                        <UiButton variant="outline" :disabled="!mailReady" @click="testMail">Send a test email</UiButton>
                        <UiButton v-if="!allEnv('mail')" type="submit" form="mail-form" :disabled="forms.mail.processing">Save</UiButton>
                    </div>
                </template>
            </UiPanel>

            <UiPanel id="agents" title="Connected agents" class="scroll-mt-6">
                <template #header>
                    <span class="rounded-full px-2 py-0.5 text-xs" :class="pill(status.agents)">{{ status.agents ? 'On' : 'Off' }}</span>
                </template>
                <p class="-mt-4 text-sm text-slate-600 dark:text-slate-400">
                    Lets people connect Claude, ChatGPT, and other AI agents to their accounts at <code class="font-mono text-xs">/mcp</code>.
                    Turned off, agents can't connect, existing connections stop working, and the agent page is hidden until it is turned on again.
                </p>
                <form id="agents-form" @submit.prevent="save('agents')">
                    <CredentialField v-if="field('agents.enabled').source === 'env'" :field="field('agents.enabled')" />
                    <UiSelect v-else v-model="forms.agents.agents__enabled" label="Agent connections" :error="forms.agents.errors.agents__enabled"
                        :options="[{ value: 'true', label: 'On' }, { value: 'false', label: 'Off' }]" />
                </form>
                <template #actions>
                    <div class="flex flex-wrap items-center justify-end gap-2">
                        <span v-if="flash === 'agents-saved'" class="text-sm text-slate-500 dark:text-slate-400">Saved.</span>
                        <UiButton v-if="!allEnv('agents')" type="submit" form="agents-form" :disabled="forms.agents.processing">Save</UiButton>
                    </div>
                </template>
            </UiPanel>
        </div>
    </SectionLayout>
</template>
