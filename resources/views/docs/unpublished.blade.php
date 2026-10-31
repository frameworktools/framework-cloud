@extends('docs.layout')

@section('full')
    <div class="mx-auto max-w-2xl px-6 py-24 text-center">
        <img src="/images/logo.png" alt="" class="mx-auto h-14 w-auto">
        <h1 class="mt-8 text-3xl font-semibold tracking-tight text-slate-900 dark:text-white">Documentation is not published yet</h1>
        <p class="mt-4 text-lg text-slate-600 dark:text-slate-400">
            The documentation for {{ \App\Support\Docs\Products::NAMES[$product] }} will appear here after it is published.
        </p>
        <p class="mt-8 text-sm text-slate-500 dark:text-slate-400">
            In the meantime, read the <a href="{{ \App\Support\Docs\Products::url('r') }}" class="font-medium text-sky-600 hover:underline dark:text-sky-400">documentation for the R package</a>.
        </p>
    </div>
@endsection
