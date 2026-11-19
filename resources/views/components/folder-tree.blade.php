@props(['text'])
{{-- A folder tree drawn with box characters: folders in blue, branch lines and # notes dimmed.
     Line height just over the font size keeps the vertical connectors joined (as in ProjectTreeView.vue). --}}
<pre {{ $attributes->class('overflow-x-auto px-3 py-2.5 font-mono text-xs leading-[1.2] text-slate-100') }} style="color-scheme: dark"><code>{!! preg_replace(
    ['~(?:(?<=── )|^)([^\n#]*?/)(?=\s|$)~mu', '~([│├└─]+)~u', '~(?<=\s)(#[^\n]*)~u'],
    ['<span class="text-sky-300">$1</span>', '<span class="text-slate-500">$1</span>', '<span class="text-slate-500">$1</span>'],
    e($text),
) !!}</code></pre>
