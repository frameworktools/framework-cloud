<script setup>
import { watch, onBeforeUnmount, computed } from 'vue';
import { useEditor, EditorContent } from '@tiptap/vue-3';
import { BubbleMenu } from '@tiptap/vue-3/menus';
import { Extension } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import Subscript from '@tiptap/extension-subscript';
import Superscript from '@tiptap/extension-superscript';
import Typography from '@tiptap/extension-typography';
import { Placeholder, CharacterCount } from '@tiptap/extensions';
import { BoldIcon, ItalicIcon, UnderlineIcon, CodeBracketIcon, LinkIcon } from '@heroicons/vue/20/solid';
import { shortcutLabel } from '@/report-editor.js';

// One text section of a report. The page's shared toolbar formats whichever section has focus,
// so this component announces its editor on focus and asks the toolbar to open the link form.
const text = defineModel({ type: String, default: '' });
const props = defineProps({ label: { type: String, default: 'Report text' }, autofocus: Boolean });
const emit = defineEmits(['focus', 'link']);

const LinkShortcut = Extension.create({
    name: 'linkShortcut',
    addKeyboardShortcuts: () => ({ 'Mod-k': ({ editor }) => { emit('link', editor); return true; } }),
});

const editor = useEditor({
    extensions: [
        StarterKit.configure({
            heading: { levels: [2, 3] },
            link: { openOnClick: false, autolink: true, defaultProtocol: 'https', protocols: ['mailto'] },
            trailingNode: false,
        }),
        Subscript, Superscript, Typography, LinkShortcut, CharacterCount,
        Placeholder.configure({ placeholder: 'Write here. Type ## for a heading, - for a list, or > for a quote.' }),
    ],
    content: text.value,
    autofocus: props.autofocus ? 'end' : false,
    editorProps: {
        attributes: {
            role: 'textbox', 'aria-label': props.label, 'aria-multiline': 'true',
            class: 'report-prose min-h-12 focus:outline-none',
        },
    },
    onFocus: ({ editor }) => emit('focus', editor),
    onUpdate: ({ editor }) => { text.value = editor.isEmpty ? '' : editor.getHTML(); },
});
watch(text, (value) => {
    const current = editor.value?.isEmpty ? '' : editor.value?.getHTML();
    if (editor.value && current !== value) editor.value.commands.setContent(value || '', { emitUpdate: false });
});
onBeforeUnmount(() => editor.value?.destroy());
defineExpose({ editor });

const bubble = computed(() => editor.value ? [
    { name: 'bold', label: 'Bold', shortcut: 'Mod-B', icon: BoldIcon, run: () => editor.value.chain().focus().toggleBold().run() },
    { name: 'italic', label: 'Italic', shortcut: 'Mod-I', icon: ItalicIcon, run: () => editor.value.chain().focus().toggleItalic().run() },
    { name: 'underline', label: 'Underline', shortcut: 'Mod-U', icon: UnderlineIcon, run: () => editor.value.chain().focus().toggleUnderline().run() },
    { name: 'code', label: 'Inline code', shortcut: 'Mod-E', icon: CodeBracketIcon, run: () => editor.value.chain().focus().toggleCode().run() },
    { name: 'link', label: 'Link', shortcut: 'Mod-K', icon: LinkIcon, run: () => emit('link', editor.value) },
] : []);
const showBubble = ({ editor: instance, state }) => !state.selection.empty && !instance.isActive('codeBlock') && instance.isEditable;
</script>

<template>
    <div v-if="editor" class="report-editor -mx-3 rounded-lg px-3 py-2 ring-1 ring-transparent transition-shadow hover:ring-slate-200 focus-within:ring-sky-300 dark:hover:ring-slate-700 dark:focus-within:ring-sky-800">
        <BubbleMenu :editor="editor" :should-show="showBubble" :options="{ placement: 'top', offset: 8 }"
            class="flex items-center gap-0.5 rounded-lg border border-slate-200 bg-white p-1 shadow-lg dark:border-slate-700 dark:bg-slate-800">
            <button v-for="item in bubble" :key="item.name" type="button" :aria-label="item.label" :title="`${item.label} (${shortcutLabel(item.shortcut)})`" :aria-pressed="String(editor.isActive(item.name))"
                class="inline-flex size-7 items-center justify-center rounded text-slate-600 hover:bg-slate-100 aria-pressed:bg-sky-50 aria-pressed:text-sky-700 dark:text-slate-300 dark:hover:bg-slate-700 dark:aria-pressed:bg-sky-950 dark:aria-pressed:text-sky-300"
                @mousedown.prevent @click="item.run"><component :is="item.icon" class="size-4" aria-hidden="true" /></button>
        </BubbleMenu>
        <EditorContent :editor="editor" />
    </div>
</template>

<style>
.report-editor .tiptap p.is-editor-empty:first-child::before {
    content: attr(data-placeholder);
    float: left;
    height: 0;
    pointer-events: none;
    color: rgb(148 163 184);
}
.report-editor .tiptap > :first-child { margin-top: 0; }
.report-editor .tiptap > :last-child { margin-bottom: 0; }
.report-editor .tiptap :is(sub, sup) { font-size: 0.75em; }
.report-editor .tiptap a { cursor: text; }
.report-editor .tiptap hr.ProseMirror-selectednode { outline: 2px solid rgb(56 189 248); }
</style>
