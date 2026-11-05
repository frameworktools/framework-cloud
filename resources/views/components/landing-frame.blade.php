@props(['shadow' => 'sm', 'bare' => false])
{{--
    Two hairline borders with a faint grey gap between them. By default the slot sits in a
    white inner card and attributes go to that card. With bare, the slot brings its own
    rounded panel (the dark code panels) and attributes go to the outer frame.
--}}
@php
    $frame = [
        'min-w-0 border border-slate-800/10 bg-slate-50/50 p-1.5 dark:border-white/10 dark:bg-white/[0.025]',
        'shadow-card dark:shadow-card-dark' => $shadow === 'sm',
        'shadow-panel dark:shadow-panel-dark' => $shadow === 'xl',
    ];
@endphp
@if ($bare)
    <div {{ $attributes->class(['rounded-[18px]', ...$frame]) }}>{{ $slot }}</div>
@else
    <div @class(['flex rounded-2xl', ...$frame])>
        <div {{ $attributes->class('min-w-0 flex-1 rounded-[10px] border border-slate-800/10 bg-white dark:border-white/15 dark:bg-slate-950') }}>
            {{ $slot }}
        </div>
    </div>
@endif
