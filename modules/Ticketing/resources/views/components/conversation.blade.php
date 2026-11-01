@props(['comments', 'attachments', 'showInternal' => false])

<section aria-labelledby="conversation-heading" class="space-y-4">
    <h2 id="conversation-heading" class="text-sm font-semibold text-gray-900">{{ __('ticketing::ui.conversation') }}</h2>

    @forelse ($comments as $comment)
        <article wire:key="comment-{{ $comment->id }}" @class([
            'rounded-lg p-4 ring-1',
            'bg-amber-50 ring-amber-200' => $comment->is_internal,
            'bg-white ring-gray-200' => ! $comment->is_internal,
        ])>
            <header class="mb-2 flex items-center justify-between text-xs text-gray-500">
                <span class="font-medium text-gray-700">{{ $comment->author?->name ?? __('ticketing::ui.system') }}</span>
                <span>
                    @if ($showInternal && $comment->is_internal)
                        <span class="mr-2 font-semibold uppercase text-amber-700">{{ __('ticketing::ui.internal_note') }}</span>
                    @endif
                    <time datetime="{{ $comment->created_at?->toIso8601String() }}">{{ $comment->created_at?->diffForHumans() }}</time>
                </span>
            </header>
            {{-- Escaped; line breaks kept with CSS, never with HTML from the user. --}}
            <p class="whitespace-pre-line text-sm text-gray-800">{{ $comment->body }}</p>
        </article>
    @empty
        <p class="text-sm text-gray-500">{{ __('ticketing::ui.no_comments') }}</p>
    @endforelse

    @if ($attachments->isNotEmpty())
        <ul class="divide-y divide-gray-100 rounded-lg bg-white ring-1 ring-gray-200">
            @foreach ($attachments as $attachment)
                <li wire:key="attachment-{{ $attachment->id }}" class="flex items-center justify-between px-4 py-2 text-sm">
                    <a href="{{ route('core.attachments.download', $attachment) }}" class="text-blue-700 underline">{{ $attachment->original_name }}</a>
                    <span class="text-xs text-gray-500">
                        @if ($showInternal && $attachment->is_internal)
                            <span class="mr-2 font-semibold uppercase text-amber-700">{{ __('ticketing::ui.internal_note') }}</span>
                        @endif
                        {{ \Illuminate\Support\Number::fileSize($attachment->size) }}
                    </span>
                </li>
            @endforeach
        </ul>
    @endif
</section>
