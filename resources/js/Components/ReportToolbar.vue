<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, useId } from 'vue';
import {
    BoldIcon, ItalicIcon, UnderlineIcon, StrikethroughIcon, CodeBracketIcon, LinkIcon, LinkSlashIcon,
    ListBulletIcon, NumberedListIcon, H2Icon, H3Icon, MinusIcon, ArrowUturnLeftIcon, ArrowUturnRightIcon,
    ArrowTopRightOnSquareIcon, EllipsisHorizontalIcon,
} from '@heroicons/vue/20/solid';
import { normalizeLink, shortcutLabel } from '@/report-editor.js';

// The report's one formatting toolbar. It acts on the text section that last had focus.
const props = defineProps({ editor: { type: Object, default: null }, words: { type: Number, default: 0 } });
const id = useId();
const ready = computed(() => Boolean(props.editor && !props.editor.isDestroyed));
const chain = () => props.editor.chain().focus();
const active = (name, attrs) => ready.value && props.editor.isActive(name, attrs);

const main = computed(() => [
    [
        { name: 'paragraph', label: 'Body text', shortcut: 'Mod-Alt-0', text: 'Aa', pressed: () => active('paragraph'), run: () => chain().setParagraph().run() },
        { name: 'h2', label: 'Heading', shortcut: 'Mod-Alt-2', icon: H2Icon, pressed: () => active('heading', { level: 2 }), run: () => chain().toggleHeading({ level: 2 }).run() },
        { name: 'h3', label: 'Subheading', shortcut: 'Mod-Alt-3', icon: H3Icon, pressed: () => active('heading', { level: 3 }), run: () => chain().toggleHeading({ level: 3 }).run() },
    ],
    [
        { name: 'bold', label: 'Bold', shortcut: 'Mod-B', icon: BoldIcon, run: () => chain().toggleBold().run() },
        { name: 'italic', label: 'Italic', shortcut: 'Mod-I', icon: ItalicIcon, run: () => chain().toggleItalic().run() },
        { name: 'underline', label: 'Underline', shortcut: 'Mod-U', icon: UnderlineIcon, run: () => chain().toggleUnderline().run() },
        { name: 'strike', label: 'Strikethrough', shortcut: 'Mod-Shift-S', icon: StrikethroughIcon, run: () => chain().toggleStrike().run() },
        { name: 'code', label: 'Inline code', shortcut: 'Mod-E', icon: CodeBracketIcon, run: () => chain().toggleCode().run() },
        { name: 'link', label: 'Link', shortcut: 'Mod-K', icon: LinkIcon, run: () => openLink() },
    ],
    [
        { name: 'bulletList', label: 'Bulleted list', shortcut: 'Mod-Shift-8', icon: ListBulletIcon, run: () => chain().toggleBulletList().run() },
        { name: 'orderedList', label: 'Numbered list', shortcut: 'Mod-Shift-7', icon: NumberedListIcon, run: () => chain().toggleOrderedList().run() },
        { name: 'blockquote', label: 'Quote', shortcut: 'Mod-Shift-B', svg: 'quote', run: () => chain().toggleBlockquote().run() },
    ],
]);
const more = computed(() => [
    { name: 'superscript', label: 'Superscript', shortcut: 'Mod-.', svg: 'sup', run: () => chain().toggleSuperscript().run() },
    { name: 'subscript', label: 'Subscript', shortcut: 'Mod-,', svg: 'sub', run: () => chain().toggleSubscript().run() },
    { name: 'codeBlock', label: 'Code block', shortcut: 'Mod-Alt-C', svg: 'codeblock', run: () => chain().toggleCodeBlock().run() },
    { name: 'horizontalRule', label: 'Divider', icon: MinusIcon, toggle: false, run: () => chain().setHorizontalRule().run() },
    { name: 'clear', label: 'Clear formatting', svg: 'clear', toggle: false, run: () => chain().unsetAllMarks().clearNodes().run() },
]);
const pressed = item => item.toggle === false ? undefined : String(item.pressed ? item.pressed() : active(item.name));
const tip = item => item.shortcut ? `${item.label} (${shortcutLabel(item.shortcut)})` : item.label;

