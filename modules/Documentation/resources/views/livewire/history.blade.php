<div class="space-y-6">
    <header class="space-y-1">
        <a href="{{ route('documentation.pages.show', $page) }}" class="text-sm text-gray-500 underline">← {{ $page->title }}</a>
        <h1 class="text-xl font-semibold">{{ __('documentation::docs.history.title', ['page' => $page->title]) }}</h1>
    </header>

    <div class="grid gap-6 lg:grid-cols-3">
        <ol class="divide-y divide-gray-100 rounded-lg bg-white px-4 text-sm ring-1 ring-gray-200">
            @foreach ($versions as $item)
                <li wire:key="version-{{ $item->id }}" @class(['py-2', 'font-semibold' => $selected?->version === $item->version])>
                    <a href="{{ route('documentation.pages.history', ['page' => $page, 'version' => $item->version]) }}" class="text-blue-700 underline">{{ __('documentation::docs.history.version', ['version' => $item->version]) }}</a>
                    <span class="block text-xs text-gray-500">{{ $item->author?->name ?? '—' }} · {{ $item->created_at->timezone($timezone)->format('Y-m-d H:i') }}</span>
                    @if ($item->version === $page->published_version) <span class="block text-xs font-semibold text-green-700">{{ __('documentation::docs.publishing.published') }}</span> @endif
                    @if ($item->approver) <span class="block text-xs text-gray-500">{{ __('documentation::docs.publishing.approved_by', ['name' => $item->approver->name, 'date' => $item->approved_at?->timezone($timezone)->format('Y-m-d')]) }}</span> @endif
                    @if ($item->summary) <span class="block text-xs text-gray-600">{{ $item->summary }}</span> @endif
                </li>
            @endforeach
        </ol>

        <section aria-labelledby="diff-heading" class="space-y-2 lg:col-span-2">
            @if ($selected)
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 id="diff-heading" class="text-sm font-semibold">{{ $previous ? __('documentation::docs.history.compared', ['version' => $selected->version, 'previous' => $previous->version]) : __('documentation::docs.history.first') }}</h2>
                    @if ($canEdit && $selected->version !== $page->version)
                        <button type="button" wire:click="restore({{ $selected->version }})" wire:confirm="{{ __('documentation::docs.history.restore_confirm', ['version' => $selected->version]) }}" class="rounded-md bg-white px-3 py-1.5 text-sm font-semibold ring-1 ring-gray-300">{{ __('documentation::docs.history.restore') }}</button>
                    @endif
                </div>
                @if ($previous && $previous->title !== $selected->title)
                    <p class="text-sm">{{ __('documentation::docs.history.renamed', ['from' => $previous->title, 'to' => $selected->title]) }}</p>
                @endif
                <pre class="overflow-x-auto rounded-lg bg-white p-3 font-mono text-xs ring-1 ring-gray-200">@foreach ($diff as $row)<span @class(['block whitespace-pre-wrap', 'bg-green-50 text-green-900' => $row['type'] === 'added', 'bg-red-50 text-red-900 line-through' => $row['type'] === 'removed'])>{{ ['added' => '+ ', 'removed' => '- ', 'same' => '  '][$row['type']] }}{{ $row['line'] }}</span>@endforeach</pre>
            @endif
        </section>
    </div>
</div>
