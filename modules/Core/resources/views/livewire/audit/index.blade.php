<div class="space-y-6" @if ($preparing) wire:poll.3s @endif>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold">{{ __('core::audit.title') }}</h1>
            <p class="text-sm text-gray-600">{{ __('core::audit.intro') }}</p>
        </div>
        <button type="button" wire:click="export" wire:loading.attr="disabled" class="rounded-md bg-white px-4 py-2 text-sm font-semibold ring-1 ring-gray-300">{{ __('core::audit.export') }}</button>
    </div>

    @if ($exports->isNotEmpty())
        <ul class="divide-y divide-gray-100 rounded-lg bg-white px-4 text-sm ring-1 ring-gray-200">
            @foreach ($exports as $export)
                <li wire:key="export-{{ $export->id }}" class="flex flex-wrap items-center justify-between gap-2 py-2">
                    <span>{{ __('core::audit.export_of', ['date' => $export->created_at?->timezone($timezone)->format('Y-m-d H:i')]) }}</span>
                    @if ($export->isDownloadable())
                        <span class="text-gray-500">
                            <a href="{{ route('core.exports.download', $export) }}" class="font-medium text-blue-700 underline">{{ __('core::exports.download') }}</a>
                            · {{ __('core::exports.expires', ['date' => $export->expires_at?->timezone($timezone)->format('Y-m-d H:i')]) }}
                        </span>
                    @else
                        <span class="text-gray-500">{{ $export->status->label() }}</span>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif

    <div class="flex flex-wrap items-end gap-4 text-sm">
        <label class="flex flex-col gap-1">
            <span class="text-gray-600">{{ __('core::audit.filters.category') }}</span>
            <select wire:model.live="category" class="rounded-md border-gray-300 text-sm">
                @foreach (['security', 'changes', 'all'] as $option)
                    <option value="{{ $option }}">{{ __('core::audit.categories.'.$option) }}</option>
                @endforeach
            </select>
        </label>
        <label class="flex flex-col gap-1">
            <span class="text-gray-600">{{ __('core::audit.filters.event') }}</span>
            <select wire:model.live="event" class="rounded-md border-gray-300 text-sm">
                <option value="">{{ __('core::audit.filters.any_event') }}</option>
                @foreach ($events as $option)
                    <option value="{{ $option->value }}">{{ $option->label() }}</option>
                @endforeach
            </select>
        </label>
        <label class="flex flex-col gap-1">
            <span class="text-gray-600">{{ __('core::audit.filters.person') }}</span>
            <input type="search" wire:model.live.debounce.400ms="person" maxlength="200" placeholder="{{ __('core::audit.filters.person_placeholder') }}" class="rounded-md border-gray-300 text-sm">
        </label>
        <label class="flex flex-col gap-1">
            <span class="text-gray-600">{{ __('core::audit.filters.type') }}</span>
            <select wire:model.live="type" class="max-w-56 rounded-md border-gray-300 text-sm">
                <option value="">{{ __('core::audit.filters.any_type') }}</option>
                @foreach ($types as $alias => $label)
                    <option value="{{ $alias }}">{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label class="flex flex-col gap-1">
            <span class="text-gray-600">{{ __('core::audit.filters.from') }}</span>
            <input type="date" wire:model.live="from" class="rounded-md border-gray-300 text-sm">
        </label>
        <label class="flex flex-col gap-1">
            <span class="text-gray-600">{{ __('core::audit.filters.until') }}</span>
            <input type="date" wire:model.live="until" class="rounded-md border-gray-300 text-sm">
        </label>
    </div>

    <div class="overflow-x-auto rounded-lg bg-white ring-1 ring-gray-200">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs text-gray-500">
                <tr>
                    <th class="px-3 py-2">{{ __('core::audit.fields.when') }}</th>
                    <th class="px-3 py-2">{{ __('core::audit.fields.event') }}</th>
                    <th class="px-3 py-2">{{ __('core::audit.fields.person') }}</th>
                    <th class="px-3 py-2">{{ __('core::audit.fields.record_type') }}</th>
                    <th class="px-3 py-2">{{ __('core::audit.fields.details') }}</th>
                    <th class="px-3 py-2">{{ __('core::audit.fields.ip_address') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($entries as $entry)
                    <tr wire:key="entry-{{ $entry->id }}" class="border-t border-gray-100 align-top">
                        <td class="whitespace-nowrap px-3 py-2">{{ $entry->created_at->timezone($timezone)->format('Y-m-d H:i:s') }}</td>
                        <td @class(['px-3 py-2', 'font-medium text-red-800' => in_array($entry->event->value, ['login_failed', 'lockout', 'login_denied', 'mfa_failed'], true)])>{{ $entry->event->label() }}</td>
                        <td class="px-3 py-2">
                            @if ($entry->user)
                                {{ $entry->user->name }}<span class="block text-xs text-gray-500">{{ $entry->user->email }}</span>
                            @else
                                <span class="text-gray-500">{{ __('core::audit.system') }}</span>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-gray-700">{{ \Modules\Core\Http\Livewire\Audit\AuditLogIndex::typeLabel($entry->auditable_type) }}</td>
                        <td class="px-3 py-2">
                            @foreach ($changes->rows($entry) as $row)
                                <p class="text-xs break-words"><span class="text-gray-500">{{ $row['field'] }}:</span>
                                    @if ($row['old'] !== null) <span class="text-gray-500 line-through">{{ $row['old'] }}</span> → @endif
                                    {{ $row['new'] ?? '' }}</p>
                            @endforeach
                        </td>
                        <td class="whitespace-nowrap px-3 py-2 font-mono text-xs text-gray-600">{{ $entry->ip_address ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-3 py-6 text-center text-gray-500">{{ __('core::audit.empty') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $entries->links() }}
</div>