// The More menu
const menuOpen = ref(false);
const menu = ref(null);
const closeMenu = event => { if (menuOpen.value && !menu.value?.contains(event.target)) menuOpen.value = false; };
onMounted(() => document.addEventListener('pointerdown', closeMenu));
onBeforeUnmount(() => document.removeEventListener('pointerdown', closeMenu));
function runMore(item) { item.run(); menuOpen.value = false; }

// The link form
const linking = ref(false);
const linkUrl = ref('');
const linkError = ref('');
const linkInput = ref(null);
function openLink() {
    if (!ready.value) return;
    linkUrl.value = props.editor.getAttributes('link').href || '';
    linkError.value = '';
    linking.value = true;
    nextTick(() => { linkInput.value?.focus(); linkInput.value?.select(); });
}
function closeLink() { linking.value = false; if (ready.value) props.editor.commands.focus(); }
function applyLink() {
    const { url, error } = normalizeLink(linkUrl.value);
    if (error) { linkError.value = error; return; }
    const commands = chain().extendMarkRange('link');
    if (!url) commands.unsetLink().run();
    else if (props.editor.state.selection.empty && !props.editor.isActive('link')) {
        commands.insertContent({ type: 'text', text: linkUrl.value.trim(), marks: [{ type: 'link', attrs: { href: url } }] }).insertContent(' ').run();
    } else commands.setLink({ href: url }).run();
    linking.value = false;
}
function removeLink() { chain().extendMarkRange('link').unsetLink().run(); linking.value = false; }
defineExpose({ openLink });

const button = 'inline-flex h-8 min-w-8 items-center justify-center rounded-md px-1.5 text-slate-600 transition-colors hover:bg-slate-100 hover:text-slate-900 focus-visible:outline-2 focus-visible:outline-sky-500 disabled:pointer-events-none disabled:opacity-35 aria-pressed:bg-sky-50 aria-pressed:text-sky-700 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white dark:aria-pressed:bg-sky-950 dark:aria-pressed:text-sky-300';
</script>

