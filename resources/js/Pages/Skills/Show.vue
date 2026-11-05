<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { ArrowLeftIcon, CheckIcon, ClipboardDocumentIcon } from '@heroicons/vue/20/solid';
import AppLayout from '@/Layouts/AppLayout.vue';
import UiButton from '@/Components/UiButton.vue';
import { languageNames, languageScope } from '@/library.js';

// A skill read in full, as a project in the chosen language gets it
const props = defineProps({
    skill: Object,
    language: String, // the variant shown
    variants: Array, // languages that can be switched between
    html: String, // the rendered instructions, without the frontmatter
    source: String, // SKILL.md as delivered
    from: String, // the catalog to go back to
});

const view = ref('read');
const back = computed(() => `/${props.from}?language=${props.language}`);
const backLabel = computed(() => (props.from === 'skill-sets' ? 'Skill sets' : 'Skills'));
const switchTo = (language) => router.get(`/skills/${props.skill.id}`, { language, from: props.from }, { preserveScroll: true, replace: true });

const copied = ref(false);
const copy = async () => {
    try {
        await navigator.clipboard.writeText(props.source);
        copied.value = true;
        setTimeout(() => (copied.value = false), 1500);
    } catch {
        // Clipboard unavailable: the Source view can still be selected and copied
        view.value = 'source';
    }
};
const fork = () => router.post(`/skills/${props.skill.id}/fork`);

const pill = (on) => on
    ? 'bg-white text-slate-900 shadow-sm ring-1 ring-slate-200 dark:bg-slate-800 dark:text-white dark:ring-slate-700'
    : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white';
</script>

<template>
    <Head :title="skill.name" />

    <AppLayout>
        <div class="flex flex-col gap-6">
            <Link :href="back" class="inline-flex w-fit items-center gap-1 text-sm text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white">
                <ArrowLeftIcon class="size-4" />{{ backLabel }}
            </Link>

            <header class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0 max-w-[70ch]">
                    <h1 class="text-3xl font-bold tracking-tight text-slate-900 dark:text-white">{{ skill.name }}</h1>
                    <p v-if="skill.summary" class="mt-2 text-base text-slate-700 dark:text-slate-300">{{ skill.summary }}</p>
                    <details class="group mt-2 text-sm">
                        <summary class="cursor-pointer list-none text-xs text-slate-500 hover:text-slate-800 dark:hover:text-slate-200 [&::-webkit-details-marker]:hidden">
                            <span class="group-open:hidden">Agent instructions ›</span><span class="hidden group-open:inline">Agent instructions ⌄</span>
                        </summary>
                        <p class="mt-1 text-slate-600 dark:text-slate-400">{{ skill.descriptions?.[language] ?? skill.description }}</p>
                    </details>
                    <p class="mt-2 text-xs text-slate-500">{{ languageScope(skill.language) }}</p>
                    <div v-if="skill.tags?.length" class="mt-3 flex flex-wrap gap-3">
                        <Link v-for="tag in skill.tags" :key="tag.id" :href="`/skills?tag=${tag.key}`" class="text-xs text-slate-500 underline underline-offset-4">{{ tag.name }}</Link>
                    </div>
                </div>
                <div class="flex shrink-0 gap-2">
                    <Link v-if="skill.editable" :href="`/skills/${skill.id}/edit`"><UiButton size="sm" variant="outline">Edit</UiButton></Link>
                    <UiButton v-else size="sm" variant="outline" @click="fork">Copy and edit</UiButton>
                </div>
            </header>

            <div class="flex flex-wrap items-center gap-x-4 gap-y-2 border-b border-slate-200 pb-3 dark:border-slate-800">
                <div role="radiogroup" aria-label="View" class="inline-flex gap-1 rounded-lg bg-slate-100 p-1 dark:bg-slate-900">
                    <button v-for="v in ['read', 'source']" :key="v" type="button" role="radio" :aria-checked="view === v" class="rounded-md px-3 py-1 text-sm font-medium transition" :class="pill(view === v)" @click="view = v">
                        {{ v === 'read' ? 'Read' : 'Source' }}
                    </button>
                </div>
                <!-- A skill with its own text per language shows the one a project in that language gets -->
                <div v-if="variants.length > 1" role="radiogroup" aria-label="Language" class="inline-flex gap-1 rounded-lg bg-slate-100 p-1 dark:bg-slate-900">
                    <button v-for="l in variants" :key="l" type="button" role="radio" :aria-checked="language === l" class="rounded-md px-3 py-1 text-sm font-medium transition" :class="pill(language === l)" @click="switchTo(l)">
                        {{ languageNames[l] }}
                    </button>
                </div>
                <span v-if="variants.length > 1" class="text-xs text-slate-500">{{ languageNames[language] }} project instructions</span>
                <button type="button" class="ml-auto inline-flex items-center gap-1 text-sm text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white" @click="copy">
                    <component :is="copied ? CheckIcon : ClipboardDocumentIcon" class="size-4" />{{ copied ? 'Copied' : 'Copy source' }}
                </button>
            </div>

            <p v-if="skill.parent" class="text-sm text-slate-500">Forked from <Link :href="`/skills/${skill.parent.id}`" class="underline">{{ skill.parent.name }}</Link>, version {{ skill.parent.version }}.</p>
            <article v-if="view === 'read'" class="skill-prose" v-html="html" />
            <pre v-else class="max-w-[90ch] overflow-x-auto whitespace-pre-wrap break-words rounded-lg bg-slate-50 p-4 font-mono text-[0.8125rem] leading-relaxed text-slate-800 ring-1 ring-slate-200 dark:bg-slate-900 dark:text-slate-200 dark:ring-slate-800">{{ source }}</pre>

            <footer class="flex flex-wrap gap-x-3 gap-y-1 border-t border-slate-200 pt-3 text-xs text-slate-500 dark:border-slate-800">
                <span class="font-mono">{{ skill.handle }} v{{ skill.version }}</span>
                <span v-if="!skill.is_system && skill.owner">by {{ skill.owner.name ?? skill.owner }}</span>
                <a v-if="skill.source_url" :href="skill.source_url" class="text-sky-600 hover:underline dark:text-sky-400" rel="noopener" target="_blank">Source on GitHub</a>
            </footer>
        </div>
    </AppLayout>
</template>
