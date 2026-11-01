<script setup>
import { computed, ref } from 'vue';
import { ChevronRightIcon } from '@heroicons/vue/20/solid';

/**
 * The default blueprint, picked from a list. Each row's details open on
 * their own: opening them never changes the choice. The details are read
 * from the blueprint's latest version, and anything it leaves to the
 * person's own defaults says so, with the value those defaults give now.
 */
const props = defineProps({
    blueprints: { type: Array, required: true },
    defaults: { type: Object, required: true }, // the Project defaults form, for "uses your default" values
});
const model = defineModel({ type: String });

// The chosen blueprint starts open; any number can stay open to compare
const open = ref(new Set([model.value]));
const toggleOpen = (key) => {
    const next = new Set(open.value);
    next.has(key) ? next.delete(key) : next.add(key);
    open.value = next;
};

const onOff = (value) => (value ? 'on' : 'off');
const languageNote = (language) => (language?.length === 1 ? (language[0] === 'r' ? 'R only' : 'Python only') : null);
const hookNames = { data_security: 'check for secrets', check_sensitive_dirs: 'check private folders' };
const defaultHooks = computed(() => Object.entries(props.defaults.hooks ?? {}).filter(([, on]) => on).map(([key]) => hookNames[key] ?? key));

// Folders grouped under their parent folder, one line each
const tree = (paths) => {
    const groups = new Map();
    for (const path of paths) {
        const cut = path.lastIndexOf('/');
        const parent = cut === -1 ? '' : path.slice(0, cut + 1);
        const leaf = path.slice(cut + 1);
        groups.set(parent, [...(groups.get(parent) ?? []), leaf.includes('.') ? leaf : `${leaf}/`]);
    }
    return [...groups].map(([parent, leaves]) => ({ parent, leaves }));
};

const label = 'text-xs font-medium uppercase tracking-wide text-slate-500';
const muted = 'text-xs text-slate-500 dark:text-slate-400';
const link = 'text-sky-600 hover:underline dark:text-sky-400';
</script>