<template>
    <div>
        <div role="toolbar" aria-label="Text formatting" class="flex flex-wrap items-center gap-0.5 sm:flex-nowrap">
            <template v-for="(group, index) in main" :key="index">
                <span v-if="index" aria-hidden="true" class="mx-1 h-5 w-px shrink-0 bg-slate-200 dark:bg-slate-700" />
                <button v-for="item in group" :key="item.name" type="button" :class="button" :disabled="!ready" :aria-label="item.label" :title="tip(item)" :aria-pressed="pressed(item)"
                    @mousedown.prevent @click="item.run">
                    <component :is="item.icon" v-if="item.icon" class="size-4" aria-hidden="true" />
                    <span v-else-if="item.text" class="text-xs font-semibold" aria-hidden="true">{{ item.text }}</span>
                    <svg v-else viewBox="0 0 20 20" class="size-4" aria-hidden="true" fill="currentColor"><path d="M4.5 5.5h4v4.2c0 2.6-1.3 4.3-3.6 4.8l-.5-1.3c1.2-.4 1.9-1.3 2-2.5H4.5V5.5Zm7 0h4v4.2c0 2.6-1.3 4.3-3.6 4.8l-.5-1.3c1.2-.4 1.9-1.3 2-2.5h-1.9V5.5Z"/></svg>
                </button>
            </template>
            <div ref="menu" class="relative">
                <button type="button" :class="button" :disabled="!ready" aria-label="More formatting" title="More formatting" aria-haspopup="menu" :aria-expanded="String(menuOpen)" @mousedown.prevent @click="menuOpen = !menuOpen">
                    <EllipsisHorizontalIcon class="size-4" aria-hidden="true" />
                </button>
                <div v-if="menuOpen" role="menu" class="absolute left-0 top-full z-30 mt-1 w-56 rounded-lg border border-slate-200 bg-white p-1 shadow-lg dark:border-slate-700 dark:bg-slate-800" @keydown.esc="menuOpen = false">
                    <button v-for="item in more" :key="item.name" type="button" role="menuitemcheckbox" :aria-checked="pressed(item) ?? 'false'"
                        class="flex w-full items-center gap-3 rounded-md px-2.5 py-1.5 text-left text-sm text-slate-700 hover:bg-slate-100 aria-checked:text-sky-700 dark:text-slate-200 dark:hover:bg-slate-700 dark:aria-checked:text-sky-300"
                        @mousedown.prevent @click="runMore(item)">
                        <component :is="item.icon" v-if="item.icon" class="size-4 text-slate-500" aria-hidden="true" />
                        <svg v-else-if="item.svg === 'sup'" viewBox="0 0 20 20" class="size-4 text-slate-500" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><path d="m3 7 7 9M10 7l-7 9"/><path d="M13 5.2a1.6 1.6 0 1 1 2.6 1.2L13 9h3.2" stroke-width="1.3"/></svg>
                        <svg v-else-if="item.svg === 'sub'" viewBox="0 0 20 20" class="size-4 text-slate-500" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><path d="m3 4 7 9M10 4l-7 9"/><path d="M13 13.2a1.6 1.6 0 1 1 2.6 1.2L13 17h3.2" stroke-width="1.3"/></svg>
                        <svg v-else-if="item.svg === 'codeblock'" viewBox="0 0 20 20" class="size-4 text-slate-500" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="3.5" width="15" height="13" rx="2"/><path d="m8 8-2 2 2 2M12 8l2 2-2 2"/></svg>
                        <svg v-else-if="item.svg === 'clear'" viewBox="0 0 20 20" class="size-4 text-slate-500" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M5 4.5h10M10 4.5l-3 11M12.5 12.5l4 4M16.5 12.5l-4 4"/></svg>
                        <span class="flex-1">{{ item.label }}</span>
                        <kbd v-if="item.shortcut" class="font-sans text-xs text-slate-400">{{ shortcutLabel(item.shortcut) }}</kbd>
                    </button>
                </div>
            </div>
            <span class="ml-auto flex shrink-0 items-center gap-0.5">
                <span v-if="!ready" class="mr-2 hidden text-xs text-slate-400 sm:inline">Click into text to format it</span>
                <button type="button" :class="button" aria-label="Undo" :title="`Undo (${shortcutLabel('Mod-Z')})`" :disabled="!ready || !editor.can().undo()" @mousedown.prevent @click="chain().undo().run()"><ArrowUturnLeftIcon class="size-4" aria-hidden="true" /></button>
                <button type="button" :class="button" aria-label="Redo" :title="`Redo (${shortcutLabel('Mod-Shift-Z')})`" :disabled="!ready || !editor.can().redo()" @mousedown.prevent @click="chain().redo().run()"><ArrowUturnRightIcon class="size-4" aria-hidden="true" /></button>
            </span>
        </div>

        <form v-if="linking && ready" class="mt-2 flex flex-wrap items-start gap-2 rounded-lg border border-slate-200 bg-slate-50 p-2 dark:border-slate-700 dark:bg-slate-950/60" @submit.prevent="applyLink">
            <label :for="id" class="sr-only">Link address</label>
            <div class="min-w-56 flex-1">
                <input :id="id" ref="linkInput" v-model="linkUrl" type="text" inputmode="url" autocomplete="off" placeholder="Paste or type a link, such as example.org"
                    :aria-invalid="linkError ? 'true' : undefined" :aria-describedby="linkError ? `${id}-error` : undefined"
                    class="block w-full rounded-md border border-slate-300 bg-white px-2.5 py-1.5 text-sm text-slate-900 focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100"
                    @keydown.esc.prevent="closeLink">
                <p v-if="linkError" :id="`${id}-error`" class="mt-1 text-xs text-red-700 dark:text-red-400">{{ linkError }}</p>
            </div>
            <button type="submit" class="rounded-md bg-sky-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-sky-700">{{ editor.isActive('link') ? 'Update' : 'Add link' }}</button>
            <a v-if="editor.isActive('link')" :href="editor.getAttributes('link').href" target="_blank" rel="noopener noreferrer" title="Open link in a new tab" :class="button"><ArrowTopRightOnSquareIcon class="size-4" aria-hidden="true" /><span class="sr-only">Open link</span></a>
            <button v-if="editor.isActive('link')" type="button" title="Remove link" :class="button" @click="removeLink"><LinkSlashIcon class="size-4" aria-hidden="true" /><span class="sr-only">Remove link</span></button>
            <button type="button" class="rounded-md px-2 py-1.5 text-sm text-slate-600 hover:bg-slate-200 dark:text-slate-300 dark:hover:bg-slate-800" @click="closeLink">Cancel</button>
        </form>
    </div>
</template>
