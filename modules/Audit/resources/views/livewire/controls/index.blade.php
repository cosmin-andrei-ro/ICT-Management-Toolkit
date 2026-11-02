<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <a href="{{ route('audit.index') }}" class="text-sm text-gray-500 underline">{{ __('audit::compliance.ui.back') }}</a>
            <h1 class="text-xl font-semibold">{{ __('audit::compliance.controls.title') }}</h1>
            <p class="text-sm text-gray-600">{{ __('audit::compliance.controls.intro') }}</p>
        </div>
        @can('create', \Modules\Audit\Domain\Models\Control::class)
            <a href="{{ route('audit.controls.create') }}" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white">{{ __('audit::compliance.controls.new') }}</a>
        @endcan
    </div>

    <div class="flex flex-wrap items-end gap-3">
        <div>
            <label for="search" class="block text-xs text-gray-600">{{ __('audit::compliance.ui.search') }}</label>
            <input id="search" type="search" wire:model.live.debounce.400ms="search" maxlength="200" class="mt-1 block w-56">
        </div>
        <div>
            <label for="status" class="block text-xs text-gray-600">{{ __('audit::compliance.controls.fields.status') }}</label>
            <select id="status" wire:model.live="status" class="mt-1 block">
                <option value="">{{ __('audit::compliance.ui.all') }}</option>
                @foreach ($statuses as $option) <option value="{{ $option->value }}" wire:key="status-{{ $option->value }}">{{ $option->label() }}</option> @endforeach
            </select>
        </div>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model.live="unmapped"> {{ __('audit::compliance.controls.unmapped') }}</label>
    </div>

    <div class="overflow-x-auto rounded-lg bg-white ring-1 ring-gray-200">
        <table class="min-w-full text-left text-sm">
            <thead class="text-xs text-gray-500">
                <tr>
                    <th scope="col" class="px-4 py-2 font-medium">{{ __('audit::compliance.controls.fields.control') }}</th>
                    <th scope="col" class="px-4 py-2 font-medium">{{ __('audit::compliance.controls.fields.owner') }}</th>
                    <th scope="col" class="px-4 py-2 font-medium">{{ __('audit::compliance.controls.fields.status') }}</th>
                    <th scope="col" class="px-4 py-2 font-medium">{{ __('audit::compliance.controls.fields.requirements') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($controls as $control)
                    <tr wire:key="control-{{ $control->id }}">
                        <td class="px-4 py-2"><a href="{{ route('audit.controls.show', $control) }}" class="text-blue-700 underline">{{ $control->code() }} · {{ $control->name }}</a></td>
                        <td class="px-4 py-2">{{ $control->owner?->name ?? '—' }}</td>
                        <td class="px-4 py-2"><x-audit::status :status="$control->status" /></td>
                        <td class="px-4 py-2">{{ $control->requirements_count }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-3 text-gray-500">{{ __('audit::compliance.controls.none') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $controls->links() }}
</div>
