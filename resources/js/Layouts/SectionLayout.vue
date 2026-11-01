<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { ChevronDownIcon, ChevronRightIcon } from '@heroicons/vue/20/solid';
import AppLayout from '@/Layouts/AppLayout.vue';

/**
 * The Framework GUI's three-column page: the app sidebar, a section sidebar
 * with the page's main button at its foot, and the main panel.
 *
 * Items either link to other pages ({ label, href }) or to sections of this
 * page ({ label, anchor }). Items have no icons; an item that opens and
 * closes others says so with expanded: true or false, shown as an arrow. Anchored sections are highlighted
 * as they scroll into view. A { heading } item starts a group, and a link
 * with preserveState keeps the page's unsaved form when it moves. Items can
 * be indented under a quiet item that heads them, and an exact link is
 * current only on its own page.
 */
const props = defineProps({
    heading: String,
    back: Object, // { label, href }: a way out of this context, shown above the heading
    group: String,
    items: { type: Array, default: () => [] },
    // A narrower column, for short labels such as Project defaults'
    narrow: Boolean,
    // Unsaved changes somewhere in this section: an amber dot by the heading
    dirty: Boolean,
});

const page = usePage();
const active = ref(props.items.find((i) => i.anchor)?.anchor ?? null);

const isActive = (item) => (item.href
    ? page.url === item.href || page.url.startsWith(item.href + '?') || (!item.exact && page.url.startsWith(item.href + '/'))
    : active.value === item.anchor);

const anchors = computed(() => props.items.filter((i) => i.anchor).map((i) => i.anchor));

function spy() {
    let current = anchors.value[0] ?? null;
    for (const id of anchors.value) {
        const el = document.getElementById(id);
        if (el && el.getBoundingClientRect().top <= 140) current = id;
    }
    // At the bottom of the page the last section counts as current
    if (window.innerHeight + window.scrollY >= document.body.scrollHeight - 4) current = anchors.value.at(-1) ?? current;
    active.value = current;
}

function go(item) {
    active.value = item.anchor;
    document.getElementById(item.anchor)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

onMounted(() => {
    if (anchors.value.length) {
        window.addEventListener('scroll', spy, { passive: true });
        spy();
    }
});
onBeforeUnmount(() => window.removeEventListener('scroll', spy));

// Links reach 12px into the column's padding (lg:-mx-3), so their text lines
// up with the headings and their backgrounds sit around it.
// A quiet item heads the items indented under it: bold while open, never highlighted itself
const itemClass = (item) => [
    item.indent ? 'pl-6!' : '',
    item.quiet
        ? 'font-semibold text-slate-900 hover:bg-slate-100 dark:text-white dark:hover:bg-slate-800'
        : isActive(item)
            ? 'bg-sky-100 font-semibold text-sky-800 dark:bg-sky-950/60 dark:text-sky-300'
            : 'text-slate-700 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white',
];
</script>

<template>
    <AppLayout bleed>
        <div class="flex min-h-screen max-lg:flex-col">
            <aside class="shrink-0 border-slate-200 lg:border-r dark:border-slate-800 max-lg:border-b" :class="narrow ? 'lg:w-56' : 'lg:w-64'">
                <!-- On wide screens the column scrolls on its own once it's taller than the window; the actions follow its contents and stick to the bottom only while it scrolls -->
                <div class="flex flex-col lg:sticky lg:top-0 lg:max-h-screen lg:overflow-y-auto">
                    <div class="flex flex-col gap-4 p-6 [&>*]:shrink-0">
                        <slot name="top" />
                        <Link v-if="back" :href="back.href" class="-mb-2 text-sm text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white">← {{ back.label }}</Link>
                        <h2 v-if="heading" class="-mb-2 flex items-center gap-2 text-sm font-semibold text-slate-900 dark:text-white">
                            <span class="truncate">{{ heading }}</span>
                            <span v-if="dirty" class="size-2 shrink-0 rounded-full bg-amber-500" title="Unsaved changes" />
                            <span v-if="dirty" class="sr-only">Unsaved changes</span>
                        </h2>

                        <slot name="lead" />

                        <nav class="flex gap-1 max-lg:-mx-1 max-lg:overflow-x-auto lg:flex-col lg:gap-0.5">
                            <div v-if="group" class="mb-1 mt-2 text-xs font-medium uppercase tracking-wider text-slate-500 max-lg:hidden">{{ group }}</div>
                            <template v-for="item in items" :key="item.href ?? item.anchor ?? item.heading">
                                <div v-if="item.heading" class="mb-1 mt-4 text-xs font-semibold uppercase tracking-wider text-slate-500 first:mt-5 max-lg:hidden dark:text-slate-400">{{ item.heading }}</div>
                                <Link
                                    v-else-if="item.href" :href="item.href" :preserve-state="!!item.preserveState" :preserve-scroll="!!item.preserveState"
                                    class="flex shrink-0 items-center justify-between gap-2 rounded-md px-3 py-2 text-sm transition lg:-mx-3" :class="itemClass(item)"
                                >
                                    <span class="truncate">{{ item.label }}</span>
                                    <component :is="item.expanded ? ChevronDownIcon : ChevronRightIcon" v-if="item.expanded !== undefined" class="size-4 shrink-0 text-slate-400" />
                                </Link>
                                <a
                                    v-else :href="`#${item.anchor}`"
                                    class="flex shrink-0 items-center rounded-md px-3 py-2 text-sm transition lg:-mx-3" :class="itemClass(item)"
                                    @click.prevent="go(item)"
                                >{{ item.label }}</a>
                            </template>
                        </nav>

                        <div v-if="$slots.aside" class="max-lg:hidden">
                            <slot name="aside" />
                        </div>

                    </div>
                    <div v-if="$slots.actions" class="-mt-2 flex shrink-0 flex-col gap-2 bg-white pb-6 pt-2 dark:bg-slate-950 max-lg:hidden lg:sticky lg:bottom-0 px-6">
                        <slot name="actions" />
                    </div>
                </div>
            </aside>

            <!-- The same margins as the project list, with the content against its left edge -->
            <div class="min-w-0 flex-1 px-6 py-10 lg:px-12 xl:px-16">
                <div class="max-w-4xl">
                    <slot />

                    <!-- Narrow screens: the sidebar's summary and button go under the page -->
                    <div v-if="$slots.aside" class="mt-8 lg:hidden">
                        <slot name="aside" />
                    </div>
                    <div v-if="$slots.actions" class="mt-8 flex flex-col gap-2 lg:hidden">
                        <slot name="actions" />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
