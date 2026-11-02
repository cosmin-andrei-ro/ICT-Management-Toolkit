<div class="space-y-6">
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div class="space-y-1">
            @can('viewAny', \Modules\Risk\Domain\Models\Risk::class)
                <a href="{{ route('risk.index') }}" class="text-sm text-gray-500 underline">{{ __('risk::risks.ui.back') }}</a>
            @else
                <a href="{{ route('risk.mine') }}" class="text-sm text-gray-500 underline">← {{ __('risk::risks.mine.title') }}</a>
            @endcan
            <h1 class="flex flex-wrap items-center gap-2 text-xl font-semibold">{{ $risk->code() }} · {{ $risk->title }} <x-risk::level :level="$level" :score="$score" /></h1>
            <p class="text-xs text-gray-500">{{ $risk->category->label() }} · {{ $risk->status->label() }} · {{ __('risk::risks.fields.owner') }}: {{ $risk->owner?->name ?? '—' }} · {{ __('risk::risks.fields.next_review') }}: {{ $risk->next_review_on?->toDateString() ?? '—' }}</p>
        </div>
        @can('update', $risk)
            <a href="{{ route('risk.risks.edit', $risk) }}" class="rounded-md bg-white px-3 py-1.5 text-sm font-semibold ring-1 ring-gray-300">{{ __('risk::risks.ui.edit') }}</a>
        @endcan
    </header>

    @if ($risk->description) <p class="whitespace-pre-line rounded-lg bg-white p-4 text-sm ring-1 ring-gray-200">{{ $risk->description }}</p> @endif

    <section aria-labelledby="assets-heading" class="space-y-3 rounded-lg bg-white p-4 ring-1 ring-gray-200">
        <h2 id="assets-heading" class="text-sm font-semibold">{{ __('risk::risks.ui.concerns') }}</h2>
        <ul class="space-y-1 text-sm">
            @forelse ($linked as $asset)
                <li wire:key="asset-{{ $asset->type->value }}-{{ $asset->id }}" class="flex flex-wrap items-center justify-between gap-2">
                    <span>
                        <span class="text-xs text-gray-500">{{ $asset->type->label() }}:</span>
                        @if ($asset->url) <a href="{{ $asset->url }}" class="text-blue-700 underline">{{ $asset->name }}</a> @else {{ $asset->name }} @endif
                        @if ($asset->detail) <span class="text-xs text-gray-500">{{ $asset->detail }}</span> @endif
                    </span>
                    @if ($assessable) <button type="button" wire:click="removeAsset('{{ $asset->type->value }}', '{{ $asset->id }}')" class="text-xs text-red-700 underline">{{ __('risk::risks.ui.remove') }}</button> @endif
                </li>
            @empty
                <li class="text-gray-500">{{ __('risk::risks.ui.no_assets') }}</li>
            @endforelse
        </ul>
        @if ($assessable)
            <div class="flex flex-wrap gap-2">
                <label for="assetType" class="sr-only">{{ __('risk::risks.ui.asset_type') }}</label>
                <select id="assetType" wire:model.live="assetType" class="block text-sm">
                    @foreach ($assetTypes as $type) <option value="{{ $type->value }}" wire:key="type-{{ $type->value }}">{{ $type->label() }}</option> @endforeach
                </select>
                <input type="search" wire:model.live.debounce.300ms="assetSearch" maxlength="100" placeholder="{{ __('risk::risks.ui.find_asset') }}" aria-label="{{ __('risk::risks.ui.find_asset') }}" class="block min-w-64 flex-1 text-sm">
            </div>
            @error('assetSearch') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            @if ($candidates !== [])
                <ul class="divide-y divide-gray-100 rounded-md text-sm ring-1 ring-gray-200">
                    @foreach ($candidates as $candidate)
                        <li wire:key="candidate-{{ $candidate->id }}" class="flex items-center justify-between gap-2 px-2 py-1.5">
                            <span>{{ $candidate->name }} @if ($candidate->detail) <span class="text-xs text-gray-500">{{ $candidate->detail }}</span> @endif</span>
                            <button type="button" wire:click="addAsset('{{ $candidate->type->value }}', '{{ $candidate->id }}')" class="text-xs text-blue-700 underline">{{ __('risk::risks.ui.link') }}</button>
                        </li>
                    @endforeach
                </ul>
            @endif
        @endif
    </section>

    <form wire:submit="assess" class="space-y-5 rounded-lg bg-white p-4 ring-1 ring-gray-200" novalidate>
        <h2 class="text-sm font-semibold">{{ __('risk::risks.ui.assessment') }}</h2>
        <div class="grid gap-6 md:grid-cols-2">
            @foreach (['threats' => $threats, 'vulnerabilities' => $vulnerabilities] as $kind => $entries)
                <fieldset wire:key="fieldset-{{ $kind }}" class="space-y-1">
                    <legend class="text-sm font-medium">{{ __('risk::risks.ui.'.$kind) }}</legend>
                    <div class="max-h-64 space-y-1 overflow-y-auto rounded-md p-2 ring-1 ring-gray-200">
                        @foreach ($entries as $entry)
                            <label wire:key="entry-{{ $entry->id }}" class="flex items-start gap-2 text-sm" @if ($entry->description) title="{{ $entry->description }}" @endif>
                                <input type="checkbox" wire:model="entryIds" value="{{ $entry->id }}" class="mt-1" @disabled(! $assessable)> {{ $entry->name }}
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            @endforeach
        </div>
        @error('entryIds') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        <div>
            <label for="controls" class="block text-sm font-medium">{{ __('risk::risks.fields.existing_controls') }}</label>
            <textarea id="controls" wire:model="controls" rows="3" maxlength="10000" placeholder="{{ __('risk::risks.examples.controls') }}" class="mt-1 block w-full" @disabled(! $assessable)></textarea>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            @foreach (['likelihood' => $settings->likelihood_levels, 'impact' => $settings->impact_levels] as $axis => $scale)
                <div wire:key="axis-{{ $axis }}">
                    <label for="{{ $axis }}" class="block text-sm font-medium">{{ __('risk::risks.fields.'.$axis) }}</label>
                    <select id="{{ $axis }}" wire:model="{{ $axis }}" class="mt-1 block w-full" @disabled(! $assessable)>
                        <option value="">{{ __('risk::risks.ui.not_assessed') }}</option>
                        @foreach ($scale as $index => $levelInfo) <option value="{{ $index + 1 }}" wire:key="{{ $axis }}-{{ $index }}">{{ $index + 1 }} - {{ $levelInfo['label'] }}</option> @endforeach
                    </select>
                    @error($axis) <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    <ul class="mt-1 space-y-0.5 text-xs text-gray-500">
                        @foreach ($scale as $index => $levelInfo) <li wire:key="{{ $axis }}-help-{{ $index }}"><span class="font-medium">{{ $index + 1 }}</span> {{ $levelInfo['description'] }}</li> @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
        @if ($assessable)
            <button type="submit" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white">{{ __('risk::risks.ui.save_assessment') }}</button>
        @endif
    </form>
</div>
