@props(['title' => null, 'wrap' => false])

{{-- A dark code window with an optional file name bar --}}
<div {{ $attributes->merge(['class' => 'overflow-hidden rounded-xl bg-slate-900 text-slate-100 shadow-sm ring-1 ring-slate-900/5 dark:ring-slate-800']) }}>
    @if ($title)
        <div class="flex items-center gap-2 border-b border-white/10 px-4 py-2.5">
            <span class="size-2.5 rounded-full bg-slate-700"></span>
            <span class="size-2.5 rounded-full bg-slate-700"></span>
            <span class="size-2.5 rounded-full bg-slate-700"></span>
            <span class="ml-2 font-mono text-xs text-slate-400">{{ $title }}</span>
        </div>
    @endif
    <div @class(['overflow-x-auto p-5 font-mono text-[13px] leading-relaxed', 'whitespace-pre-wrap break-all' => $wrap, 'whitespace-pre' => ! $wrap])>{!! trim($slot) !!}</div>
</div>
