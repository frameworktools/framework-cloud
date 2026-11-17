@props(['image', 'title'])
{{-- Step 04's copy scene: Framework's Share dialog, with the image it copies --}}
<div class="w-full max-w-xl rounded-xl bg-white p-5 shadow-xl ring-1 ring-slate-800/10 dark:bg-slate-800 dark:ring-white/10">
    <p class="font-semibold text-slate-900 dark:text-white">Share {{ $title }}</p>
    {{-- The image that will be copied --}}
    <img src="{{ $image['src'] }}" width="{{ $image['width'] }}" height="{{ $image['height'] }}" alt="" loading="lazy" class="mx-auto mt-3 block h-auto max-h-40 w-auto rounded-md ring-1 ring-slate-800/10">
    <p class="mt-3 text-sm text-slate-600 dark:text-slate-300"><span class="font-medium text-slate-800 dark:text-white">Image.</span> Copy or download a PNG for Substack, email, and slides.</p>
    <p class="mt-3 flex justify-end gap-2 text-sm font-medium">
        <span class="rounded-md bg-sky-700 px-3 py-1.5 text-white" data-swap="Copied">Copy image</span>
        <span class="rounded-md bg-slate-800/5 px-3 py-1.5 text-slate-700 dark:bg-white/10 dark:text-slate-200">Download PNG</span>
    </p>
</div>
