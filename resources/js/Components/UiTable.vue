<script setup>
// A table after Flux: no box, a rule under the headings, a hairline between rows, and the
// first and last columns flush with the page. Write plain <thead>/<tbody>/<th>/<td> inside.
// Mark the cell people read first with class "strong"; give action columns class "actions".
defineProps({ label: String });
</script>

<template>
    <div class="relative -mx-1 min-w-0 max-w-full overflow-x-auto px-1">
        <table :aria-label="label"
            class="min-w-full border-separate border-spacing-0 text-sm whitespace-nowrap
                [&_th]:border-b [&_th]:border-slate-800/10 [&_th]:px-3 [&_th]:py-3 [&_th]:text-start [&_th]:font-medium [&_th]:text-slate-800 dark:[&_th]:border-white/15 dark:[&_th]:text-white
                [&_td]:px-3 [&_td]:py-3 [&_td]:text-slate-500 dark:[&_td]:text-slate-400
                [&_tbody_tr:not(:first-child)_td]:border-t [&_tbody_tr_td]:border-slate-800/10 dark:[&_tbody_tr_td]:border-white/10
                [&_tr>*:first-child]:ps-0 [&_tr>*:last-child]:pe-0
                [&_td.strong]:font-medium [&_td.strong]:text-slate-800 dark:[&_td.strong]:text-white
                [&_.actions]:text-end [&_.numeric]:text-end [&_.numeric]:tabular-nums">
            <slot />
        </table>
    </div>
</template>
