<div class="mx-auto max-w-2xl space-y-6">
    <header class="space-y-1">
        <a href="{{ route('ticketing.catalog.index') }}" class="text-sm text-gray-500 underline">{{ __('ticketing::catalog.back') }}</a>
        <h1 class="text-xl font-semibold">{{ $service->name }}</h1>
        @if ($service->description)
            <p class="whitespace-pre-line text-sm text-gray-700">{{ $service->description }}</p>
        @endif
    </header>

    <form wire:submit="submit" class="space-y-5 rounded-lg bg-white p-6 ring-1 ring-gray-200" novalidate>
        <x-core::form-fields :fields="$fields" model="answers" />

        <div>
            <label for="details" class="block text-sm font-medium">{{ __('ticketing::catalog.details') }}</label>
            <textarea id="details" wire:model="details" rows="3" class="mt-1 block w-full rounded-md border-gray-300"></textarea>
            @error('details') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="files" class="block text-sm font-medium">{{ __('ticketing::ui.fields.attachments') }}</label>
            <input id="files" type="file" wire:model="files" multiple class="mt-1 block w-full text-sm">
            @error('files.*') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <button type="submit" wire:loading.attr="disabled" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700 disabled:opacity-60">{{ __('ticketing::catalog.submit') }}</button>
    </form>
</div>
