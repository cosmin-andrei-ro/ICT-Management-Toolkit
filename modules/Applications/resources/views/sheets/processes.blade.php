{{-- 10.1: business processes of the BIA the application supports. --}}
@php($ids = $editable ? ($form[$section->key][$field->key] ?? []) : ($sheet->section($section->key)[$field->key] ?? []))
@php($known = $insights['processes'])
@if (! $processesAvailable)
    <p class="text-sm text-gray-500">{{ __('applications::sheet.ui.bia_off') }}</p>
@else
    <ul class="space-y-1 text-sm">
        @forelse ((array) $ids as $id)
            @php($process = is_string($id) ? ($known[$id] ?? null) : null)
            <li wire:key="process-{{ $id }}" class="flex flex-wrap items-center justify-between gap-2">
                @if ($process) <a href="{{ $process->url }}" class="text-blue-700 underline">{{ $process->name }}</a> @else <span class="text-gray-500">{{ __('applications::sheet.ui.unknown') }}</span> @endif
                @if ($editable) <button type="button" wire:click="removeProcess('{{ $section->key }}', '{{ $field->key }}', '{{ $id }}')" class="text-xs text-red-700 underline">{{ __('applications::sheet.ui.remove_row') }}</button> @endif
            </li>
        @empty
            <li class="text-gray-500">{{ __('applications::sheet.ui.no_processes') }}</li>
        @endforelse
    </ul>
    @if ($editable)
        <input type="search" wire:model.live.debounce.300ms="processSearch" maxlength="100" placeholder="{{ __('applications::sheet.ui.find_process') }}" aria-label="{{ __('applications::sheet.ui.find_process') }}" class="mt-2 block w-full text-sm">
        @if ($processCandidates !== [])
            <ul class="mt-1 divide-y divide-gray-100 rounded-md bg-white text-sm ring-1 ring-gray-200">
                @foreach ($processCandidates as $candidate)
                    <li wire:key="process-candidate-{{ $candidate->id }}" class="flex items-center justify-between gap-2 px-2 py-1.5">
                        <span>{{ $candidate->name }}</span>
                        <button type="button" wire:click="addProcess('{{ $section->key }}', '{{ $field->key }}', '{{ $candidate->id }}')" class="text-xs text-blue-700 underline">{{ __('applications::sheet.ui.link') }}</button>
                    </li>
                @endforeach
            </ul>
        @endif
    @endif
@endif