<template>
    <fieldset>
        <legend class="sr-only">Default blueprint</legend>
        <div class="divide-y divide-slate-200 rounded-lg border border-slate-200 dark:divide-slate-800 dark:border-slate-800">
            <div v-for="b in blueprints" :key="b.key" :class="model === b.key ? 'bg-sky-50/60 dark:bg-sky-950/20' : ''">
                <div class="flex items-start gap-3 px-4 py-3">
                    <label class="flex min-w-0 flex-1 cursor-pointer items-start gap-3">
                        <input v-model="model" type="radio" name="project_type" :value="b.key" class="mt-0.5 size-4 shrink-0 accent-sky-600">
                        <span class="flex min-w-0 flex-col gap-0.5">
                            <span class="text-sm font-medium text-slate-900 dark:text-white">{{ b.name }}</span>
                            <span class="text-sm text-slate-600 dark:text-slate-400">{{ b.description }}</span>
                        </span>
                    </label>
                    <button
                        type="button" :aria-expanded="open.has(b.key)" :aria-controls="`blueprint-${b.key}`"
                        class="inline-flex shrink-0 items-center gap-1 rounded px-1.5 py-0.5 text-sm text-slate-500 hover:bg-slate-100 hover:text-slate-800 focus-visible:outline-2 focus-visible:outline-sky-500 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                        @click="toggleOpen(b.key)"
                    >
                        Details
                        <ChevronRightIcon class="size-4 transition-transform" :class="open.has(b.key) ? 'rotate-90' : ''" />
                    </button>
                </div>

                <dl v-if="open.has(b.key)" :id="`blueprint-${b.key}`" class="grid gap-x-6 gap-y-4 px-4 pb-4 pl-11 text-sm text-slate-700 sm:grid-cols-[9rem_1fr] dark:text-slate-300">
                    <dt :class="label">Project structure</dt>
                    <dd>
                        <div>{{ b.structure.name }}</div>
                        <div v-if="b.structure.paths.length" class="mt-1.5 flex flex-col gap-0.5 font-mono text-xs text-slate-600 dark:text-slate-400">
                            <div v-for="line in tree(b.structure.paths)" :key="line.parent">
                                <span v-if="line.parent" class="text-slate-900 dark:text-slate-200">{{ line.parent }}</span>
                                {{ line.leaves.join('  ') }}
                            </div>
                        </div>
                        <div v-else :class="muted">No folders</div>
                    </dd>

                    <dt :class="label">Tools</dt>
                    <dd>
                        <div v-if="b.tools.length">
                            <template v-for="(t, i) in b.tools" :key="t.name">{{ i ? ', ' : '' }}{{ t.name }}<span v-if="languageNote(t.language)" class="text-slate-500"> ({{ languageNote(t.language) }})</span></template>
                        </div>
                        <div v-else>None included</div>
                        <div :class="muted">Your tool defaults are applied on top: tools you've turned on are added, and tools you've turned off are left out.</div>
                    </dd>

                    <dt :class="label">Packages</dt>
                    <dd class="flex flex-col gap-1">
                        <div v-for="set in b.package_sets" :key="set.id">
                            <Link :href="`/package-sets/${set.id}/edit`" :class="link">{{ set.name }}</Link>
                            <span class="font-mono text-xs text-slate-500"> {{ set.packages.join(', ') }}</span>
                        </div>
                        <div v-if="!b.package_sets.length">No package sets included</div>
                        <div v-for="p in b.other_packages" :key="p.package + p.from" class="text-sm"><span class="font-mono text-xs">{{ p.package }}</span>, from {{ p.from }}</div>
                        <div :class="muted">Your default package sets are added.</div>
                    </dd>

                    <dt :class="label">Agents</dt>
                    <dd class="flex flex-col gap-1">
                        <div v-for="set in b.skill_sets" :key="set.id">
                            <Link :href="`/skill-sets/${set.id}/edit`" :class="link">{{ set.name }}</Link>
                            <span class="text-slate-500">: {{ set.skills.join(', ') }}</span>
                        </div>
                        <div v-if="b.skills.length">Skills: {{ b.skills.join(', ') }}</div>
                        <div v-if="!b.skill_sets.length && !b.skills.length">None included</div>
                        <div :class="muted">Your default skill sets are added. With agent setup off, no skills are installed.</div>
                    </dd>

                    <dt :class="label">Settings</dt>
                    <dd class="flex flex-col gap-1">
                        <div>
                            Sensitive data:
                            <template v-if="b.settings.sensitive !== null">{{ onOff(b.settings.sensitive) }}, set by its {{ b.settings.sensitive_from === 'structure' ? 'project structure' : 'blueprint' }}</template>
                            <span v-else class="text-slate-500">uses your default ({{ onOff(defaults.sensitive) }})</span>
                        </div>
                        <div>
                            Git:
                            <template v-if="b.settings.git !== null">{{ onOff(b.settings.git) }}, set by the blueprint</template>
                            <span v-else class="text-slate-500">uses your default ({{ onOff(defaults.use_git) }})</span>
                        </div>
                        <div>
                            Agent setup:
                            <template v-if="b.settings.ai !== null">{{ onOff(b.settings.ai) }}, set by the blueprint</template>
                            <span v-else class="text-slate-500">uses your default ({{ onOff(defaults.ai_enabled) }})</span>
                        </div>
                        <div>
                            Git hooks:
                            <template v-if="b.settings.hooks !== null">set by the blueprint</template>
                            <span v-else class="text-slate-500">uses your defaults ({{ defaultHooks.length ? defaultHooks.join(', ') : 'none on' }})</span>
                        </div>
                    </dd>

                    <dt :class="label">Version</dt>
                    <dd><Link :href="`/blueprints/${b.id}`" class="font-mono text-xs" :class="link">{{ b.handle }} v{{ b.version }}</Link></dd>
                </dl>
            </div>
        </div>
    </fieldset>
</template>
