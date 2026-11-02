<div class="space-y-6">
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div class="space-y-1">
            <a href="{{ route('core.contracts.index') }}" class="text-sm text-gray-500 underline">{{ __('core::contracts.ui.back') }}</a>
            <h1 class="text-xl font-semibold">{{ $contract->title }}</h1>
            <div class="flex flex-wrap items-center gap-2 text-xs">
                <x-core::contract-status :status="$status" />
                <span class="rounded bg-gray-100 px-2 py-0.5">{{ $contract->type->label() }}</span>
                @if ($contract->reference) <span class="font-mono text-gray-500">{{ $contract->reference }}</span> @endif
            </div>
        </div>
        @can('update', $contract)
            <div class="flex gap-2">
                <a href="{{ route('core.contracts.edit', $contract) }}" class="rounded-md bg-white px-3 py-1.5 text-sm font-semibold ring-1 ring-gray-300">{{ __('core::contracts.ui.edit') }}</a>
                <button type="button" wire:click="delete" wire:confirm="{{ __('core::contracts.ui.delete_confirm') }}" class="rounded-md bg-white px-3 py-1.5 text-sm font-semibold text-red-700 ring-1 ring-gray-300">{{ __('core::contracts.ui.delete') }}</button>
            </div>
        @endcan
    </header>

    <div class="grid gap-6 lg:grid-cols-3">
        <section aria-labelledby="details-heading" class="space-y-3 rounded-lg bg-white p-4 ring-1 ring-gray-200">
            <h2 id="details-heading" class="text-sm font-semibold">{{ __('core::contracts.ui.details') }}</h2>
            <dl class="space-y-2 text-sm">
                <div><dt class="text-xs text-gray-500">{{ __('core::contracts.fields.supplier') }}</dt><dd><a href="{{ route('core.suppliers.show', $contract->supplier) }}" class="text-blue-700 underline">{{ $contract->supplier->name }}</a></dd></div>
                <div><dt class="text-xs text-gray-500">{{ __('core::contracts.fields.owner') }}</dt><dd>{{ $contract->owner?->name ?? '—' }}</dd></div>
                <div>
                    <dt class="text-xs text-gray-500">{{ __('core::contracts.fields.value') }}</dt>
                    <dd>
                        @if ($contract->value !== null)
                            {{ \Illuminate\Support\Number::format((float) $contract->value, 2) }} {{ $contract->currency }}
                            @if ($contract->billing_period) <span class="text-gray-500">· {{ $contract->billing_period->label() }}</span> @endif
                        @else
                            —
                        @endif
                    </dd>
                </div>
                @if ($contract->description)
                    <div><dt class="text-xs text-gray-500">{{ __('core::contracts.fields.description') }}</dt><dd class="whitespace-pre-line">{{ $contract->description }}</dd></div>
                @endif
            </dl>
        </section>

        <section aria-labelledby="terms-heading" class="space-y-3 rounded-lg bg-white p-4 ring-1 ring-gray-200">
            <h2 id="terms-heading" class="text-sm font-semibold">{{ __('core::contracts.ui.terms') }}</h2>
            <dl class="space-y-2 text-sm">
                <div><dt class="text-xs text-gray-500">{{ __('core::contracts.fields.start_date') }}</dt><dd>{{ $contract->start_date->toDateString() }}</dd></div>
                <div><dt class="text-xs text-gray-500">{{ __('core::contracts.fields.end_date') }}</dt><dd>{{ $contract->end_date?->toDateString() ?? __('core::contracts.ui.no_end') }}</dd></div>
                @if ($contract->notice_days !== null)
                    <div><dt class="text-xs text-gray-500">{{ __('core::contracts.fields.notice_days') }}</dt><dd>{{ $contract->notice_days }}</dd></div>
                @endif
                @if ($deadline !== null)
                    <div>
                        <dt class="text-xs text-gray-500">{{ __('core::contracts.fields.deadline') }}</dt>
                        <dd class="font-semibold">{{ $deadline->toDateString() }}</dd>
                        <dd class="text-xs text-gray-500">{{ __('core::contracts.help.deadline') }}</dd>
                    </div>
                @endif
                @if ($contract->auto_renews)
                    <div><dt class="text-xs text-gray-500">{{ __('core::contracts.fields.auto_renews') }}</dt><dd>{{ trans_choice('core::contracts.ui.renews_every', $contract->renewal_months ?? 0, ['months' => $contract->renewal_months]) }}</dd></div>
                @endif
                @if ($contract->terminated_on)
                    <div><dt class="text-xs text-gray-500">{{ __('core::contracts.fields.terminated_on') }}</dt><dd>{{ $contract->terminated_on->toDateString() }}</dd></div>
                @endif
            </dl>
        </section>

        <section aria-labelledby="documents-heading" class="space-y-3 rounded-lg bg-white p-4 ring-1 ring-gray-200">
            <h2 id="documents-heading" class="text-sm font-semibold">{{ __('core::contracts.ui.documents') }}</h2>
            <ul class="divide-y divide-gray-100 text-sm">
                @forelse ($documents as $document)
                    <li wire:key="document-{{ $document->id }}" class="flex items-center justify-between gap-2 py-2">
                        <a href="{{ route('core.attachments.download', $document) }}" class="break-all text-blue-700 underline">{{ $document->original_name }}</a>
                        <span class="flex shrink-0 items-center gap-2 text-xs text-gray-500">
                            {{ \Illuminate\Support\Number::fileSize($document->size) }}
                            @can('update', $contract)
                                <button type="button" wire:click="removeDocument('{{ $document->id }}')" wire:confirm="{{ __('core::contracts.ui.remove_document_confirm') }}" class="text-red-700 underline">{{ __('core::contracts.ui.remove_document') }}</button>
                            @endcan
                        </span>
                    </li>
                @empty
                    <li class="py-2 text-gray-500">{{ __('core::contracts.ui.no_documents') }}</li>
                @endforelse
            </ul>
            @can('update', $contract)
                <form wire:submit="uploadDocuments" class="space-y-2">
                    <input type="file" wire:model="files" multiple class="block w-full text-sm" aria-label="{{ __('core::contracts.ui.documents') }}">
                    @error('files') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                    @error('files.*') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                    <button type="submit" wire:loading.attr="disabled" class="rounded-md bg-white px-3 py-1.5 text-sm font-semibold ring-1 ring-gray-300">{{ __('core::contracts.ui.upload') }}</button>
                </form>
            @endcan
        </section>
    </div>

    @foreach ($dependents as $section)
        <section aria-labelledby="dependents-{{ $loop->index }}" wire:key="dependents-{{ $loop->index }}" class="space-y-2 rounded-lg bg-white p-4 ring-1 ring-gray-200">
            <h2 id="dependents-{{ $loop->index }}" class="text-sm font-semibold">{{ $section['label'] }}</h2>
            <ul class="divide-y divide-gray-100 text-sm">
                @foreach ($section['records'] as $record)
                    <li wire:key="dependent-{{ $record->id }}" class="flex items-center justify-between gap-2 py-1.5">
                        <a href="{{ $record->url }}" class="text-blue-700 underline">{{ $record->title }}</a>
                        @if ($record->status) <span class="text-xs text-gray-500">{{ $record->status }}</span> @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endforeach
</div>
