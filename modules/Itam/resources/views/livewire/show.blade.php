<div class="space-y-6">
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div class="space-y-1">
            <a href="{{ route('itam.assets.index') }}" class="text-sm text-gray-500 underline">{{ __('itam::assets.ui.back') }}</a>
            <h1 class="text-xl font-semibold"><span class="font-mono">{{ $asset->asset_tag }}</span> · {{ $asset->displayName() }}</h1>
            <div class="flex flex-wrap items-center gap-2 text-xs">
                <x-itam::status :status="$asset->status" />
                <span class="rounded bg-gray-100 px-2 py-0.5">{{ $asset->category->name }}</span>
                @if ($asset->serial_number) <span class="font-mono text-gray-500">{{ $asset->serial_number }}</span> @endif
            </div>
        </div>
        @can('update', $asset)
            <div class="flex gap-2">
                <a href="{{ route('itam.assets.edit', $asset) }}" class="rounded-md bg-white px-3 py-1.5 text-sm font-semibold ring-1 ring-gray-300">{{ __('itam::assets.ui.edit') }}</a>
                <button type="button" wire:click="delete" wire:confirm="{{ __('itam::assets.ui.delete_confirm') }}" class="rounded-md bg-white px-3 py-1.5 text-sm font-semibold text-red-700 ring-1 ring-gray-300">{{ __('itam::assets.ui.delete') }}</button>
            </div>
        @endcan
    </header>

    <div class="grid gap-6 lg:grid-cols-3">
        <section aria-labelledby="holder-heading" class="space-y-3 rounded-lg bg-white p-4 ring-1 ring-gray-200">
            <h2 id="holder-heading" class="text-sm font-semibold">{{ __('itam::assets.ui.holder') }}</h2>
            <p class="text-sm">
                @if ($asset->holder)
                    <span class="font-medium">{{ $asset->holder->name }}</span>
                    @if ($heldSince)
                        <span class="text-gray-500">{{ __('itam::assets.ui.since', ['date' => $heldSince->toDateString()]) }}</span>
                    @endif
                @else
                    <span class="text-gray-500">{{ __('itam::assets.ui.nobody') }}</span>
                @endif
            </p>
            <p class="text-sm text-gray-600">{{ __('itam::assets.fields.location') }}: {{ $asset->location_id ? ($locations[$asset->location_id] ?? $asset->location?->name) : '—' }}</p>
            @if ($placement !== null)
                <p class="text-sm text-gray-600">{{ __('itam::assets.fields.rack') }}: <a href="{{ $placement->url }}" class="text-blue-700 underline">{{ $placement->title }}</a></p>
            @endif

            @can('assign', $asset)
                @if (in_array($asset->status, [\Modules\Itam\Domain\Enums\AssetStatus::Ordered, \Modules\Itam\Domain\Enums\AssetStatus::InStock, \Modules\Itam\Domain\Enums\AssetStatus::Deployed], true))
                    <form wire:submit="assignAsset" class="space-y-2 border-t border-gray-100 pt-3" novalidate>
                        <label for="holderSearch" class="block text-sm font-medium">{{ __($asset->holder ? 'itam::assets.ui.reassign' : 'itam::assets.ui.assign') }}</label>
                        <input id="holderSearch" type="search" wire:model.live.debounce.300ms="holderSearch" maxlength="100" placeholder="{{ __('itam::assets.ui.find_person') }}" class="block w-full text-sm">
                        @if ($people->isNotEmpty())
                            <ul class="divide-y divide-gray-100 rounded-md ring-1 ring-gray-200" role="listbox" aria-label="{{ __('itam::assets.ui.find_person') }}">
                                @foreach ($people as $person)
                                    <li wire:key="person-{{ $person->id }}">
                                        <label class="flex cursor-pointer items-center gap-2 px-2 py-1.5 text-sm hover:bg-gray-50">
                                            <input type="radio" wire:model="holderId" value="{{ $person->id }}">
                                            {{ $person->name }} <span class="text-xs text-gray-500">{{ $person->email }}</span>
                                        </label>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                        @error('holderId') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                        <select wire:model="assignLocationId" aria-label="{{ __('itam::assets.ui.location_optional') }}" class="block w-full text-sm">
                            <option value="">{{ __('itam::assets.ui.location_optional') }}</option>
                            @foreach ($locations as $id => $path)
                                <option value="{{ $id }}" wire:key="assign-location-{{ $id }}">{{ $path }}</option>
                            @endforeach
                        </select>
                        <input type="text" wire:model="assignNotes" maxlength="1000" placeholder="{{ __('itam::assets.ui.notes_optional') }}" aria-label="{{ __('itam::assets.ui.notes_optional') }}" class="block w-full text-sm">
                        <button type="submit" class="rounded-md bg-gray-900 px-3 py-1.5 text-sm font-semibold text-white">{{ __('itam::assets.ui.assign') }}</button>
                    </form>
                @endif
            @endcan
        </section>

        <section aria-labelledby="life-heading" class="space-y-3 rounded-lg bg-white p-4 ring-1 ring-gray-200">
            <h2 id="life-heading" class="text-sm font-semibold">{{ __('itam::assets.ui.life_cycle') }}</h2>
            @error('status') <p class="rounded-md bg-red-50 p-2 text-sm text-red-800" role="alert">{{ $message }}</p> @enderror

            @if ($asset->status === \Modules\Itam\Domain\Enums\AssetStatus::Disposed)
                <p class="text-sm">{{ __('itam::assets.ui.disposed', ['date' => $asset->disposed_on?->toDateString(), 'method' => $asset->disposal_method?->label()]) }}</p>
                <p class="text-sm text-gray-600">{{ __('itam::assets.fields.data_wiped') }}: {{ __('itam::assets.data_wiped.'.($asset->data_wiped === null ? '' : ($asset->data_wiped ? '1' : '0'))) }}</p>
                @if ($asset->disposal_notes) <p class="whitespace-pre-line text-sm text-gray-600">{{ $asset->disposal_notes }}</p> @endif
                <p class="text-xs text-gray-500">{{ __('itam::assets.ui.no_moves') }}</p>
            @else
                @if ($moves !== [] && auth()->user()->can('assign', $asset))
                    <div class="space-y-2">
                        <select wire:model="statusLocationId" aria-label="{{ __('itam::assets.ui.location_optional') }}" class="block w-full text-sm">
                            <option value="">{{ __('itam::assets.ui.location_optional') }}</option>
                            @foreach ($locations as $id => $path)
                                <option value="{{ $id }}" wire:key="status-location-{{ $id }}">{{ $path }}</option>
                            @endforeach
                        </select>
                        <input type="text" wire:model="statusNotes" maxlength="1000" placeholder="{{ __('itam::assets.ui.notes_optional') }}" aria-label="{{ __('itam::assets.ui.notes_optional') }}" class="block w-full text-sm">
                        <div class="flex flex-wrap gap-2">
                            @foreach ($moves as $move)
                                @if ($move->value !== 'retired' && $move->value !== 'lost' || auth()->user()->can('update', $asset))
                                    <button type="button" wire:key="move-{{ $move->value }}" wire:click="changeStatus('{{ $move->value }}')" class="rounded-md bg-white px-3 py-1.5 text-sm font-semibold ring-1 ring-gray-300">{{ __('itam::assets.moves.'.($move->value === 'deployed' && $asset->assigned_to_id === null ? 'in_service' : $move->value)) }}</button>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($canDispose)
                    @can('update', $asset)
                        <form wire:submit="dispose" class="space-y-2 border-t border-gray-100 pt-3" novalidate>
                            <p class="text-sm font-medium">{{ __('itam::assets.ui.dispose') }}</p>
                            <p class="text-xs text-gray-500">{{ __('itam::assets.ui.dispose_help') }}</p>
                            <div class="grid grid-cols-2 gap-2">
                                <input type="date" wire:model="disposedOn" aria-label="{{ __('itam::assets.fields.disposed_on') }}" class="block w-full text-sm">
                                <select wire:model="disposalMethod" aria-label="{{ __('itam::assets.fields.disposal_method') }}" class="block w-full text-sm">
                                    <option value="">{{ __('itam::assets.fields.disposal_method') }}…</option>
                                    @foreach ($disposalMethods as $method)
                                        <option value="{{ $method->value }}">{{ $method->label() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            @error('disposedOn') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                            @error('disposalMethod') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                            <select wire:model="dataWiped" aria-label="{{ __('itam::assets.fields.data_wiped') }}" class="block w-full text-sm">
                                @foreach (['', '1', '0'] as $option)
                                    <option value="{{ $option }}">{{ __('itam::assets.data_wiped.'.$option) }}</option>
                                @endforeach
                            </select>
                            <textarea wire:model="disposalNotes" rows="2" maxlength="5000" placeholder="{{ __('itam::assets.fields.disposal_notes') }}" aria-label="{{ __('itam::assets.fields.disposal_notes') }}" class="block w-full text-sm"></textarea>
                            <button type="submit" class="rounded-md bg-white px-3 py-1.5 text-sm font-semibold text-red-700 ring-1 ring-gray-300">{{ __('itam::assets.ui.dispose') }}</button>
                        </form>
                    @endcan
                @endif
            @endif
        </section>

        <section aria-labelledby="cmdb-heading" class="space-y-3 rounded-lg bg-white p-4 ring-1 ring-gray-200">
            <h2 id="cmdb-heading" class="text-sm font-semibold">{{ __('itam::assets.ui.configuration_item') }}</h2>
            @if (! $cmdbAvailable)
                <p class="text-sm text-gray-500">{{ __('itam::assets.ui.cmdb_off') }}</p>
            @else
                <ul class="space-y-1 text-sm">
                    @forelse ($linkedItems as $item)
                        <li wire:key="item-{{ $item->id }}" class="flex items-center justify-between gap-2">
                            <span><a href="{{ $item->url }}" class="text-blue-700 underline">{{ $item->name }}</a> <span class="text-xs text-gray-500">{{ $item->typeName }}</span></span>
                            @can('update', $asset)
                                <button type="button" wire:click="unlinkItem('{{ $item->id }}')" class="text-xs text-red-700 underline">{{ __('itam::assets.ui.unlink_item') }}</button>
                            @endcan
                        </li>
                    @empty
                        <li class="text-gray-500">{{ __('itam::assets.ui.no_item') }}</li>
                    @endforelse
                </ul>
                @can('update', $asset)
                    <input type="search" wire:model.live.debounce.300ms="itemSearch" maxlength="100" placeholder="{{ __('itam::assets.ui.find_item') }}" aria-label="{{ __('itam::assets.ui.find_item') }}" class="block w-full text-sm">
                    @error('itemSearch') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                    @if ($itemCandidates !== [])
                        <ul class="divide-y divide-gray-100 rounded-md text-sm ring-1 ring-gray-200">
                            @foreach ($itemCandidates as $candidate)
                                <li wire:key="candidate-{{ $candidate->id }}" class="flex items-center justify-between gap-2 px-2 py-1.5">
                                    <span>{{ $candidate->name }} <span class="text-xs text-gray-500">{{ $candidate->typeName }}</span></span>
                                    <button type="button" wire:click="linkItem('{{ $candidate->id }}')" class="text-xs text-blue-700 underline">{{ __('itam::assets.ui.link_item') }}</button>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                @endcan
            @endif
        </section>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <section aria-labelledby="details-heading" class="space-y-3 rounded-lg bg-white p-4 ring-1 ring-gray-200">
            <h2 id="details-heading" class="text-sm font-semibold">{{ __('itam::assets.ui.details') }}</h2>
            <dl class="grid gap-x-4 gap-y-2 text-sm sm:grid-cols-2">
                <div><dt class="text-xs text-gray-500">{{ __('itam::assets.fields.manufacturer') }}</dt><dd>{{ $asset->manufacturer ?? '—' }}</dd></div>
                <div><dt class="text-xs text-gray-500">{{ __('itam::assets.fields.model') }}</dt><dd>{{ $asset->model ?? '—' }}</dd></div>
                @foreach ($fields as $field)
                    @if (array_key_exists($field->key, $asset->properties))
                        <div wire:key="property-{{ $field->key }}">
                            <dt class="text-xs text-gray-500">{{ $field->label }}</dt>
                            <dd>{{ is_bool($asset->properties[$field->key]) ? __($asset->properties[$field->key] ? 'itam::assets.ui.yes' : 'itam::assets.ui.no') : $asset->properties[$field->key] }}</dd>
                        </div>
                    @endif
                @endforeach
            </dl>
            @if ($asset->notes)
                <p class="whitespace-pre-line border-t border-gray-100 pt-2 text-sm text-gray-700">{{ $asset->notes }}</p>
            @endif
        </section>

        <section aria-labelledby="purchase-heading" class="space-y-3 rounded-lg bg-white p-4 ring-1 ring-gray-200">
            <h2 id="purchase-heading" class="text-sm font-semibold">{{ __('itam::assets.ui.purchase') }}</h2>
            <dl class="grid gap-x-4 gap-y-2 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-xs text-gray-500">{{ __('itam::assets.fields.supplier') }}</dt>
                    <dd>@if ($asset->supplier) @can('view', $asset->supplier)<a href="{{ route('core.suppliers.show', $asset->supplier) }}" class="text-blue-700 underline">{{ $asset->supplier->name }}</a>@else {{ $asset->supplier->name }} @endcan @else — @endif</dd>
                </div>
                @can('viewAny', \Modules\Core\Domain\Models\Contract::class)
                    <div>
                        <dt class="text-xs text-gray-500">{{ __('itam::assets.fields.contract') }}</dt>
                        <dd>@if ($asset->contract)<a href="{{ route('core.contracts.show', $asset->contract) }}" class="text-blue-700 underline">{{ $asset->contract->title }}</a>@else — @endif</dd>
                    </div>
                @endcan
                <div><dt class="text-xs text-gray-500">{{ __('itam::assets.fields.purchase_date') }}</dt><dd>{{ $asset->purchase_date?->toDateString() ?? '—' }}</dd></div>
                <div><dt class="text-xs text-gray-500">{{ __('itam::assets.fields.purchase_cost') }}</dt><dd>{{ $asset->purchase_cost !== null ? \Illuminate\Support\Number::format((float) $asset->purchase_cost, 2).' '.$asset->currency : '—' }}</dd></div>
                <div><dt class="text-xs text-gray-500">{{ __('itam::assets.fields.order_number') }}</dt><dd>{{ $asset->order_number ?? '—' }}</dd></div>
                <div><dt class="text-xs text-gray-500">{{ __('itam::assets.fields.invoice_number') }}</dt><dd>{{ $asset->invoice_number ?? '—' }}</dd></div>
                <div><dt class="text-xs text-gray-500">{{ __('itam::assets.fields.warranty_ends_on') }}</dt><dd>{{ $asset->warranty_ends_on?->toDateString() ?? '—' }}</dd></div>
                <div><dt class="text-xs text-gray-500">{{ __('itam::assets.fields.replace_on') }}</dt><dd>{{ $asset->replace_on?->toDateString() ?? '—' }}</dd></div>
            </dl>
        </section>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <section aria-labelledby="history-heading" class="space-y-2 rounded-lg bg-white p-4 ring-1 ring-gray-200">
            <h2 id="history-heading" class="text-sm font-semibold">{{ __('itam::assets.ui.history') }}</h2>
            <ul class="divide-y divide-gray-100 text-sm">
                @forelse ($assignments as $assignment)
                    <li wire:key="assignment-{{ $assignment->id }}" class="py-2">
                        <p><span class="font-medium">{{ $assignment->user->name }}</span> · {{ $assignment->assigned_at->toDateString() }} – {{ $assignment->returned_at?->toDateString() ?? '…' }}</p>
                        <p class="text-xs text-gray-500">
                            @if ($assignment->assigner) {{ __('itam::assets.ui.handed_by', ['name' => $assignment->assigner->name]) }} @endif
                            @if ($assignment->notes) · {{ $assignment->notes }} @endif
                            @if ($assignment->return_notes) · {{ $assignment->return_notes }} @endif
                        </p>
                        <p class="text-xs">
                            <a href="{{ route('itam.handover', $assignment->id) }}" class="text-blue-700 underline">{{ __('itam::handover.protocol') }}</a>
                            @if ($assignment->acknowledged_at) · <span class="text-green-700">{{ __('itam::handover.confirmed') }}</span> @endif
                        </p>
                    </li>
                @empty
                    <li class="py-2 text-gray-500">{{ __('itam::assets.ui.no_history') }}</li>
                @endforelse
            </ul>
        </section>

        <section aria-labelledby="documents-heading" class="space-y-3 rounded-lg bg-white p-4 ring-1 ring-gray-200">
            <h2 id="documents-heading" class="text-sm font-semibold">{{ __('itam::assets.ui.documents') }}</h2>
            <ul class="divide-y divide-gray-100 text-sm">
                @forelse ($documents as $document)
                    <li wire:key="document-{{ $document->id }}" class="flex items-center justify-between gap-2 py-2">
                        <a href="{{ route('core.attachments.download', $document) }}" class="break-all text-blue-700 underline">{{ $document->original_name }}</a>
                        <span class="flex shrink-0 items-center gap-2 text-xs text-gray-500">
                            {{ \Illuminate\Support\Number::fileSize($document->size) }}
                            @can('update', $asset)
                                <button type="button" wire:click="removeDocument('{{ $document->id }}')" wire:confirm="{{ __('itam::assets.ui.remove_document_confirm') }}" class="text-red-700 underline">{{ __('itam::assets.ui.remove_document') }}</button>
                            @endcan
                        </span>
                    </li>
                @empty
                    <li class="py-2 text-gray-500">{{ __('itam::assets.ui.no_documents') }}</li>
                @endforelse
            </ul>
            @can('update', $asset)
                <form wire:submit="uploadDocuments" class="space-y-2">
                    <input type="file" wire:model="files" multiple class="block w-full text-sm" aria-label="{{ __('itam::assets.ui.documents') }}">
                    @error('files') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                    @error('files.*') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                    <button type="submit" wire:loading.attr="disabled" class="rounded-md bg-white px-3 py-1.5 text-sm font-semibold ring-1 ring-gray-300">{{ __('itam::assets.ui.upload') }}</button>
                </form>
            @endcan
        </section>
    </div>

    @if ($licenses->isNotEmpty())
        <section aria-labelledby="licenses-heading" class="space-y-2 rounded-lg bg-white p-4 ring-1 ring-gray-200">
            <h2 id="licenses-heading" class="text-sm font-semibold">{{ __('itam::licenses.ui.on_device') }}</h2>
            <ul class="divide-y divide-gray-100 text-sm">
                @foreach ($licenses as $seat)
                    <li wire:key="license-seat-{{ $seat->id }}" class="py-1.5"><a href="{{ route('itam.licenses.show', $seat->license_id) }}" class="text-blue-700 underline">{{ $seat->license->product->name }} · {{ $seat->license->name }}</a></li>
                @endforeach
            </ul>
        </section>
    @endif

    <x-core::record-custom-fields :record="$asset" />

    <x-core::record-tags :record="$asset" />

    <x-core::record-history :record="$asset" :can-change="auth()->user()->can('update', $asset)" />
</div>
