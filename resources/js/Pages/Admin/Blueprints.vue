<script setup>
import { ref, computed, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import UiButton from '@/Components/UiButton.vue';
import UiInput from '@/Components/UiInput.vue';

const props = defineProps({ blueprints: Array });

const page = usePage();
const saved = computed(() => page.props.flash.status === 'blueprint-updated');

const selectedId = ref(null);
const selected = computed(() => props.blueprints.find((b) => b.id === selectedId.value));

const form = useForm({ name: '', description: '', structure_json: '', agents_md: '', skills: {} });
const structureError = ref('');
const newSkillName = ref('');

watch(selected, (blueprint) => {
    if (!blueprint) return;
    form.name = blueprint.name;
    form.description = blueprint.description ?? '';
    form.structure_json = JSON.stringify(blueprint.structure, null, 2);
    form.agents_md = blueprint.agents_md ?? '';
    form.skills = { ...(blueprint.skills ?? {}) };
    form.clearErrors();
    structureError.value = '';
});

function addSkill() {
    const name = newSkillName.value.trim();
    if (!name || form.skills[name] !== undefined) return;
    form.skills = { ...form.skills, [name]: `---\nname: ${name}\ndescription: \n---\n\n` };
    newSkillName.value = '';
}

function removeSkill(name) {
    const skills = { ...form.skills };
    delete skills[name];
    form.skills = skills;
}

function save() {
    structureError.value = '';
    let structure;
    try {
        structure = JSON.parse(form.structure_json);
    } catch {
        structureError.value = 'Structure must be valid JSON.';
        return;
    }
    form.transform((data) => ({
        name: data.name,
        description: data.description,
        structure,
        agents_md: data.agents_md,
        skills: data.skills,
    })).patch(`/admin/blueprints/${selectedId.value}`);
}
</script>

<template>
    <Head title="Blueprints" />

    <AppLayout>
        <div class="flex flex-col gap-6">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Blueprints</h1>
                <p class="mt-1 text-neutral-600 dark:text-neutral-400">
                    Master copies of each project type: directory structure, the AGENTS.md
                    template, and Framework skills. Every user inherits these; user settings
                    apply overrides on top — the masters stay canonical.
                </p>
            </div>

            <div class="flex items-start gap-8 max-lg:flex-col">
                <nav class="flex w-full flex-col gap-0.5 lg:w-52">
                    <button
                        v-for="blueprint in blueprints" :key="blueprint.id"
                        class="rounded-md px-2 py-1.5 text-left text-sm"
                        :class="selectedId === blueprint.id
                            ? 'bg-orange-100 font-medium text-orange-700 dark:bg-orange-950/50 dark:text-orange-300'
                            : 'text-neutral-700 hover:bg-neutral-100 dark:text-neutral-300 dark:hover:bg-neutral-800'"
                        @click="selectedId = blueprint.id"
                    >
                        {{ blueprint.name }}
                    </button>
                </nav>

                <div class="min-w-0 flex-1">
                    <form v-if="selected" class="flex flex-col gap-5" @submit.prevent="save">
                        <UiInput v-model="form.name" label="Name" :error="form.errors.name" />
                        <UiInput v-model="form.description" label="Description" :error="form.errors.description" />

                        <div class="flex flex-col gap-1.5">
                            <label class="text-sm font-medium">Structure (JSON: directories, render_dirs, quarto)</label>
                            <textarea
                                v-model="form.structure_json" rows="12"
                                class="w-full rounded-md border border-neutral-300 bg-white px-3 py-2 font-mono text-xs focus:border-orange-500 focus:outline-none focus:ring-1 focus:ring-orange-500 dark:border-neutral-600 dark:bg-neutral-900"
                            />
                            <p v-if="structureError || form.errors.structure" class="text-xs text-red-600">{{ structureError || form.errors.structure }}</p>
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <label class="text-sm font-medium">AGENTS.md master</label>
                            <textarea
                                v-model="form.agents_md" rows="14"
                                class="w-full rounded-md border border-neutral-300 bg-white px-3 py-2 font-mono text-xs focus:border-orange-500 focus:outline-none focus:ring-1 focus:ring-orange-500 dark:border-neutral-600 dark:bg-neutral-900"
                            />
                        </div>

                        <fieldset class="flex flex-col gap-4">
                            <legend class="text-sm font-semibold">Skills</legend>
                            <div v-for="(content, skillName) in form.skills" :key="skillName" class="flex flex-col gap-1">
                                <div class="flex items-center justify-between">
                                    <span class="font-mono text-sm font-medium">{{ skillName }}</span>
                                    <UiButton variant="ghost" size="sm" @click="removeSkill(skillName)">Remove</UiButton>
                                </div>
                                <textarea
                                    v-model="form.skills[skillName]" rows="8"
                                    class="w-full rounded-md border border-neutral-300 bg-white px-3 py-2 font-mono text-xs focus:border-orange-500 focus:outline-none focus:ring-1 focus:ring-orange-500 dark:border-neutral-600 dark:bg-neutral-900"
                                />
                            </div>
                            <div class="flex items-end gap-2">
                                <div class="w-60"><UiInput v-model="newSkillName" label="Add skill" placeholder="framework-something" /></div>
                                <UiButton variant="outline" @click="addSkill">Add</UiButton>
                            </div>
                        </fieldset>

                        <div class="flex items-center gap-3">
                            <UiButton type="submit" :disabled="form.processing">Save blueprint</UiButton>
                            <span v-if="saved" class="text-sm text-neutral-500">Saved.</span>
                        </div>
                    </form>
                    <p v-else class="text-sm text-neutral-500">Select a blueprint to edit its masters.</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
