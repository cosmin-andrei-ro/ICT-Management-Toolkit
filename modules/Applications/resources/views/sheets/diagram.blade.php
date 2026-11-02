{{-- A diagram: drawn from the sheet where the section knows how (2.2), plus the team's own image and .drawio file. --}}
@php($value = $editable ? ($form[$section->key][$field->key] ?? []) : ($sheet->section($section->key)[$field->key] ?? []))
@php($image = is_string($value['image'] ?? null) && $value['image'] !== '' ? $files->get($value['image']) : null)
@php($source = is_string($value['source'] ?? null) && $value['source'] !== '' ? $files->get($value['source']) : null)
<div class="space-y-4">
    @if ($section->key === '2_2')
        @if ($integrations === [])
            <p class="text-sm text-gray-500">{{ __('applications::sheet.ui.dfd_empty') }}</p>
        @else
            @include('applications::sheets.dataflow', ['integrations' => $integrations, 'application' => $application])
        @endif
    @endif

    @if ($section->key === '4_2')
        @if ($firewallRules === [])
            <p class="text-sm text-gray-500">{{ __('applications::sheet.ui.network_empty') }}</p>
        @else
            @include('applications::sheets.network', ['rules' => $firewallRules, 'application' => $application, 'options' => $options])
        @endif
    @endif

    <div class="space-y-2">
        <p class="text-xs font-medium text-gray-600">{{ __('applications::sheet.ui.own_diagram') }}</p>
        @if ($image)
            <figure class="space-y-1">
                <img src="{{ route('applications.diagrams.image', $image) }}" alt="{{ __('applications::sheet.ui.own_diagram_alt', ['section' => $section->title()]) }}" class="max-h-[32rem] rounded border border-gray-200">
                <figcaption class="text-xs text-gray-500">{{ $image->original_name }}</figcaption>
            </figure>
        @endif
        <p class="flex flex-wrap items-center gap-3 text-sm">
            @if ($source)
                <a href="{{ route('core.attachments.download', $source) }}" class="text-blue-700 underline">{{ __('applications::sheet.ui.download_source', ['name' => $source->original_name]) }}</a>
            @endif
            @if ($editable && $image) <button type="button" wire:click="removeDiagramFile('{{ $section->key }}', '{{ $field->key }}', 'image')" class="text-xs text-red-700 underline">{{ __('applications::sheet.ui.remove_image') }}</button> @endif
            @if ($editable && $source) <button type="button" wire:click="removeDiagramFile('{{ $section->key }}', '{{ $field->key }}', 'source')" class="text-xs text-red-700 underline">{{ __('applications::sheet.ui.remove_source') }}</button> @endif
            @if (! $image && ! $source && ! $editable) <span class="text-gray-400">—</span> @endif
        </p>
        @if ($editable)
            <div class="grid gap-3 rounded-md bg-gray-50 p-3 sm:grid-cols-2">
                <div>
                    <label for="diagram-image-{{ $section->key }}" class="block text-xs font-medium">{{ __('applications::sheet.ui.image_file') }}</label>
                    <input id="diagram-image-{{ $section->key }}" type="file" wire:model="diagramImage" accept="image/png,image/jpeg,image/webp,image/gif" class="mt-1 block w-full text-sm">
                    @error('diagramImage') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="diagram-source-{{ $section->key }}" class="block text-xs font-medium">{{ __('applications::sheet.ui.source_file') }}</label>
                    <input id="diagram-source-{{ $section->key }}" type="file" wire:model="diagramSource" accept=".drawio,.xml" class="mt-1 block w-full text-sm">
                    @error('diagramSource') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-2">
                    <button type="button" wire:click="attachDiagram('{{ $section->key }}', '{{ $field->key }}')" class="rounded-md bg-white px-3 py-1.5 text-sm font-semibold ring-1 ring-gray-300">{{ __('applications::sheet.ui.attach') }}</button>
                    <span wire:loading wire:target="diagramImage,diagramSource,attachDiagram" class="text-xs text-gray-500">{{ __('applications::sheet.ui.uploading') }}</span>
                </div>
            </div>
        @endif
    </div>
</div>
