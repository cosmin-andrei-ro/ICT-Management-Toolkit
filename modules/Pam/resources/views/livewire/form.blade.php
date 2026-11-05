<div class="mx-auto max-w-2xl space-y-6">
    <header class="space-y-1">
        <a href="{{ $accountId ? route('pam.accounts.show', $accountId) : route('pam.accounts.index') }}" class="text-sm text-gray-500 underline">← {{ __('pam::accounts.title') }}</a>
        <h1 class="text-xl font-semibold">{{ __($accountId ? 'pam::accounts.ui.edit' : 'pam::accounts.ui.new') }}</h1>
    </header>

    <form wire:submit="save" class="space-y-5 rounded-lg bg-white p-6 ring-1 ring-gray-200" novalidate>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="name" class="block text-sm font-medium">{{ __('pam::accounts.fields.name') }} <span class="text-red-600" aria-hidden="true">*</span></label>
                <input id="name" type="text" wire:model="name" maxlength="255" class="mt-1 block w-full">
                @error('name') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="username" class="block text-sm font-medium">{{ __('pam::accounts.fields.username') }} <span class="text-red-600" aria-hidden="true">*</span></label>
                <input id="username" type="text" wire:model="username" maxlength="255" autocomplete="off" class="mt-1 block w-full">
                @error('username') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="type" class="block text-sm font-medium">{{ __('pam::accounts.fields.type') }}</label>
                <select id="type" wire:model="type" class="mt-1 block w-full">
                    @foreach ($types as $option) <option value="{{ $option->value }}">{{ $option->label() }}</option> @endforeach
                </select>
                @error('type') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="ownerId" class="block text-sm font-medium">{{ __('pam::accounts.fields.owner') }}</label>
                <select id="ownerId" wire:model="ownerId" class="mt-1 block w-full">
                    <option value="">—</option>
                    @foreach ($people as $id => $person) <option value="{{ $id }}">{{ $person }}</option> @endforeach
                </select>
                @error('ownerId') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label for="description" class="block text-sm font-medium">{{ __('pam::accounts.fields.description') }}</label>
            <textarea id="description" wire:model="description" rows="3" maxlength="5000" class="mt-1 block w-full"></textarea>
            <p class="mt-1 text-xs text-gray-500">{{ __('pam::accounts.ui.description_help') }}</p>
            @error('description') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
        </div>

        @if ($itemsAvailable)
            <div>
                <label for="itemSearch" class="block text-sm font-medium">{{ __('pam::accounts.fields.item') }}</label>
                @if ($item) <p class="text-sm">{{ $item->name }} <button type="button" wire:click="$set('configurationItemId', '')" class="text-xs text-gray-600 underline">{{ __('pam::accounts.ui.remove') }}</button></p> @endif
                <input id="itemSearch" type="search" wire:model.live.debounce.400ms="itemSearch" maxlength="200" class="mt-1 block w-full">
                @foreach ($itemResults as $result)
                    <button type="button" wire:key="item-{{ $result->id }}" wire:click="pickItem('{{ $result->id }}')" class="mt-1 block text-sm text-blue-700 underline">{{ $result->name }}</button>
                @endforeach
            </div>
        @endif

        @if ($applicationsAvailable)
            <div>
                <label for="applicationSearch" class="block text-sm font-medium">{{ __('pam::accounts.fields.application') }}</label>
                @if ($application) <p class="text-sm">{{ $application->name }} <button type="button" wire:click="$set('applicationId', '')" class="text-xs text-gray-600 underline">{{ __('pam::accounts.ui.remove') }}</button></p> @endif
                <input id="applicationSearch" type="search" wire:model.live.debounce.400ms="applicationSearch" maxlength="200" class="mt-1 block w-full">
                @foreach ($applicationResults as $result)
                    <button type="button" wire:key="application-{{ $result->id }}" wire:click="pickApplication('{{ $result->id }}')" class="mt-1 block text-sm text-blue-700 underline">{{ $result->name }}</button>
                @endforeach
            </div>
        @endif

        <fieldset class="space-y-3 border-t border-gray-100 pt-4">
            <legend class="text-sm font-semibold">{{ __('pam::accounts.ui.access_rules') }}</legend>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="requiresApproval"> {{ __('pam::accounts.fields.requires_approval') }}</label>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="approverId" class="block text-sm font-medium">{{ __('pam::accounts.fields.approver') }}</label>
                    <select id="approverId" wire:model="approverId" class="mt-1 block w-full">
                        <option value="">—</option>
                        @foreach ($people as $id => $person) <option value="{{ $id }}">{{ $person }}</option> @endforeach
                    </select>
                    <p class="mt-1 text-xs text-gray-500">{{ __('pam::accounts.ui.approver_help') }}</p>
                    @error('approverId') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="accessMinutes" class="block text-sm font-medium">{{ __('pam::accounts.fields.access_minutes') }}</label>
                    <input id="accessMinutes" type="number" min="5" max="1440" wire:model="accessMinutes" class="mt-1 block w-full">
                    @error('accessMinutes') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="rotationDays" class="block text-sm font-medium">{{ __('pam::accounts.fields.rotation_days') }}</label>
                    <input id="rotationDays" type="number" min="1" max="3650" wire:model="rotationDays" class="mt-1 block w-full">
                    @error('rotationDays') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="reviewDays" class="block text-sm font-medium">{{ __('pam::accounts.fields.review_days') }}</label>
                    <input id="reviewDays" type="number" min="1" max="3650" wire:model="reviewDays" class="mt-1 block w-full">
                    @error('reviewDays') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>
            </div>
            @if ($accountId)
                <div>
                    <label for="code" class="block text-sm font-medium">{{ __('pam::accounts.ui.code') }}</label>
                    <input id="code" type="text" wire:model="code" inputmode="numeric" autocomplete="one-time-code" maxlength="32" class="mt-1 block w-40">
                    <p class="mt-1 text-xs text-gray-500">{{ __('pam::accounts.ui.code_rules_help') }}</p>
                    @error('code') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>
            @endif
        </fieldset>

        <button type="submit" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white">{{ __('pam::accounts.ui.save') }}</button>
    </form>
</div>
