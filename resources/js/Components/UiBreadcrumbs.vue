<script setup>
import { HomeIcon, Square2StackIcon } from '@heroicons/vue/24/outline';
import { ChevronRightIcon } from '@heroicons/vue/20/solid';

// The full-width bar at the top of a page outside the project section (an artifact, a
// collection, a report): home, then each step up to this page, split by compact chevrons.
// Page-wide actions (status, View, Save, Publish) go in the actions slot on the right.
// Pages inside the three-column project layout don't use it; their second column says where you are.
defineProps({
    items: { type: Array, required: true }, // [{ label, href }], the last one is this page
    width: { type: String, default: 'max-w-6xl' }, // match the page's content column
    sticky: Boolean,
    inline: Boolean,
    trailing: Boolean,
});
</script>

<template>
    <div :class="inline ? '' : ['z-20 border-b border-slate-800/10 bg-white/90 backdrop-blur dark:border-white/10 dark:bg-slate-950/90', { 'sm:sticky sm:top-0': sticky }]">
        <div class="flex w-full flex-wrap items-center justify-between gap-x-4" :class="inline ? '' : ['mx-auto min-h-10 px-5 sm:flex-nowrap sm:px-8', width]">
            <nav aria-label="Breadcrumb" class="flex min-w-0 self-stretch">
                <ol role="list" class="flex min-w-0 flex-wrap items-center gap-x-2 gap-y-2" :class="{ 'min-h-10': !inline }">
                    <li class="flex shrink-0">
                        <Link href="/projects" class="flex items-center text-slate-400 hover:text-slate-600 dark:text-slate-500 dark:hover:text-slate-300">
                            <HomeIcon class="size-4 shrink-0" aria-hidden="true" /><span class="sr-only">Home</span>
                        </Link>
                    </li>
                    <li v-for="(item, index) in items" :key="index" class="flex min-w-0" :class="{ 'max-sm:hidden': !inline && index < items.length - 1, 'shrink-0': index < items.length - 1 }">
                        <ChevronRightIcon aria-hidden="true" class="size-4 shrink-0 self-center text-slate-400 dark:text-slate-500" />
                        <div v-if="item.links" class="ml-2 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm font-medium text-slate-500 dark:text-slate-400"><template v-for="(link, linkIndex) in item.links" :key="link.href"><span v-if="linkIndex" aria-hidden="true">,</span><Link :href="link.href" class="inline-flex items-center gap-1.5 hover:text-slate-800 dark:hover:text-white"><Square2StackIcon v-if="link.icon === 'collection'" class="size-4 shrink-0 text-slate-400" aria-hidden="true" />{{ link.label }}</Link></template></div>
                        <Link v-else-if="item.href && (trailing || index < items.length - 1)" :href="item.href"
                            class="ml-2 flex max-w-60 items-center gap-1.5 truncate text-sm font-medium text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white">
                            <Square2StackIcon v-if="item.icon === 'collection'" class="size-4 shrink-0 text-slate-400" aria-hidden="true" /><span class="truncate">{{ item.label }}</span>
                        </Link>
                        <span v-else aria-current="page" class="ml-2 flex min-w-0 items-center text-sm font-medium text-slate-800 dark:text-white">
                            <span class="truncate">{{ item.label }}</span>
                        </span>
                    </li>
                    <li v-if="trailing" aria-hidden="true" class="flex"><ChevronRightIcon class="size-4 text-slate-400 dark:text-slate-500" /></li>
                </ol>
            </nav>
            <div v-if="$slots.actions" class="flex shrink-0 items-center gap-3 py-1 max-sm:ml-auto">
                <slot name="actions" />
            </div>
        </div>
    </div>
</template>
