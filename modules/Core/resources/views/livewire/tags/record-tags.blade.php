<section aria-labelledby="tags-heading" class="space-y-2 rounded-lg bg-white p-4 ring-1 ring-gray-200">
    <h2 id="tags-heading" class="text-sm font-semibold">{{ __('core::tags.title') }}</h2>

    @if ($tags->isEmpty())
        <p class="text-sm text-gray-500">{{ __('core::tags.none') }}</p>
    @else
        <ul class="flex flex-wrap gap-2">
            @foreach ($tags as $tag)
                <li wire:key="tag-{{ $tag->id }}" class="inline-flex items-center gap-1 rounded bg-gray-100 px-2 py-0.5 text-xs">
                    <a href="{{ route('core.tags.show', $tag) }}" class="text-gray-800 hover:underline">{{ $tag->name }}</a>
                    @if ($canChange)
                        <button type="button" wire:click="remove('{{ $tag->id }}')" class="text-gray-500 hover:text-red-700" aria-label="{{ __('core::tags.remove', ['name' => $tag->name]) }}">×</button>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif

    @if ($canChange)
        <form wire:submit="add" class="flex items-center gap-2">
            <label for="tag-name-{{ $this->getId() }}" class="sr-only">{{ __('core::tags.add_label') }}</label>
            <input id="tag-name-{{ $this->getId() }}" type="text" wire:model="name" list="tag-suggestions-{{ $this->getId() }}" maxlength="50" placeholder="{{ __('core::tags.add_label') }}" class="w-56 rounded-md border border-gray-300 px-2 py-1 text-sm">
            <datalist id="tag-suggestions-{{ $this->getId() }}">
                @foreach ($suggestions as $suggestion)
                    <option value="{{ $suggestion }}"></option>
                @endforeach
            </datalist>
            <button type="submit" class="rounded-md bg-white px-3 py-1 text-sm font-medium ring-1 ring-gray-300 hover:bg-gray-50">{{ __('core::tags.add') }}</button>
        </form>
        @error('name') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
    @endif
</section>
