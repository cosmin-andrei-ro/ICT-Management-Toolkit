@php($title = $document === 'soa' ? __('audit::report.soa_title', ['framework' => $framework->name]) : __('audit::report.title'))
@if ($pdf)
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    {{-- The application's own compiled stylesheet (a build artefact, not user content): the PDF service fetches nothing. --}}
    <style>{!! $styles ?? '' !!}</style>
</head>
<body class="bg-white font-sans text-gray-900 antialiased">
    @include($document === 'soa' ? 'audit::report.soa' : 'audit::report.body')
</body>
</html>
@else
<x-core::layouts.base :title="$title">
    <div class="mx-auto flex max-w-4xl flex-wrap items-center justify-between gap-3 px-8 pt-6 print:hidden">
        <a href="{{ $document === 'soa' ? route('audit.frameworks.show', $framework) : route('audit.index') }}" class="text-sm text-gray-500 underline">{{ $document === 'soa' ? $framework->name : __('audit::compliance.ui.back') }}</a>
        <div class="flex flex-wrap gap-2">
            @if ($pdfAvailable) <a href="{{ $document === 'soa' ? route('audit.frameworks.soa.pdf', $framework) : route('audit.report.pdf') }}" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white">{{ __('audit::report.download_pdf') }}</a> @endif
            <button type="button" data-print class="rounded-md bg-white px-4 py-2 text-sm font-semibold ring-1 ring-gray-300">{{ __('audit::report.print') }}</button>
        </div>
    </div>
    @if (session('status')) <p class="mx-auto mt-3 max-w-4xl rounded-md bg-amber-50 p-3 text-sm text-amber-900 print:hidden" role="status">{{ session('status') }}</p> @endif
    @include($document === 'soa' ? 'audit::report.soa' : 'audit::report.body')
</x-core::layouts.base>
@endif
