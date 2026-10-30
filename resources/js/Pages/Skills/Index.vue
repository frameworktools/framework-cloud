<script setup>
import { computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import UiButton from '@/Components/UiButton.vue';
import UiCallout from '@/Components/UiCallout.vue';
import { group, groups } from '@/library.js';
import GitHubImport from '@/Components/GitHubImport.vue';

const props = defineProps({ skills: Array });

const page = usePage();
const errors = computed(() => Object.values(page.props.errors ?? {}));

const sections = computed(() =>
    groups
        .map((g) => ({ ...g, rows: props.skills.filter((s) => group(s) === g.key) }))
        .filter((g) => g.rows.length || g.key === 'mine'),
);

const fork = (id) => router.post(`/skills/${id}/fork`);
</script>

<template>
    <Head title="Skills" />

    <AppLayout>
        <div class="flex flex-col gap-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">Skills</h1>
                    <p class="mt-1 max-w-2xl text-slate-600 dark:text-slate-400">
                        A skill is a SKILL.md file of instructions that an AI assistant loads when its
                        description matches the task. Add skills to facets to ship them with projects.
                    </p>
                </div>
                <Link href="/skills/create"><UiButton>New skill</UiButton></Link>
            </div>

            <UiCallout v-if="errors.length" variant="danger">{{ errors.join(' ') }}</UiCallout>

            <GitHubImport
                action="/skills/import"
                label="Import a skill from GitHub"
                placeholder="https://github.com/owner/repo/tree/main/skills/my-skill"
            />

            <section v-for="section in sections" :key="section.key" class="flex flex-col gap-3">
                <h2 class="text-sm font-medium uppercase tracking-wide text-slate-500">{{ section.label }}</h2>
                <p v-if="!section.rows.length" class="text-sm text-slate-500">You haven't made a skill yet.</p>

                <div class="flex flex-col divide-y divide-slate-200 rounded-lg border border-slate-200 dark:divide-slate-800 dark:border-slate-800">
                    <div v-for="skill in section.rows" :key="skill.id" class="flex items-start justify-between gap-4 p-4">
                        <div class="min-w-0">
                            <Link :href="`/skills/${skill.id}/edit`" class="font-mono text-sm font-medium hover:underline">{{ skill.key }}</Link>
                            <span class="font-mono text-xs text-slate-500"> · {{ skill.handle }} · v{{ skill.version }}</span>
                            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ skill.description }}</p>
                        </div>
                        <UiButton v-if="!skill.editable" size="sm" variant="outline" class="shrink-0 whitespace-nowrap" @click="fork(skill.id)">Copy and edit</UiButton>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
