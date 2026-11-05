<div class="space-y-6">
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div class="space-y-1">
            <a href="{{ route('itam.licenses.index') }}" class="text-sm text-gray-500 underline">{{ __('itam::licenses.ui.back') }}</a>
            <h1 class="text-xl font-semibold">{{ $license->product->name }} · {{ $license->name }}</h1>
            <div class="flex flex-wrap items-center gap-2 text-xs">
                <span class="rounded bg-gray-100 px-2 py-0.5">{{ $license->model->label() }}</span>
                <span>{{ $seats->count() }}{{ $license->quantity !== null ? ' / '.$license->quantity : '' }} {{ __('itam::licenses.fields.seats') }}</span>
                @if ($license->quantity !== null && $seats->count() > $license->quantity)
                    <span class="rounded bg-red-50 px-1.5 py-0.5 font-medium text-red-800">{{ __('itam::licenses.ui.over', ['count' => $seats->count() - $license->quantity]) }}</span>
                @endif
                @if ($expired) <span class="rounded bg-gray-100 px-1.5 py-0.5 text-gray-700">{{ __('itam::licenses.ui.expired') }}</span> @endif
            </div>
        </div>
        @can('update', $license)
            <div class="flex gap-2">
                <a href="{{ route('itam.licenses.edit', $license) }}" class="rounded-md bg-white px-3 py-1.5 text-sm font-semibold ring-1 ring-gray-300">{{ __('itam::licenses.ui.edit') }}</a>
                <button type="button" wire:click="delete" wire:confirm="{{ __('itam::licenses.ui.delete_confirm') }}" class="rounded-md bg-white px-3 py-1.5 text-sm font-semibold text-red-700 ring-1 ring-gray-300">{{ __('itam::licenses.ui.delete') }}</button>
            </div>
        @endcan
    </header>

    <div class="grid gap-6 lg:grid-cols-3">
        <section aria-labelledby="seats-heading" class="space-y-3 rounded-lg bg-white p-4 ring-1 ring-gray-200 lg:col-span-2">
            <h2 id="seats-heading" class="text-sm font-semibold">{{ __($license->model->forDevices() ? 'itam::licenses.ui.devices' : 'itam::licenses.ui.seats') }}</h2>
            <ul class="divide-y divide-gray-100 text-sm">
                @forelse ($seats as $seat)
                    <li wire:key="seat-{{ $seat->id }}" class="flex items-center justify-between gap-2 py-1.5">
                        <span>
                            @if ($seat->asset)
                                <a href="{{ route('itam.assets.show', $seat->asset) }}" class="font-mono text-blue-700 underline">{{ $seat->asset->asset_tag }}</a> {{ $seat->asset->displayName() }}
                            @else
                                {{ $seat->user?->name }} <span class="text-xs text-gray-500">{{ $seat->user?->email }}</span>
                            @endif
                            <span class="text-xs text-gray-500">· {{ $seat->assigned_at->toDateString() }}</span>
                        </span>
                        @can('assign', $license)
                            <button type="button" wire:click="releaseSeat('{{ $seat->id }}')" class="text-xs text-red-700 underline">{{ __('itam::licenses.ui.release') }}</button>
                        @endcan
                    </li>
                @empty
                    <li class="py-1.5 text-gray-500">{{ __('itam::licenses.ui.no_seats') }}</li>
                @endforelse
            </ul>
            @can('assign', $license)
                <div class="space-y-2 border-t border-gray-100 pt-3">
                    <input type="search" wire:model.live.debounce.300ms="seatSearch" maxlength="100" placeholder="{{ __($license->model->forDevices() ? 'itam::licenses.ui.find_device' : 'itam::licenses.ui.find_person') }}" aria-label="{{ __($license->model->forDevices() ? 'itam::licenses.ui.find_device' : 'itam::licenses.ui.find_person') }}" class="block w-full text-sm">
                    @error('seatSearch') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                    @if ($candidates->isNotEmpty())
                        <ul class="divide-y divide-gray-100 rounded-md text-sm ring-1 ring-gray-200">
                            @foreach ($candidates as $candidate)
                                <li wire:key="candidate-{{ $candidate->id }}" class="flex items-center justify-between gap-2 px-2 py-1.5">
                                    @if ($license->model->forDevices())
                                        <span><span class="font-mono">{{ $candidate->asset_tag }}</span> {{ $candidate->displayName() }}</span>
                                        <button type="button" wire:click="giveDevice('{{ $candidate->id }}')" class="text-xs text-blue-700 underline">{{ __('itam::licenses.ui.give') }}</button>
                                    @else
                                        <span>{{ $candidate->name }} <span class="text-xs text-gray-500">{{ $candidate->email }}</span></span>
                                        <button type="button" wire:click="givePerson('{{ $candidate->id }}')" class="text-xs text-blue-700 underline">{{ __('itam::licenses.ui.give') }}</button>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endcan
        </section>

        <section aria-labelledby="key-heading" class="space-y-3 rounded-lg bg-white p-4 ring-1 ring-gray-200">
            <h2 id="key-heading" class="text-sm font-semibold">{{ __('itam::licenses.ui.key') }}</h2>
            @if (! $license->hasKey())
                <p class="text-sm text-gray-500">{{ __('itam::licenses.ui.no_key') }}</p>
            @elseif ($revealedKey !== null)
                <p class="break-all rounded-md bg-gray-50 p-2 font-mono text-sm">{{ $revealedKey }}</p>
                <button type="button" wire:click="hideKey" class="rounded-md bg-white px-3 py-1.5 text-sm font-semibold ring-1 ring-gray-300">{{ __('itam::licenses.ui.hide') }}</button>
            @else
                <p class="text-sm text-gray-600">{{ __('itam::licenses.ui.key_stored') }}</p>
                @can('revealKey', $license)
                    <form wire:submit="revealKey" class="space-y-2">
                        <p class="text-xs text-gray-500">{{ __('itam::licenses.ui.reveal_help') }}</p>
                        <input type="text" wire:model="revealCode" inputmode="numeric" autocomplete="one-time-code" maxlength="32" aria-label="{{ __('itam::licenses.ui.reveal_help') }}" class="block w-40 text-sm">
                        @error('revealCode') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                        <button type="submit" class="rounded-md bg-white px-3 py-1.5 text-sm font-semibold ring-1 ring-gray-300">{{ __('itam::licenses.ui.reveal') }}</button>
                    </form>
                @endcan
            @endif
        </section>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <section aria-labelledby="details-heading" class="space-y-3 rounded-lg bg-white p-4 ring-1 ring-gray-200">
            <h2 id="details-heading" class="text-sm font-semibold">{{ __('itam::licenses.ui.details') }}</h2>
            <dl class="grid gap-x-4 gap-y-2 text-sm sm:grid-cols-2">
                <div><dt class="text-xs text-gray-500">{{ __('itam::licenses.fields.publisher') }}</dt><dd>{{ $license->product->publisher ?? '—' }}</dd></div>
                <div><dt class="text-xs text-gray-500">{{ __('itam::licenses.fields.supplier') }}</dt><dd>{{ $license->supplier?->name ?? '—' }}</dd></div>
                @can('viewAny', \Modules\Core\Domain\Models\Contract::class)
                    <div><dt class="text-xs text-gray-500">{{ __('itam::licenses.fields.contract') }}</dt><dd>@if ($license->contract)<a href="{{ route('core.contracts.show', $license->contract) }}" class="text-blue-700 underline">{{ $license->contract->title }}</a>@else — @endif</dd></div>
                @endcan
                <div><dt class="text-xs text-gray-500">{{ __('itam::licenses.fields.purchase_date') }}</dt><dd>{{ $license->purchase_date?->toDateString() ?? '—' }}</dd></div>
                <div><dt class="text-xs text-gray-500">{{ __('itam::licenses.fields.cost') }}</dt><dd>{{ $license->cost !== null ? \Illuminate\Support\Number::format((float) $license->cost, 2).' '.$license->currency : '—' }}</dd></div>
                <div><dt class="text-xs text-gray-500">{{ __('itam::licenses.fields.starts_on') }}</dt><dd>{{ $license->starts_on?->toDateString() ?? '—' }}</dd></div>
                <div><dt class="text-xs text-gray-500">{{ __('itam::licenses.fields.expires_on') }}</dt><dd>{{ $license->expires_on?->toDateString() ?? __('itam::licenses.ui.perpetual') }}</dd></div>
            </dl>
            @if ($license->notes)
                <p class="whitespace-pre-line border-t border-gray-100 pt-2 text-sm text-gray-700">{{ $license->notes }}</p>
            @endif
        </section>

        <section aria-labelledby="documents-heading" class="space-y-3 rounded-lg bg-white p-4 ring-1 ring-gray-200">
            <h2 id="documents-heading" class="text-sm font-semibold">{{ __('itam::licenses.ui.documents') }}</h2>
            <ul class="divide-y divide-gray-100 text-sm">
                @forelse ($documents as $document)
                    <li wire:key="document-{{ $document->id }}" class="flex items-center justify-between gap-2 py-2">
                        <a href="{{ route('core.attachments.download', $document) }}" class="break-all text-blue-700 underline">{{ $document->original_name }}</a>
                        <span class="flex shrink-0 items-center gap-2 text-xs text-gray-500">
                            {{ \Illuminate\Support\Number::fileSize($document->size) }}
                            @can('update', $license)
                                <button type="button" wire:click="removeDocument('{{ $document->id }}')" wire:confirm="{{ __('itam::licenses.ui.remove_document_confirm') }}" class="text-red-700 underline">{{ __('itam::licenses.ui.remove_document') }}</button>
                            @endcan
                        </span>
                    </li>
                @empty
                    <li class="py-2 text-gray-500">{{ __('itam::licenses.ui.no_documents') }}</li>
                @endforelse
            </ul>
            @can('update', $license)
                <form wire:submit="uploadDocuments" class="space-y-2">
                    <input type="file" wire:model="files" multiple class="block w-full text-sm" aria-label="{{ __('itam::licenses.ui.documents') }}">
                    @error('files') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                    @error('files.*') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                    <button type="submit" wire:loading.attr="disabled" class="rounded-md bg-white px-3 py-1.5 text-sm font-semibold ring-1 ring-gray-300">{{ __('itam::licenses.ui.upload') }}</button>
                </form>
            @endcan
        </section>
    </div>

    <x-core::record-history :record="$license" />
</div>
