<div class="space-y-6" @if ($preparing) wire:poll.3s @endif>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold">{{ __('itam::licenses.title') }}</h1>
            <p class="text-sm text-gray-600">{{ __('itam::licenses.ui.intro') }}</p>
        </div>
        @can('create', \Modules\Itam\Domain\Models\License::class)
            <div class="flex flex-wrap gap-2">
                <button type="button" wire:click="export('xlsx')" wire:loading.attr="disabled" class="rounded-md bg-white px-4 py-2 text-sm font-semibold ring-1 ring-gray-300">{{ __('itam::licenses.ui.export') }}</button>
                <a href="{{ route('itam.assets.import', ['kind' => 'licenses']) }}" class="rounded-md bg-white px-4 py-2 text-sm font-semibold ring-1 ring-gray-300">{{ __('itam::licenses.ui.import') }}</a>
                <a href="{{ route('itam.licenses.create') }}" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white">{{ __('itam::licenses.ui.new') }}</a>
            </div>
        @endcan
    </div>

    @if ($exports->isNotEmpty())
        <ul class="divide-y divide-gray-100 rounded-lg bg-white px-4 text-sm ring-1 ring-gray-200">
            @foreach ($exports as $export)
                <li wire:key="export-{{ $export->id }}" class="flex flex-wrap items-center justify-between gap-2 py-2">
                    <span>{{ __('itam::licenses.ui.export_of', ['date' => $export->created_at?->timezone($timezone)->format('Y-m-d H:i')]) }}</span>
                    @if ($export->isDownloadable())
                        <span class="text-gray-500">
                            <a href="{{ route('core.exports.download', $export) }}" class="font-medium text-blue-700 underline">{{ __('core::exports.download') }}</a>
                            · {{ __('core::exports.expires', ['date' => $export->expires_at?->timezone($timezone)->format('Y-m-d H:i')]) }}
                        </span>
                    @else
                        <span class="text-gray-500">{{ $export->status->label() }}</span>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif

    <div class="flex flex-wrap items-center gap-4">
        <input type="search" wire:model.live.debounce.400ms="search" maxlength="200" placeholder="{{ __('itam::licenses.ui.search') }}" aria-label="{{ __('itam::licenses.ui.search') }}" class="w-72 rounded-md text-sm">
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model.live="expired" class="rounded border-gray-300"> {{ __('itam::licenses.ui.show_expired') }}</label>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model.live="over" class="rounded border-gray-300"> {{ __('itam::licenses.ui.only_over') }}</label>
    </div>

    <div class="space-y-4">
        @forelse ($products as $compliance)
            <section wire:key="product-{{ $compliance->product->id }}" class="rounded-lg bg-white ring-1 ring-gray-200">
                <header class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 px-4 py-3">
                    <h2 class="font-semibold">{{ $compliance->product->name }} @if ($compliance->product->publisher)<span class="text-sm font-normal text-gray-500">· {{ $compliance->product->publisher }}</span>@endif</h2>
                    <p class="flex items-center gap-2 text-sm">
                        @if ($compliance->purchased === null)
                            {{ __('itam::licenses.ui.used_unlimited', ['used' => $compliance->used]) }}
                        @else
                            {{ __('itam::licenses.ui.used', ['used' => $compliance->used, 'bought' => $compliance->purchased]) }}
                        @endif
                        @if ($compliance->isOverAllocated())
                            <span class="rounded bg-red-50 px-1.5 py-0.5 text-xs font-medium text-red-800">{{ __('itam::licenses.ui.over', ['count' => $compliance->used - $compliance->purchased]) }}</span>
                        @endif
                    </p>
                </header>
                <ul class="divide-y divide-gray-100 text-sm">
                    @foreach ($compliance->licenses as $license)
                        <li wire:key="license-{{ $license->id }}" class="flex flex-wrap items-center justify-between gap-2 px-4 py-2">
                            <a href="{{ route('itam.licenses.show', $license) }}" class="text-blue-700 underline">{{ $license->name }}</a>
                            <span class="flex items-center gap-3 text-xs text-gray-500">
                                <span>{{ $license->model->label() }}</span>
                                <span>{{ $license->assignments_count }}{{ $license->quantity !== null ? ' / '.$license->quantity : '' }}</span>
                                @if ($license->isExpiredOn($today))
                                    <span class="rounded bg-gray-100 px-1.5 py-0.5 text-gray-700">{{ __('itam::licenses.ui.expired') }}</span>
                                @else
                                    <span>{{ $license->expires_on?->toDateString() ?? __('itam::licenses.ui.perpetual') }}</span>
                                @endif
                            </span>
                        </li>
                    @endforeach
                </ul>
            </section>
        @empty
            <p class="rounded-lg bg-white p-6 text-center text-sm text-gray-500 ring-1 ring-gray-200">{{ __('itam::licenses.ui.empty') }}</p>
        @endforelse
    </div>
</div>
