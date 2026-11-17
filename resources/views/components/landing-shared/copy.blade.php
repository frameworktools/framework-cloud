@props(['rows', 'meta', 'title'])
{{-- Step 04's copy scene: Framework's Share dialog, with the image it copies --}}
<div class="w-full max-w-xl rounded-xl bg-white p-5 shadow-xl ring-1 ring-slate-800/10 dark:bg-slate-800 dark:ring-white/10">
    <p class="font-semibold text-slate-900 dark:text-white">Share {{ $title }}</p>
    {{-- The image that will be copied --}}
    <div class="mt-3 overflow-hidden rounded-md bg-white px-3 py-2 text-slate-800 ring-1 ring-slate-800/10">
        <p class="text-[10px] text-slate-500">{{ $meta }}</p>
        <x-landing-shared.table :rows="$rows" :image="true" class="mt-0.5 text-xs [&_td]:py-1 [&_td]:pl-2.5 [&_td]:text-[11px] [&_th]:py-1" />
    </div>
    <p class="mt-3 text-sm text-slate-600 dark:text-slate-300"><span class="font-medium text-slate-800 dark:text-white">Image.</span> Copy or download a PNG for Substack, email, and slides.</p>
    <p class="mt-3 flex justify-end gap-2 text-sm font-medium">
        <span class="rounded-md bg-sky-700 px-3 py-1.5 text-white" data-swap="Copied">Copy image</span>
        <span class="rounded-md bg-slate-800/5 px-3 py-1.5 text-slate-700 dark:bg-white/10 dark:text-slate-200">Download PNG</span>
    </p>
</div>
