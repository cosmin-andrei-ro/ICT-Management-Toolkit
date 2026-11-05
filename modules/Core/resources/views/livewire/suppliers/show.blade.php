<div class="space-y-6">
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div class="space-y-1">
            <a href="{{ route('core.suppliers.index') }}" class="text-sm text-gray-500 underline">{{ __('core::suppliers.ui.back') }}</a>
            <h1 class="text-xl font-semibold">{{ $supplier->name }}</h1>
            @if ($supplier->legal_name || ! $supplier->is_active)
                <p class="text-sm text-gray-600">
                    {{ $supplier->legal_name }}
                    @unless ($supplier->is_active) <span class="ml-1 rounded bg-gray-100 px-2 py-0.5 text-xs">{{ __('core::suppliers.ui.inactive') }}</span> @endunless
                </p>
            @endif
        </div>
        @can('update', $supplier)
            <div class="flex gap-2">
                <a href="{{ route('core.suppliers.edit', $supplier) }}" class="rounded-md bg-white px-3 py-1.5 text-sm font-semibold ring-1 ring-gray-300">{{ __('core::suppliers.ui.edit') }}</a>
                <button type="button" wire:click="delete" wire:confirm="{{ __('core::suppliers.ui.delete_confirm') }}" class="rounded-md bg-white px-3 py-1.5 text-sm font-semibold text-red-700 ring-1 ring-gray-300">{{ __('core::suppliers.ui.delete') }}</button>
            </div>
        @endcan
    </header>
    @error('delete') <p class="rounded-md bg-red-50 p-3 text-sm text-red-800" role="alert">{{ $message }}</p> @enderror

    <div class="grid gap-6 lg:grid-cols-3">
        <section aria-labelledby="support-heading" class="space-y-3 rounded-lg bg-white p-4 ring-1 ring-gray-200">
            <h2 id="support-heading" class="text-sm font-semibold">{{ __('core::suppliers.ui.support') }}</h2>
            <dl class="space-y-2 text-sm">
                <div><dt class="text-xs text-gray-500">{{ __('core::suppliers.fields.support_phone') }}</dt><dd>@if ($supplier->support_phone)<a href="tel:{{ $supplier->support_phone }}" class="text-blue-700 underline">{{ $supplier->support_phone }}</a>@else — @endif</dd></div>
                <div><dt class="text-xs text-gray-500">{{ __('core::suppliers.fields.support_email') }}</dt><dd>@if ($supplier->support_email)<a href="mailto:{{ $supplier->support_email }}" class="text-blue-700 underline">{{ $supplier->support_email }}</a>@else — @endif</dd></div>
                <div><dt class="text-xs text-gray-500">{{ __('core::suppliers.fields.support_hours') }}</dt><dd>{{ $supplier->support_hours ?? '—' }}</dd></div>
                <div><dt class="text-xs text-gray-500">{{ __('core::suppliers.fields.customer_number') }}</dt><dd class="font-mono">{{ $supplier->customer_number ?? '—' }}</dd></div>
                <div><dt class="text-xs text-gray-500">{{ __('core::suppliers.fields.website') }}</dt><dd>@if ($supplier->website)<a href="{{ $supplier->website }}" rel="noopener noreferrer" target="_blank" class="break-all text-blue-700 underline">{{ $supplier->website }}</a>@else — @endif</dd></div>
                <div><dt class="text-xs text-gray-500">{{ __('core::suppliers.fields.tax_id') }}</dt><dd>{{ $supplier->tax_id ?? '—' }}</dd></div>
                @if ($supplier->notes)
                    <div><dt class="text-xs text-gray-500">{{ __('core::suppliers.fields.notes') }}</dt><dd class="whitespace-pre-line">{{ $supplier->notes }}</dd></div>
                @endif
            </dl>
        </section>

        <section aria-labelledby="contacts-heading" class="space-y-3 rounded-lg bg-white p-4 ring-1 ring-gray-200 lg:col-span-2">
            <div class="flex items-center justify-between">
                <h2 id="contacts-heading" class="text-sm font-semibold">{{ __('core::suppliers.contacts.title') }}</h2>
                @can('update', $supplier)
                    @if ($editingContactId === null)
                        <button type="button" wire:click="startContact" class="rounded-md bg-white px-3 py-1 text-sm font-semibold ring-1 ring-gray-300">{{ __('core::suppliers.contacts.add') }}</button>
                    @endif
                @endcan
            </div>

            @if ($editingContactId !== null)
                <form wire:submit="saveContact" class="space-y-3 rounded-md bg-gray-50 p-3" novalidate>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label for="contactName" class="block text-sm font-medium">{{ __('core::suppliers.contacts.name') }} <span class="text-red-600" aria-hidden="true">*</span></label>
                            <input id="contactName" type="text" wire:model="contactName" maxlength="255" required class="mt-1 block w-full text-sm">
                            @error('contactName') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="contactRole" class="block text-sm font-medium">{{ __('core::suppliers.contacts.role') }}</label>
                            <input id="contactRole" type="text" wire:model="contactRole" maxlength="255" class="mt-1 block w-full text-sm">
                        </div>
                        <div>
                            <label for="contactEmail" class="block text-sm font-medium">{{ __('core::suppliers.contacts.email') }}</label>
                            <input id="contactEmail" type="email" wire:model="contactEmail" maxlength="255" class="mt-1 block w-full text-sm">
                            @error('contactEmail') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="contactPhone" class="block text-sm font-medium">{{ __('core::suppliers.contacts.phone') }}</label>
                            <input id="contactPhone" type="text" wire:model="contactPhone" maxlength="64" class="mt-1 block w-full text-sm">
                        </div>
                    </div>
                    <div>
                        <label for="contactNotes" class="block text-sm font-medium">{{ __('core::suppliers.contacts.notes') }}</label>
                        <input id="contactNotes" type="text" wire:model="contactNotes" maxlength="1000" class="mt-1 block w-full text-sm">
                    </div>
                    <p class="text-xs text-gray-500">{{ __('core::suppliers.contacts.privacy') }}</p>
                    <div class="flex gap-2">
                        <button type="submit" class="rounded-md bg-gray-900 px-3 py-1.5 text-sm font-semibold text-white">{{ __('core::suppliers.contacts.save') }}</button>
                        <button type="button" wire:click="cancelContact" class="rounded-md bg-white px-3 py-1.5 text-sm font-semibold ring-1 ring-gray-300">{{ __('core::suppliers.contacts.cancel') }}</button>
                    </div>
                </form>
            @endif

            <ul class="divide-y divide-gray-100 text-sm">
                @forelse ($supplier->contacts as $contact)
                    <li wire:key="contact-{{ $contact->id }}" class="flex flex-wrap items-start justify-between gap-2 py-2">
                        <div>
                            <p class="font-medium">{{ $contact->name }} @if ($contact->role)<span class="font-normal text-gray-500">· {{ $contact->role }}</span>@endif</p>
                            <p class="text-gray-600">
                                @if ($contact->email)<a href="mailto:{{ $contact->email }}" class="text-blue-700 underline">{{ $contact->email }}</a>@endif
                                @if ($contact->email && $contact->phone) · @endif
                                @if ($contact->phone)<a href="tel:{{ $contact->phone }}" class="text-blue-700 underline">{{ $contact->phone }}</a>@endif
                            </p>
                            @if ($contact->notes)<p class="text-xs text-gray-500">{{ $contact->notes }}</p>@endif
                        </div>
                        @can('update', $supplier)
                            <div class="flex gap-2 text-xs">
                                <button type="button" wire:click="startContact('{{ $contact->id }}')" class="text-blue-700 underline">{{ __('core::suppliers.contacts.edit') }}</button>
                                <button type="button" wire:click="removeContact('{{ $contact->id }}')" wire:confirm="{{ __('core::suppliers.contacts.remove_confirm') }}" class="text-red-700 underline">{{ __('core::suppliers.contacts.remove') }}</button>
                            </div>
                        @endcan
                    </li>
                @empty
                    <li class="py-2 text-gray-500">{{ __('core::suppliers.contacts.empty') }}</li>
                @endforelse
            </ul>
        </section>
    </div>

    @if ($canSeeContracts)
        <section aria-labelledby="contracts-heading" class="space-y-3 rounded-lg bg-white p-4 ring-1 ring-gray-200">
            <div class="flex items-center justify-between">
                <h2 id="contracts-heading" class="text-sm font-semibold">{{ __('core::suppliers.ui.contracts') }}</h2>
                @can('create', \Modules\Core\Domain\Models\Contract::class)
                    <a href="{{ route('core.contracts.create', ['supplier' => $supplier->id]) }}" class="rounded-md bg-white px-3 py-1 text-sm font-semibold ring-1 ring-gray-300">{{ __('core::suppliers.ui.new_contract') }}</a>
                @endcan
            </div>
            <ul class="divide-y divide-gray-100 text-sm">
                @forelse ($contracts as $contract)
                    <li wire:key="contract-{{ $contract->id }}" class="flex flex-wrap items-center justify-between gap-2 py-2">
                        <a href="{{ route('core.contracts.show', $contract) }}" class="text-blue-700 underline">{{ $contract->title }}</a>
                        <span class="flex items-center gap-2 text-xs text-gray-500">
                            {{ $contract->start_date->toDateString() }} – {{ $contract->end_date?->toDateString() ?? __('core::contracts.ui.no_end') }}
                            <x-core::contract-status :status="$contract->statusOn(\Carbon\CarbonImmutable::today(), $expiringDays)" />
                        </span>
                    </li>
                @empty
                    <li class="py-2 text-gray-500">{{ __('core::suppliers.ui.no_contracts') }}</li>
                @endforelse
            </ul>
        </section>
    @endif

    <x-core::record-custom-fields :record="$supplier" />

    <x-core::record-tags :record="$supplier" />

    <x-core::record-history :record="$supplier" />
</div>
