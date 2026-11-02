<div class="mx-auto max-w-2xl space-y-6">
    <header class="space-y-1">
        <a href="{{ $processId ? route('bia.processes.show', $processId) : route('bia.processes.index') }}" class="text-sm text-gray-500 underline">{{ __('bia::processes.ui.back') }}</a>
        <h1 class="text-xl font-semibold">{{ __($processId ? 'bia::processes.ui.edit' : 'bia::processes.ui.new') }}</h1>
    </header>

    <form wire:submit="save" class="space-y-5 rounded-lg bg-white p-6 ring-1 ring-gray-200" novalidate>
        <div>
            <label for="name" class="block text-sm font-medium">{{ __('bia::processes.fields.name') }} <span class="text-red-600" aria-hidden="true">*</span></label>
            <input id="name" type="text" wire:model="name" maxlength="255" required class="mt-1 block w-full">
            @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="departmentId" class="block text-sm font-medium">{{ __('bia::processes.fields.department') }}</label>
                <select id="departmentId" wire:model="departmentId" class="mt-1 block w-full">
                    <option value="">—</option>
                    @foreach ($departments as $department)
                        <option value="{{ $department->id }}" wire:key="department-{{ $department->id }}">{{ $department->name }}</option>
                    @endforeach
                </select>
                @error('departmentId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="status" class="block text-sm font-medium">{{ __('bia::processes.fields.status') }}</label>
                <select id="status" wire:model="status" class="mt-1 block w-full">
                    <option value="active">{{ __('bia::processes.statuses.active') }}</option>
                    <option value="retired">{{ __('bia::processes.statuses.retired') }}</option>
                </select>
            </div>
            <div>
                <label for="ownerId" class="block text-sm font-medium">{{ __('bia::processes.fields.owner') }} <span class="text-red-600" aria-hidden="true">*</span></label>
                <select id="ownerId" wire:model="ownerId" required aria-describedby="ownerId-help" class="mt-1 block w-full">
                    <option value="">—</option>
                    @foreach ($people as $person)
                        <option value="{{ $person->id }}" wire:key="owner-{{ $person->id }}">{{ $person->name }}</option>
                    @endforeach
                </select>
                <p id="ownerId-help" class="mt-1 text-xs text-gray-500">{{ __('bia::processes.help.owner') }}</p>
                @error('ownerId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="deputyId" class="block text-sm font-medium">{{ __('bia::processes.fields.deputy') }}</label>
                <select id="deputyId" wire:model="deputyId" class="mt-1 block w-full">
                    <option value="">—</option>
                    @foreach ($people as $person)
                        <option value="{{ $person->id }}" wire:key="deputy-{{ $person->id }}">{{ $person->name }}</option>
                    @endforeach
                </select>
                @error('deputyId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>
        <div>
            <label for="description" class="block text-sm font-medium">{{ __('bia::processes.fields.description') }}</label>
            <textarea id="description" wire:model="description" rows="3" maxlength="5000" class="mt-1 block w-full"></textarea>
        </div>
        <div>
            <label for="peakPeriods" class="block text-sm font-medium">{{ __('bia::processes.fields.peak_periods') }}</label>
            <textarea id="peakPeriods" wire:model="peakPeriods" rows="2" maxlength="2000" aria-describedby="peakPeriods-help" class="mt-1 block w-full"></textarea>
            <p id="peakPeriods-help" class="mt-1 text-xs text-gray-500">{{ __('bia::processes.help.peak_periods') }}</p>
        </div>
        <button type="submit" wire:loading.attr="disabled" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white">{{ __('bia::processes.ui.save') }}</button>
    </form>
</div>
