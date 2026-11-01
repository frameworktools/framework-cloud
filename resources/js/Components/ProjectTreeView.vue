<script setup>
import { computed } from 'vue';

/**
 * A project's files and folders as an indented tree with connectors, from
 * the flat entries ProjectTree returns. Folders made later are lighter, and
 * what a name doesn't say is in its tooltip.
 */
const props = defineProps({
    entries: { type: Array, required: true },
    // Show when each conditional entry applies beside its name, not only in its tooltip
    showWhen: Boolean,
});

// The folder tree with connectors, from the preview's flat entries
const treeLines = (entries) => {
    const root = { children: new Map() };
    for (const entry of entries) {
        let node = root;
        const parts = entry.path.split('/');
        parts.forEach((part, i) => {
            if (!node.children.has(part)) node.children.set(part, { name: part, dir: true, children: new Map() });
            node = node.children.get(part);
            if (i === parts.length - 1) Object.assign(node, { dir: entry.type === 'dir', note: entry.note, when: entry.when, later: entry.later });
        });
    }
    const lines = [];
    const sorted = (node) => [...node.children.values()].sort((a, b) => (a.dir === b.dir ? a.name.localeCompare(b.name) : a.dir ? -1 : 1));
    const walk = (node, prefix) => {
        const kids = sorted(node);
        kids.forEach((child, i) => {
            const last = i === kids.length - 1;
            // What a name doesn't say goes in its tooltip
            const title = [child.note && child.note[0].toUpperCase() + child.note.slice(1), child.when && `Only ${child.when}`, child.later && 'Made when first used or rendered'].filter(Boolean).join('. ') || undefined;
            lines.push({ prefix: prefix + (last ? '└── ' : '├── '), name: child.name + (child.dir ? '/' : ''), dir: child.dir, later: !!child.later, when: child.when, title });
            walk(child, prefix + (last ? '    ' : '│   '));
        });
    };
    // A folder holding only later folders is itself made later
    const mark = (node) => {
        node.children.forEach(mark);
        if (node.children.size && [...node.children.values()].every((c) => c.later)) node.later = true;
    };
    root.children.forEach(mark);
    walk(root, '');
    return lines;
};

const lines = computed(() => treeLines(props.entries));
</script>

<template>
    <div class="overflow-x-auto">
        <!-- Line height just over the font size keeps the connectors joined -->
        <div class="whitespace-pre font-mono text-xs leading-[1.2] text-slate-800 dark:text-slate-200"><div>project-dir/</div><div v-for="(line, i) in lines" :key="i"><span class="text-slate-400 dark:text-slate-600">{{ line.prefix }}</span><span :class="[line.dir ? 'font-medium' : '', line.later ? 'text-slate-400 dark:text-slate-500' : '']" :title="line.title">{{ line.name }}</span><span v-if="showWhen && line.when" class="ml-2 font-sans text-[0.6875rem] text-slate-500 dark:text-slate-400">{{ line.when }}</span></div></div>
        <p v-if="entries.some((e) => e.later)" class="mt-2 text-xs text-slate-500">Lighter folders are made when they're first used or rendered.</p>
    </div>
</template>
