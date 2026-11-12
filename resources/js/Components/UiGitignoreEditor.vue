<script setup>
import { computed, ref, useId } from 'vue';
import UiField from '@/Components/UiField.vue';

defineProps({ label: String, description: String, error: String, placeholder: { type: String, default: '# Generated files\n*.log\n/cache/' } });
const model = defineModel({ type: String, default: '' });
const id = useId();
const backdrop = ref(null);
const lines = computed(() => model.value.split('\n').map((line) => {
    if (line.startsWith('#')) return [{ text: line, kind: 'comment' }];
    return line.split(/(\\.|\*\*|\*|\?|\[[^\]]*\]|^!)/g).filter(Boolean).map((text) => ({ text, kind: text === '!' ? 'exception' : /^(\\.|\*\*?|\?|\[)/.test(text) ? 'pattern' : '' }));
}));
function scroll(event) {
    backdrop.value.scrollTop = event.target.scrollTop;
    backdrop.value.scrollLeft = event.target.scrollLeft;
}
</script>

<template>
    <UiField :id="id" :label="label" :description="description" :error="error">
        <div class="gitignore-editor relative overflow-hidden rounded-lg border border-slate-300 bg-white focus-within:border-sky-600 focus-within:ring-1 focus-within:ring-sky-600 dark:border-slate-700 dark:bg-slate-950">
            <pre ref="backdrop" aria-hidden="true" class="highlight pointer-events-none absolute inset-0 m-0 overflow-hidden text-slate-800 dark:text-slate-200"><template v-for="(line, i) in lines" :key="i"><span v-for="(token, j) in line" :key="j" :class="token.kind">{{ token.text }}</span>{{ '\n' }}</template></pre>
            <textarea :id="id" v-model="model" :placeholder="placeholder" class="source relative block w-full resize-y bg-transparent text-transparent caret-slate-900 outline-none dark:caret-white" wrap="off" spellcheck="false" autocapitalize="off" autocomplete="off" :aria-invalid="!!error" :aria-describedby="[description && `${id}-description`, error && `${id}-error`].filter(Boolean).join(' ') || undefined" @scroll="scroll" />
        </div>
    </UiField>
</template>

<style scoped>
.highlight, .source {
    padding: 1rem;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 14px;
    line-height: 1.75;
    letter-spacing: normal;
    tab-size: 4;
    white-space: pre;
    overflow-wrap: normal;
    border: 0;
}
.source { height: max(32rem, 65vh); min-height: 24rem; }
.source::placeholder { color: #64748b; }
:global(.dark .gitignore-editor .source)::placeholder { color: #94a3b8; }
.source::selection { background: rgb(14 165 233 / 25%); }
.comment { color: #4d7c0f; }
.pattern { color: #7c3aed; }
.exception { color: #b45309; }
:global(.dark .gitignore-editor .comment) { color: #a3be8c; }
:global(.dark .gitignore-editor .pattern) { color: #c4b5fd; }
:global(.dark .gitignore-editor .exception) { color: #fbbf24; }
@media (forced-colors: active) { .source { color: CanvasText; } .highlight { display: none; } }
</style>
