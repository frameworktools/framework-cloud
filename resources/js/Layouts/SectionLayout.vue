<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

/**
 * The Framework GUI's three-column page: the app sidebar, a section sidebar
 * with the page's main button at its foot, and the main panel.
 *
 * Items either link to other pages ({ label, href, icon }) or to sections
 * of this page ({ label, anchor, icon }). Anchored sections are highlighted
 * as they scroll into view.
 */
const props = defineProps({
    heading: String,
    group: String,
    items: { type: Array, default: () => [] },
});

const page = usePage();
const active = ref(props.items.find((i) => i.anchor)?.anchor ?? null);

const isActive = (item) => (item.href
    ? page.url === item.href || page.url.startsWith(item.href + '/') || page.url.startsWith(item.href + '?')
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

const itemClass = (item) => (isActive(item)
    ? 'bg-sky-50 font-medium text-sky-700 dark:bg-sky-900/20 dark:text-sky-400'
    : 'text-slate-700 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800/60');
</script>

<template>
    <AppLayout bleed>
        <div class="flex min-h-screen max-lg:flex-col">
            <aside class="shrink-0 border-slate-200 lg:w-64 lg:border-r dark:border-slate-800 max-lg:border-b">
                <div class="flex flex-col gap-4 p-6 lg:sticky lg:top-0 lg:max-h-screen lg:overflow-y-auto">
                    <h2 v-if="heading" class="text-sm font-semibold text-slate-900 dark:text-white">{{ heading }}</h2>

                    <nav class="flex gap-1 max-lg:-mx-1 max-lg:overflow-x-auto lg:flex-col">
                        <div v-if="group" class="mb-1 mt-2 px-3 text-xs font-medium uppercase tracking-wider text-slate-500 max-lg:hidden">{{ group }}</div>
                        <template v-for="item in items" :key="item.href ?? item.anchor">
                            <Link
                                v-if="item.href" :href="item.href"
                                class="flex shrink-0 items-center gap-2 rounded-md px-3 py-2 text-sm transition" :class="itemClass(item)"
                            >
                                <component :is="item.icon" v-if="item.icon" class="size-4 shrink-0" />
                                {{ item.label }}
                            </Link>
                            <a
                                v-else :href="`#${item.anchor}`"
                                class="flex shrink-0 items-center gap-2 rounded-md px-3 py-2 text-sm transition" :class="itemClass(item)"
                                @click.prevent="go(item)"
                            >
                                <component :is="item.icon" v-if="item.icon" class="size-4 shrink-0" />
                                {{ item.label }}
                            </a>
                        </template>
                    </nav>

                    <div v-if="$slots.aside" class="max-lg:hidden">
                        <slot name="aside" />
                    </div>

                    <div v-if="$slots.actions" class="flex flex-col gap-2 border-t border-slate-200 pt-4 dark:border-slate-800 max-lg:hidden">
                        <slot name="actions" />
                    </div>
                </div>
            </aside>

            <div class="min-w-0 flex-1 p-6 lg:p-10">
                <div class="mx-auto max-w-4xl">
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
