<div class="mx-auto max-w-2xl space-y-6">
    <h1 class="text-xl font-semibold">{{ __('ticketing::ui.portal.new') }}</h1>

    <form wire:submit="save" class="space-y-5 rounded-lg bg-white p-6 ring-1 ring-gray-200">
        <fieldset>
            <legend class="text-sm font-medium">{{ __('ticketing::ui.fields.type') }}</legend>
            <div class="mt-2 flex gap-6">
                @foreach ($types as $option)
                    <label class="flex items-center gap-2 text-sm">
                        <input type="radio" wire:model="type" value="{{ $option->value }}"> {{ $option->label() }}
                    </label>
                @endforeach
            </div>
        </fieldset>

        <div>
            <label for="title" class="block text-sm font-medium">{{ __('ticketing::ui.fields.title') }}</label>
            <input id="title" type="text" wire:model="title" maxlength="200" class="mt-1 block w-full rounded-md border-gray-300">
            @error('title') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="description" class="block text-sm font-medium">{{ __('ticketing::ui.fields.description') }}</label>
            <textarea id="description" wire:model="description" rows="6" class="mt-1 block w-full rounded-md border-gray-300"></textarea>
            @error('description') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="category" class="block text-sm font-medium">{{ __('ticketing::ui.fields.category') }}</label>
            <select id="category" wire:model="categoryId" class="mt-1 block w-full rounded-md border-gray-300">
                <option value="">{{ __('ticketing::ui.not_sure') }}</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}">{{ $category->fullName() }}</option>
                @endforeach
            </select>
            @error('categoryId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="impact" class="block text-sm font-medium">{{ __('ticketing::ui.fields.impact_question') }}</label>
                <select id="impact" wire:model="impact" class="mt-1 block w-full rounded-md border-gray-300">
                    @foreach ($impacts as $option)
                        <option value="{{ $option->value }}">{{ $option->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="urgency" class="block text-sm font-medium">{{ __('ticketing::ui.fields.urgency_question') }}</label>
                <select id="urgency" wire:model="urgency" class="mt-1 block w-full rounded-md border-gray-300">
                    @foreach ($urgencies as $option)
                        <option value="{{ $option->value }}">{{ $option->label() }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div>
            <label for="files" class="block text-sm font-medium">{{ __('ticketing::ui.fields.attachments') }}</label>
            <input id="files" type="file" wire:model="files" multiple class="mt-1 block w-full text-sm">
            @error('files') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            @error('files.*') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <button type="submit" wire:loading.attr="disabled" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700 disabled:opacity-60">
            {{ __('ticketing::ui.portal.submit') }}
        </button>
    </form>
</div>
