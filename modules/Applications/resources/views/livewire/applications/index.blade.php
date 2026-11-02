<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold">{{ __('applications::applications.title') }}</h1>
            <p class="text-sm text-gray-600">{{ __('applications::applications.ui.intro') }}</p>
        </div>
        @can('create', \Modules\Applications\Domain\Models\Application::class)
            <a href="{{ route('applications.create') }}" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white">{{ __('applications::applications.ui.new') }}</a>
        @endcan
    </div>

    <div class="flex flex-wrap items-end gap-3">
        <div>
            <label for="search" class="block text-xs text-gray-600">{{ __('applications::applications.ui.search') }}</label>
            <input id="search" type="search" wire:model.live.debounce.400ms="search" maxlength="200" class="mt-1 block w-64">
        </div>
        <div>
            <label for="status" class="block text-xs text-gray-600">{{ __('applications::applications.fields.status') }}</label>
            <select id="status" wire:model.live="status" class="mt-1 block">
                <option value="">{{ __('applications::applications.ui.all') }}</option>
                @foreach ($statuses as $key => $label) <option value="{{ $key }}" wire:key="status-{{ $key }}">{{ $label }}</option> @endforeach
            </select>
        </div>
        <div>
            <label for="criticality" class="block text-xs text-gray-600">{{ __('applications::applications.fields.criticality') }}</label>
            <select id="criticality" wire:model.live="criticality" class="mt-1 block">
                <option value="">{{ __('applications::applications.ui.all') }}</option>
                @foreach ($criticalities as $key => $label) <option value="{{ $key }}" wire:key="criticality-{{ $key }}">{{ $label }}</option> @endforeach
            </select>
        </div>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model.live="due"> {{ __('applications::applications.ui.due_only') }}</label>
    </div>

    <div class="overflow-x-auto rounded-lg bg-white ring-1 ring-gray-200">
        <table class="min-w-full text-left text-sm">
            <thead class="text-xs text-gray-500">
                <tr>
                    <th scope="col" class="px-4 py-2 font-medium">{{ __('applications::applications.fields.name') }}</th>
                    <th scope="col" class="px-4 py-2 font-medium">{{ __('applications::applications.fields.department') }}</th>
                    <th scope="col" class="px-4 py-2 font-medium">{{ __('applications::applications.fields.owners') }}</th>
                    <th scope="col" class="px-4 py-2 font-medium">{{ __('applications::applications.fields.status') }}</th>
                    <th scope="col" class="px-4 py-2 font-medium">{{ __('applications::applications.fields.criticality') }}</th>
                    <th scope="col" class="px-4 py-2 font-medium">{{ __('applications::applications.fields.version') }}</th>
                    <th scope="col" class="px-4 py-2 font-medium">{{ __('applications::applications.fields.next_review') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($applications as $application)
                    <tr wire:key="application-{{ $application->id }}">
                        <td class="px-4 py-2"><a href="{{ route('applications.show', $application) }}" class="text-blue-700 underline">{{ $application->name }}</a> <span class="block text-xs text-gray-500">{{ $application->code }}</span></td>
                        <td class="px-4 py-2">{{ $application->department?->name ?? '—' }}</td>
                        <td class="px-4 py-2 text-xs">{{ $application->businessOwner?->name ?? '—' }}<br>{{ $application->technicalOwner?->name ?? '—' }}</td>
                        <td class="px-4 py-2">{{ $options->label('application_status', $application->status) ?? '—' }}</td>
                        <td class="px-4 py-2">{{ $options->label('business_criticality', $application->criticality) ?? '—' }}</td>
                        <td class="px-4 py-2">
                            {{ $application->current_version ? 'v'.$application->current_version : __('applications::applications.ui.not_documented') }}
                            @if ($open->has($application->id)) <span class="block"><x-applications::sheet-status :status="$open->get($application->id)->status" /></span> @endif
                        </td>
                        <td @class(['px-4 py-2', 'font-medium text-amber-700' => $application->next_review_on?->isPast()])>{{ $application->next_review_on?->toDateString() ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-3 text-gray-500">{{ __('applications::applications.ui.none') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $applications->links() }}
</div>
