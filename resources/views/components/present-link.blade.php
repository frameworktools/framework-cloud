@props(['target'])
@php($short = \App\Models\ShortLink::for($target))
{{-- Present: a full-screen QR code and short link, for slides and projectors --}}
<button type="button" onclick="document.getElementById('present-link').showModal()"
    class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-sm text-slate-700 hover:bg-slate-800/5 dark:text-slate-200 dark:hover:bg-white/10"
    aria-haspopup="dialog" title="Show a QR code and short link">
    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 3.75 9.375v-4.5ZM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 0 1-1.125-1.125v-4.5ZM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 13.5 9.375v-4.5Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 6.75h.75v.75h-.75v-.75ZM6.75 16.5h.75v.75h-.75v-.75ZM16.5 6.75h.75v.75h-.75v-.75ZM13.5 13.5h.75v.75h-.75v-.75ZM13.5 19.5h.75v.75h-.75v-.75ZM19.5 13.5h.75v.75h-.75v-.75ZM19.5 19.5h.75v.75h-.75v-.75ZM16.5 16.5h.75v.75h-.75v-.75Z"/></svg>
    Present
</button>
<dialog id="present-link" aria-label="QR code and short link"
    class="m-0 h-dvh max-h-none w-dvw max-w-none bg-white p-0 text-slate-900 backdrop:bg-white"
    onclick="if (event.target === this) this.close()">
    <div class="flex h-full flex-col items-center justify-center gap-[3vh] px-6 py-8">
        {{-- Black on white in both themes: scanners and projectors read it best --}}
        <img src="{{ route('go.qr', $short->code) }}" alt="QR code for {{ $short->display() }}" class="aspect-square w-[min(60vh,86vw)]">
        {{-- One line, as large as fits: monospace characters are about 0.6em wide --}}
        <p class="whitespace-nowrap text-center font-mono font-semibold leading-none tracking-tight" style="font-size: min(5rem, calc(92vw / {{ round(strlen($short->display()) * 0.62, 2) }}))">{{ $short->display() }}</p>
        <p class="text-center text-[clamp(0.95rem,2vw,1.5rem)] text-slate-600">Scan the code, or type the address. At {{ preg_replace('#^https?://#', '', route('go')) }}, enter <span class="font-mono font-semibold text-slate-900">{{ $short->code }}</span>.</p>
        <div class="flex gap-3">
            <button type="button" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-50"
                onclick="navigator.clipboard.writeText('{{ $short->url() }}').then(() => { this.textContent = 'Copied'; setTimeout(() => this.textContent = 'Copy link', 1500) })">Copy link</button>
            <button type="button" class="rounded-md px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100" onclick="this.closest('dialog').close()">Close</button>
        </div>
    </div>
</dialog>
