<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="space-y-1">
            <a href="{{ route('risk.index') }}" class="text-sm text-gray-500 underline">{{ __('risk::risks.ui.back') }}</a>
            <h1 class="text-xl font-semibold">{{ __('risk::suggestions.title') }}</h1>
            <p class="text-sm text-gray-600">{{ __('risk::suggestions.ui.intro') }}</p>
        </div>
        <button type="button" wire:click="refreshNow" wire:loading.attr="disabled" class="rounded-md bg-white px-4 py-2 text-sm font-semibold ring-1 ring-gray-300">{{ __('risk::suggestions.ui.refresh') }}</button>
    </div>
    @if ($notice !== '') <p class="rounded-md bg-gray-50 p-3 text-sm" role="status">{{ $notice }}</p> @endif

    <div class="flex flex-wrap items-end gap-3">
        <div>
            <label for="status" class="block text-xs text-gray-600">{{ __('risk::risks.fields.status') }}</label>
            <select id="status" wire:model.live="status" class="mt-1 block">
                @foreach ($statuses as $option) <option value="{{ $option->value }}" wire:key="status-{{ $option->value }}">{{ $option->label() }} ({{ $counts[$option->value] ?? 0 }})</option> @endforeach
            </select>
        </div>
        <div>
            <label for="source" class="block text-xs text-gray-600">{{ __('risk::suggestions.ui.source') }}</label>
            <select id="source" wire:model.live="source" class="mt-1 block">
                <option value="">{{ __('risk::risks.ui.all') }}</option>
                @foreach ($sources as $key => $label) <option value="{{ $key }}" wire:key="source-{{ $key }}">{{ $label }}</option> @endforeach
            </select>
        </div>
    </div>

    <ul class="space-y-3">
        @forelse ($suggestions as $suggestion)
            <li wire:key="suggestion-{{ $suggestion->id }}" class="space-y-2 rounded-lg bg-white p-4 text-sm ring-1 ring-gray-200">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div class="space-y-1">
                        <p class="text-xs text-gray-500">{{ $suggestion->source_label }} · {{ $suggestion->category->label() }} · {{ __('risk::suggestions.ui.seen', ['first' => $suggestion->first_seen_at->toDateString(), 'last' => $suggestion->last_seen_at->toDateString()]) }}</p>
                        <h2 class="font-semibold">{{ $suggestion->title }}</h2>
                        <p class="text-gray-700">{{ $suggestion->description }}</p>
                    </div>
                    @if ($suggestion->evidence_url && preg_match('#^https?://#', $suggestion->evidence_url)) <a href="{{ $suggestion->evidence_url }}" class="text-xs text-blue-700 underline">{{ __('risk::suggestions.ui.evidence') }}</a> @endif
                </div>
                <p class="text-xs text-gray-600">
                    @foreach ($linked[$suggestion->id] ?? [] as $asset) <span class="mr-2">{{ $asset->type->label() }}: {{ $asset->name }}</span> @endforeach
                    @if ($suggestion->threats !== [] || $suggestion->vulnerabilities !== []) <span class="block">{{ implode(', ', [...$suggestion->threats, ...$suggestion->vulnerabilities]) }}</span> @endif
                    @if ($suggestion->likelihood || $suggestion->impact) <span class="block">{{ __('risk::suggestions.ui.scores', ['likelihood' => $suggestion->likelihood ?? '—', 'impact' => $suggestion->impact ?? '—']) }}</span> @endif
                </p>
                @if ($suggestion->status === \Modules\Risk\Domain\Enums\SuggestionStatus::Open)
                    <div class="grid gap-3 border-t border-gray-100 pt-3 md:grid-cols-2">
                        <div class="space-y-1">
                            <label for="owner-{{ $suggestion->id }}" class="block text-xs font-medium">{{ __('risk::risks.fields.owner') }}</label>
                            <div class="flex gap-2">
                                <select id="owner-{{ $suggestion->id }}" wire:model="owners.{{ $suggestion->id }}" class="block w-full">
                                    <option value="">{{ $suggestion->owner_id ? __('risk::suggestions.ui.suggested_owner', ['name' => $people->firstWhere('id', $suggestion->owner_id)?->name ?? '—']) : '—' }}</option>
                                    @foreach ($people as $person) <option value="{{ $person->id }}" wire:key="owner-{{ $suggestion->id }}-{{ $person->id }}">{{ $person->name }}</option> @endforeach
                                </select>
                                <button type="button" wire:click="convert('{{ $suggestion->id }}')" class="whitespace-nowrap rounded-md bg-gray-900 px-3 py-1.5 text-sm font-semibold text-white">{{ __('risk::suggestions.ui.convert') }}</button>
                            </div>
                            @error('owners.'.$suggestion->id) <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div class="space-y-1">
                            <label for="reason-{{ $suggestion->id }}" class="block text-xs font-medium">{{ __('risk::suggestions.ui.reason') }}</label>
                            <div class="flex gap-2">
                                <input id="reason-{{ $suggestion->id }}" type="text" wire:model="reasons.{{ $suggestion->id }}" maxlength="2000" placeholder="{{ __('risk::suggestions.ui.reason_example') }}" class="block w-full">
                                <button type="button" wire:click="dismiss('{{ $suggestion->id }}')" class="whitespace-nowrap rounded-md bg-white px-3 py-1.5 text-sm font-semibold text-red-700 ring-1 ring-gray-300">{{ __('risk::suggestions.ui.dismiss') }}</button>
                            </div>
                            @error('reasons.'.$suggestion->id) <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                @elseif ($suggestion->status === \Modules\Risk\Domain\Enums\SuggestionStatus::Converted && $suggestion->risk)
                    <p class="text-xs">{{ __('risk::suggestions.ui.converted_to') }} <a href="{{ route('risk.risks.show', $suggestion->risk) }}" class="text-blue-700 underline">{{ $suggestion->risk->code() }}</a> · {{ $suggestion->decider?->name }}</p>
                @elseif ($suggestion->status === \Modules\Risk\Domain\Enums\SuggestionStatus::Dismissed)
                    <p class="text-xs text-gray-600">{{ __('risk::suggestions.ui.dismissed_by', ['name' => $suggestion->decider?->name ?? '—']) }} {{ $suggestion->dismissal_reason }}</p>
                @endif
            </li>
        @empty
            <li class="rounded-lg bg-white p-4 text-sm text-gray-500 ring-1 ring-gray-200">{{ __('risk::suggestions.ui.none') }}</li>
        @endforelse
    </ul>
    {{ $suggestions->links() }}
</div>
