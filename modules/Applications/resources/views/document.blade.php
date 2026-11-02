@if ($pdf)
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $application->name }} · {{ __('applications::document.title') }}</title>
    {{-- The application's own compiled stylesheet (a build artefact, not user content): the PDF service fetches nothing. --}}
    <style>{!! $styles ?? '' !!}</style>
</head>
<body class="bg-white font-sans text-gray-900 antialiased">
    @include('applications::document-body')
</body>
</html>
@else
<x-core::layouts.base :title="$application->name.' · '.__('applications::document.title')">
    <div class="mx-auto flex max-w-4xl flex-wrap items-center justify-between gap-3 px-8 pt-6 print:hidden">
        <a href="{{ route('applications.sheets.show', $sheet) }}" class="text-sm text-gray-500 underline">← {{ $application->name }}</a>
        <div class="flex flex-wrap gap-2">
            @if ($pdfAvailable)
                <a href="{{ route('applications.sheets.pdf', $sheet) }}" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white">{{ __('applications::document.download_pdf') }}</a>
            @endif
            <button type="button" data-print class="rounded-md bg-white px-4 py-2 text-sm font-semibold ring-1 ring-gray-300">{{ __('applications::document.print') }}</button>
        </div>
    </div>
    @if (session('status')) <p class="mx-auto mt-3 max-w-4xl rounded-md bg-amber-50 p-3 text-sm text-amber-900 print:hidden" role="status">{{ session('status') }}</p> @endif
    @include('applications::document-body')
</x-core::layouts.base>
@endif
