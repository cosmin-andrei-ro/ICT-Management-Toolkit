<div class="space-y-4">
    <header class="space-y-1">
        <a href="{{ $pageId ? route('documentation.pages.show', $pageId) : route('documentation.spaces.show', $space) }}" class="text-sm text-gray-500 underline">{{ __('documentation::docs.back') }}</a>
        <h1 class="text-xl font-semibold">{{ __($pageId ? 'documentation::docs.pages.edit' : 'documentation::docs.pages.new') }} <span class="text-base font-normal text-gray-500">· {{ $space->name }}</span></h1>
    </header>

    <form wire:submit="save" class="space-y-4" novalidate>
        <div class="grid gap-4 sm:grid-cols-3">
            <div class="sm:col-span-2">
                <label for="title" class="block text-sm font-medium">{{ __('documentation::docs.pages.title_field') }} <span class="text-red-600" aria-hidden="true">*</span></label>
                <input id="title" type="text" wire:model="title" maxlength="255" class="mt-1 block w-full">
                @error('title') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="parentId" class="block text-sm font-medium">{{ __('documentation::docs.pages.parent') }}</label>
                <select id="parentId" wire:model="parentId" class="mt-1 block w-full">
                    <option value="">{{ __('documentation::docs.pages.top_level') }}</option>
                    @foreach ($parents as $parent) <option value="{{ $parent->id }}" wire:key="parent-{{ $parent->id }}">{{ $parent->title }}</option> @endforeach
                </select>
                @error('parentId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        @error('body') <p class="rounded-md bg-red-50 p-3 text-sm text-red-800" role="alert">{{ $message }}</p> @enderror

        <div class="grid gap-4 lg:grid-cols-2">
            <div x-data="markdownToolbar" class="space-y-1">
                <div role="toolbar" aria-label="{{ __('documentation::docs.editor.toolbar') }}" class="flex flex-wrap gap-1">
                    @foreach ([
                        ['heading', '', '', '## ', ''],
                        ['bold', '**', '**', '', 'text'],
                        ['italic', '_', '_', '', 'text'],
                        ['code', '`', '`', '', 'code'],
                        ['link', '[', '](https://)', '', 'text'],
                        ['bullets', '', '', '- ', ''],
                        ['numbers', '', '', '1. ', ''],
                        ['checklist', '', '', '- [ ] ', ''],
                        ['quote', '', '', '> ', ''],
                        ['block', "```\n", "\n```", '', 'code'],
                        ['table', '', "\n\n| Column | Column |\n| --- | --- |\n| | |\n", '', ''],
                    ] as [$name, $before, $after, $line, $placeholder])
                        <button type="button" x-on:click="apply" data-before="{{ $before }}" data-after="{{ $after }}" data-line="{{ $line }}" data-placeholder="{{ $placeholder }}" wire:key="tool-{{ $name }}" class="rounded bg-white px-2 py-1 text-xs font-semibold ring-1 ring-gray-300">{{ __('documentation::docs.editor.'.$name) }}</button>
                    @endforeach
                </div>
                <label for="body" class="sr-only">{{ __('documentation::docs.pages.body') }}</label>
                <textarea id="body" x-ref="body" wire:model.live.debounce.800ms="body" rows="24" class="block w-full font-mono text-sm" spellcheck="true"></textarea>
                <p class="text-xs text-gray-500">{{ __('documentation::docs.editor.help') }}</p>
            </div>
            <section aria-label="{{ __('documentation::docs.editor.preview') }}" class="space-y-1">
                <p class="text-xs font-semibold uppercase text-gray-500">{{ __('documentation::docs.editor.preview') }}</p>
                {{-- Markdown::toSafeHtml(): raw HTML stripped, unsafe links dropped (CLAUDE.md §6). --}}
                <div class="markdown-content max-h-[38rem] overflow-y-auto rounded-lg bg-white p-4 ring-1 ring-gray-200">{!! $preview !!}</div>
            </section>
        </div>

        <div class="flex flex-wrap items-end gap-3">
            <div class="grow">
                <label for="summary" class="block text-sm font-medium">{{ __('documentation::docs.pages.summary') }}</label>
                <input id="summary" type="text" wire:model="summary" maxlength="500" placeholder="{{ __('documentation::docs.pages.summary_example') }}" class="mt-1 block w-full">
            </div>
            <button type="submit" wire:loading.attr="disabled" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white">{{ __('documentation::docs.save') }}</button>
        </div>
    </form>
</div>
