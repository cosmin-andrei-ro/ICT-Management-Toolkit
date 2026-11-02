<x-core::printable :title="$page->displayTitle()" :pdf="$pdf" :styles="$styles ?? ''" :back-url="route('documentation.pages.show', $page)" :back-label="'← '.$page->displayTitle()" :pdf-url="$pdfAvailable ? route('documentation.pages.pdf', $page) : null">
    <main class="mx-auto max-w-4xl space-y-6 bg-white p-8 text-sm print:max-w-none print:p-0">
        <header class="space-y-1 border-b border-gray-300 pb-4">
            <p class="text-gray-600">{{ $organization?->name }} · {{ $page->space->name }}</p>
            <h1 class="text-2xl font-semibold">{{ $page->displayTitle() }}</h1>
            <p class="text-gray-600">
                {{ __('documentation::docs.publishing.published_line', ['version' => $page->published_version, 'name' => $record?->author?->name ?? '—', 'date' => $page->published_at?->timezone($timezone)->format('Y-m-d')]) }}
                @if ($record?->approver) · {{ __('documentation::docs.publishing.approved_by', ['name' => $record->approver->name, 'date' => $record->approved_at?->timezone($timezone)->format('Y-m-d')]) }} @endif
                @if ($page->review_on) · {{ __('documentation::docs.publishing.review_on', ['date' => $page->review_on->toDateString()]) }} @endif
            </p>
            <p class="text-gray-600">{{ __('documentation::docs.publishing.printed', ['date' => now()->timezone($timezone)->format('Y-m-d H:i')]) }}</p>
        </header>
        {{-- Markdown::toSafeHtml(): raw HTML stripped, unsafe links dropped (CLAUDE.md §6). --}}
        <div class="markdown-content">{!! $page->publishedHtml() !!}</div>
    </main>
</x-core::printable>
