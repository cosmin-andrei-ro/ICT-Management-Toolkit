<div class="space-y-6">
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div class="space-y-1">
            <a href="{{ route('cmdb.items.index') }}" class="text-sm text-gray-500 underline">{{ __('cmdb::items.ui.back') }}</a>
            <h1 class="text-xl font-semibold">{{ $item->name }}</h1>
            <div class="flex flex-wrap items-center gap-2 text-xs">
                <span class="rounded bg-gray-100 px-2 py-0.5">{{ $item->type->name }}</span>
                <span class="rounded bg-gray-100 px-2 py-0.5">{{ $item->status->label() }}</span>
                @if ($item->environment) <span class="rounded bg-gray-100 px-2 py-0.5">{{ $item->environment->label() }}</span> @endif
                <x-cmdb::criticality :value="$item->criticality" />
                @if ($item->identifier) <span class="font-mono text-gray-500">{{ $item->identifier }}</span> @endif
            </div>
        </div>
        @can('update', $item)
            <div class="flex gap-2">
                <a href="{{ route('cmdb.items.edit', $item) }}" class="rounded-md bg-white px-3 py-1.5 text-sm font-semibold ring-1 ring-gray-300">{{ __('cmdb::items.ui.edit') }}</a>
                <button type="button" wire:click="delete" wire:confirm="{{ __('cmdb::items.ui.delete_confirm') }}" class="rounded-md bg-white px-3 py-1.5 text-sm font-semibold text-red-700 ring-1 ring-gray-300">{{ __('cmdb::items.ui.delete') }}</button>
            </div>
        @endcan
    </header>

    <div class="grid gap-6 lg:grid-cols-3">
        <section aria-labelledby="details-heading" class="space-y-3 rounded-lg bg-white p-4 ring-1 ring-gray-200 lg:col-span-1">
            <h2 id="details-heading" class="text-sm font-semibold">{{ __('cmdb::items.ui.details') }}</h2>
            <dl class="space-y-2 text-sm">
                <div><dt class="text-xs text-gray-500">{{ __('cmdb::items.fields.owner') }}</dt><dd>{{ $item->owner?->name ?? '—' }}</dd></div>
                <div><dt class="text-xs text-gray-500">{{ __('cmdb::items.fields.team') }}</dt><dd>{{ $item->team?->name ?? '—' }}</dd></div>
                <div><dt class="text-xs text-gray-500">{{ __('cmdb::items.fields.recovery_time') }}</dt><dd>{{ \Modules\Core\Domain\Time\Duration::format($item->recovery_time_minutes) }}</dd></div>
                <div><dt class="text-xs text-gray-500">{{ __('cmdb::items.fields.recovery_point') }}</dt><dd>{{ \Modules\Core\Domain\Time\Duration::format($item->recovery_point_minutes) }}</dd></div>
                <div>
                    <dt class="text-xs text-gray-500">{{ __('cmdb::items.fields.recovery_verified') }}</dt>
                    @if ($item->recovery_verified_on)
                        <dd>{{ $item->recovery_verified_on->toDateString() }}@if ($item->recovery_verified_minutes !== null) · {{ \Modules\Core\Domain\Time\Duration::format($item->recovery_verified_minutes) }}@endif</dd>
                        @if ($item->recovery_verified_by) <dd class="text-xs text-gray-500">{{ $item->recovery_verified_by }}</dd> @endif
                        @if ($item->recovery_verified_minutes !== null && $item->recovery_time_minutes !== null && $item->recovery_verified_minutes > $item->recovery_time_minutes)
                            <dd class="text-xs font-medium text-amber-700">{{ __('cmdb::items.ui.verified_slower') }}</dd>
                        @endif
                    @else
                        <dd class="text-gray-500">{{ __('cmdb::items.ui.never_verified') }}</dd>
                    @endif
                </div>
                @foreach ($fields as $field)
                    @if (array_key_exists($field->key, $item->properties))
                        <div wire:key="property-{{ $field->key }}">
                            <dt class="text-xs text-gray-500">{{ $field->label }}</dt>
                            <dd class="whitespace-pre-line">{{ is_bool($item->properties[$field->key]) ? __($item->properties[$field->key] ? 'cmdb::items.ui.yes' : 'cmdb::items.ui.no') : $item->properties[$field->key] }}</dd>
                        </div>
                    @endif
                @endforeach
                @if ($item->description)
                    <div><dt class="text-xs text-gray-500">{{ __('cmdb::items.fields.description') }}</dt><dd class="whitespace-pre-line">{{ $item->description }}</dd></div>
                @endif
            </dl>
        </section>

        <section aria-labelledby="graph-heading" class="space-y-2 rounded-lg bg-white p-4 ring-1 ring-gray-200 lg:col-span-2">
            <h2 id="graph-heading" class="text-sm font-semibold">{{ __('cmdb::relationships.ui.graph') }}</h2>
            @if ($graph['edges'] === [])
                <p class="text-sm text-gray-500">{{ __('cmdb::relationships.ui.none') }}</p>
            @else
                <div wire:ignore wire:key="graph-{{ md5(json_encode($graph)) }}" x-data="ciGraph" data-graph="{{ json_encode($graph) }}">
                    <div x-ref="canvas" class="h-96 w-full rounded border border-gray-100" role="img" aria-label="{{ __('cmdb::relationships.ui.graph_label') }}"></div>
                </div>
                <p class="text-xs text-gray-500">{{ __('cmdb::relationships.ui.graph_help') }}</p>
            @endif
        </section>
    </div>

    <section aria-labelledby="relationships-heading" class="space-y-3 rounded-lg bg-white p-4 ring-1 ring-gray-200">
        <h2 id="relationships-heading" class="text-sm font-semibold">{{ __('cmdb::relationships.ui.title') }}</h2>
        <ul class="divide-y divide-gray-100 text-sm">
            @foreach ($item->outgoing as $relationship)
                <li wire:key="rel-{{ $relationship->id }}" class="flex items-center justify-between gap-2 py-2">
                    <span>{{ $item->name }} <span class="text-gray-500">{{ $relationship->type->label() }}</span> <a href="{{ route('cmdb.items.show', $relationship->target_id) }}" class="text-blue-700 underline">{{ $relationship->target->name }}</a> <span class="text-xs text-gray-400">{{ $relationship->target->type->name }}</span></span>
                    @can('update', $item)
                        <button type="button" wire:click="removeRelationship('{{ $relationship->id }}')" class="text-xs text-red-700 underline">{{ __('cmdb::relationships.ui.remove') }}</button>
                    @endcan
                </li>
            @endforeach
            @foreach ($item->incoming as $relationship)
                <li wire:key="rel-{{ $relationship->id }}" class="flex items-center justify-between gap-2 py-2">
                    <span>{{ $item->name }} <span class="text-gray-500">{{ $relationship->type->inverseLabel() }}</span> <a href="{{ route('cmdb.items.show', $relationship->source_id) }}" class="text-blue-700 underline">{{ $relationship->source->name }}</a> <span class="text-xs text-gray-400">{{ $relationship->source->type->name }}</span></span>
                    @can('update', $item)
                        <button type="button" wire:click="removeRelationship('{{ $relationship->id }}')" class="text-xs text-red-700 underline">{{ __('cmdb::relationships.ui.remove') }}</button>
                    @endcan
                </li>
            @endforeach
        </ul>

        @can('update', $item)
            <form wire:submit="addRelationship" class="space-y-2 border-t border-gray-100 pt-3">
                <p class="text-sm font-medium">{{ __('cmdb::relationships.ui.add') }}</p>
                <div class="flex flex-wrap items-center gap-2 text-sm">
                    <span>{{ $item->name }}</span>
                    <select wire:model="direction" aria-label="{{ __('cmdb::relationships.ui.direction') }}" class="rounded-md border-gray-300 text-sm">
                        <option value="outgoing">{{ __('cmdb::relationships.ui.outgoing') }}</option>
                        <option value="incoming">{{ __('cmdb::relationships.ui.incoming') }}</option>
                    </select>
                    <select wire:model="relationshipType" aria-label="{{ __('cmdb::relationships.ui.type') }}" class="rounded-md border-gray-300 text-sm">
                        @foreach ($relationshipTypes as $type)
                            <option value="{{ $type->value }}">{{ $type->label() }} / {{ $type->inverseLabel() }}</option>
                        @endforeach
                    </select>
                    <input type="search" wire:model.live.debounce.300ms="otherSearch" maxlength="200" placeholder="{{ __('cmdb::relationships.ui.find') }}" aria-label="{{ __('cmdb::relationships.ui.find') }}" class="rounded-md border-gray-300 text-sm">
                    <select wire:model="otherId" aria-label="{{ __('cmdb::relationships.ui.other') }}" class="rounded-md border-gray-300 text-sm">
                        <option value="">{{ __('cmdb::relationships.ui.choose') }}</option>
                        @foreach ($candidates as $candidate)
                            <option value="{{ $candidate->id }}" wire:key="candidate-{{ $candidate->id }}">{{ $candidate->name }} ({{ $candidate->type->name }})</option>
                        @endforeach
                    </select>
                    <button type="submit" class="rounded-md bg-gray-900 px-3 py-1.5 text-sm font-semibold text-white">{{ __('cmdb::relationships.ui.add_button') }}</button>
                </div>
                <p class="text-xs text-gray-500">{{ __('cmdb::relationships.ui.add_help') }}</p>
                @error('otherId') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            </form>
        @endcan
    </section>

    <section aria-labelledby="records-heading" class="space-y-2 rounded-lg bg-white p-4 ring-1 ring-gray-200">
        <h2 id="records-heading" class="text-sm font-semibold">{{ __('cmdb::items.ui.linked_records') }}</h2>
        <ul class="space-y-1 text-sm">
            @forelse ($linkedRecords as $record)
                <li wire:key="record-{{ $record->id }}">
                    <a href="{{ $record->url }}" class="text-blue-700 underline">{{ $record->title }}</a>
                    @if ($record->status) <span class="text-xs text-gray-500">{{ $record->status }}</span> @endif
                </li>
            @empty
                <li class="text-gray-500">{{ __('cmdb::items.ui.no_linked_records') }}</li>
            @endforelse
        </ul>
    </section>

    <div class="grid gap-6 lg:grid-cols-2">
        @foreach (['affected' => $affected, 'dependencies' => $dependencies] as $kind => $list)
            <section aria-labelledby="{{ $kind }}-heading" class="space-y-2 rounded-lg bg-white p-4 ring-1 ring-gray-200" wire:key="impact-{{ $kind }}">
                <h2 id="{{ $kind }}-heading" class="text-sm font-semibold">{{ __('cmdb::relationships.impact.'.$kind) }}</h2>
                <p class="text-xs text-gray-500">{{ __('cmdb::relationships.impact.'.$kind.'_help') }}</p>
                <ul class="space-y-1 text-sm">
                    @forelse ($list as $impacted)
                        <li wire:key="{{ $kind }}-{{ $impacted->item->id }}" style="padding-left: {{ ($impacted->depth - 1) * 1 }}rem">
                            <a href="{{ route('cmdb.items.show', $impacted->item) }}" class="text-blue-700 underline">{{ $impacted->item->name }}</a>
                            <span class="text-xs text-gray-400">{{ $impacted->item->type->name }} · {{ trans_choice('cmdb::relationships.impact.depth', $impacted->depth, ['count' => $impacted->depth]) }}</span>
                            <x-cmdb::criticality :value="$impacted->item->criticality" />
                        </li>
                    @empty
                        <li class="text-gray-500">{{ __('cmdb::relationships.impact.none') }}</li>
                    @endforelse
                </ul>
            </section>
        @endforeach
    </div>

    <x-core::record-custom-fields :record="$item" />

    <x-core::record-tags :record="$item" />

    <x-core::record-history :record="$item" />
</div>
