<div class="mx-auto max-w-2xl space-y-6">
    <header class="space-y-1">
        <a href="{{ $applicationId ? route('applications.show', $applicationId) : route('applications.index') }}" class="text-sm text-gray-500 underline">{{ __('applications::applications.ui.back') }}</a>
        <h1 class="text-xl font-semibold">{{ __($applicationId ? 'applications::applications.ui.edit' : 'applications::applications.ui.new') }}</h1>
    </header>

    <form wire:submit="save" class="space-y-5 rounded-lg bg-white p-6 ring-1 ring-gray-200" novalidate>
        <div class="grid gap-4 sm:grid-cols-3">
            <div class="sm:col-span-2">
                <label for="name" class="block text-sm font-medium">{{ __('applications::applications.fields.name') }} <span class="text-red-600" aria-hidden="true">*</span></label>
                <input id="name" type="text" wire:model="name" maxlength="255" required placeholder="{{ __('applications::applications.examples.name') }}" class="mt-1 block w-full">
                @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="code" class="block text-sm font-medium">{{ __('applications::applications.fields.code') }} <span class="text-red-600" aria-hidden="true">*</span></label>
                <input id="code" type="text" wire:model="code" maxlength="32" required placeholder="{{ __('applications::applications.examples.code') }}" class="mt-1 block w-full">
                @error('code') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="departmentId" class="block text-sm font-medium">{{ __('applications::applications.fields.department_owner') }}</label>
                <select id="departmentId" wire:model="departmentId" class="mt-1 block w-full">
                    <option value="">—</option>
                    @foreach ($departments as $department) <option value="{{ $department->id }}" wire:key="department-{{ $department->id }}">{{ $department->name }}</option> @endforeach
                </select>
                @error('departmentId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="departmentEmail" class="block text-sm font-medium">{{ __('applications::applications.fields.department_email') }}</label>
                <input id="departmentEmail" type="email" wire:model="departmentEmail" maxlength="255" placeholder="{{ __('applications::applications.examples.department_email') }}" class="mt-1 block w-full">
                @error('departmentEmail') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>
        <div class="grid gap-4 sm:grid-cols-3">
            @foreach (['businessOwnerId' => ['business_owner', true], 'technicalOwnerId' => ['technical_owner', true], 'deputyId' => ['deputy', false]] as $property => [$labelKey, $required])
                <div wire:key="owner-{{ $property }}">
                    <label for="{{ $property }}" class="block text-sm font-medium">{{ __('applications::applications.fields.'.$labelKey) }}@if ($required) <span class="text-red-600" aria-hidden="true">*</span>@endif</label>
                    <select id="{{ $property }}" wire:model="{{ $property }}" class="mt-1 block w-full">
                        <option value="">—</option>
                        @foreach ($people as $person) <option value="{{ $person->id }}" wire:key="{{ $property }}-{{ $person->id }}">{{ $person->name }}</option> @endforeach
                    </select>
                    @error($property) <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            @endforeach
        </div>
        <p class="text-xs text-gray-500">{{ __('applications::applications.ui.owners_help') }}</p>
        <button type="submit" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white">{{ __('applications::applications.ui.save') }}</button>
    </form>
</div>
