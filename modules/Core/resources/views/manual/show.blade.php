<x-core::layouts.manual :title="$page->title" :contents="$contents" :current="$page->slug">
    <p class="text-xs font-semibold uppercase text-gray-500">{{ $page->audience->label() }}</p>
    <h1 class="mt-1 text-2xl font-semibold">{{ $page->title }}</h1>

    {{-- Rendered by ManualPage::html(): raw HTML stripped, unsafe links dropped (CLAUDE.md §6). --}}
    <article class="manual-content mt-6">
        {!! $page->html() !!}
    </article>

    <nav class="mt-10 flex justify-between border-t border-gray-200 pt-4 text-sm" aria-label="{{ __('core::manual.pagination') }}">
        @if ($previous)
            <a href="{{ route('manual.show', $previous->slug) }}" class="text-blue-700 underline">← {{ $previous->title }}</a>
        @else
            <span></span>
        @endif
        @if ($next)
            <a href="{{ route('manual.show', $next->slug) }}" class="text-blue-700 underline">{{ $next->title }} →</a>
        @endif
    </nav>
</x-core::layouts.manual>
