<div class="space-y-6" @if ($preparing) wire:poll.3s @endif>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold">{{ __('applications::applications.title') }}</h1>
            <p class="text-sm text-gray-600">{{ __('applications::applications.ui.intro') }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <button type="button" wire:click="export('xlsx')" wire:loading.attr="disabled" class="rounded-md bg-white px-4 py-2 text-sm font-semibold ring-1 ring-gray-300">{{ __('applications::export.button') }}</button>
            @can('create', \Modules\Applications\Domain\Models\Application::class)
                <a href="{{ route('applications.create') }}" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white">{{ __('applications::applications.ui.new') }}</a>
            @endcan
        </div>
    </div>

    @if ($exports->isNotEmpty())
        <ul class="divide-y divide-gray-100 rounded-lg bg-white px-4 text-sm ring-1 ring-gray-200">
            @foreach ($exports as $export)
                <li wire:key="export-{{ $export->id }}" class="flex flex-wrap items-center justify-between gap-2 py-2">
                    <span>{{ __('applications::export.of', ['date' => $export->created_at?->timezone($timezone)->format('Y-m-d H:i')]) }}</span>
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
        <div>
            <label for="exposure" class="block text-xs text-gray-600">{{ __('applications::applications.fields.exposure') }}</label>
            <select id="exposure" wire:model.live="exposure" class="mt-1 block">
                <option value="">{{ __('applications::applications.ui.all') }}</option>
                @foreach ($exposures as $key => $label) <option value="{{ $key }}" wire:key="exposure-{{ $key }}">{{ $label }}</option> @endforeach
            </select>
        </div>
        <div>
            <label for="hosting" class="block text-xs text-gray-600">{{ __('applications::applications.fields.hosting') }}</label>
            <select id="hosting" wire:model.live="hosting" class="mt-1 block">
                <option value="">{{ __('applications::applications.ui.all') }}</option>
                @foreach ($hostings as $key => $label) <option value="{{ $key }}" wire:key="hosting-{{ $key }}">{{ $label }}</option> @endforeach
            </select>
        </div>
        <div>
            <label for="classification" class="block text-xs text-gray-600">{{ __('applications::applications.fields.classification') }}</label>
            <select id="classification" wire:model.live="classification" class="mt-1 block">
                <option value="">{{ __('applications::applications.ui.all') }}</option>
                @foreach ($classifications as $key => $label) <option value="{{ $key }}" wire:key="classification-{{ $key }}">{{ $label }}</option> @endforeach
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
                    <th scope="col" class="px-4 py-2 font-medium">{{ __('applications::applications.fields.exposure') }}</th>
                    <th scope="col" class="px-4 py-2 font-medium">{{ __('applications::applications.fields.hosting') }}</th>
                    <th scope="col" class="px-4 py-2 font-medium">{{ __('applications::applications.fields.classification') }}</th>
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
                        @php($inForce = $application->sheetInForce)
                        <td class="px-4 py-2 text-xs">{{ collect((array) ($inForce?->section('1_3')['exposure'] ?? []))->filter(fn ($value) => isset($exposures[$value]))->map(fn ($value) => $exposures[$value])->implode(', ') ?: '—' }}</td>
                        <td class="px-4 py-2">{{ $options->label('hosting_models', is_string($inForce?->section('5_1')['hosting_model'] ?? null) ? $inForce->section('5_1')['hosting_model'] : null) ?? '—' }}</td>
                        <td class="px-4 py-2">{{ $options->label('data_classification', is_string($inForce?->section('2_1')['classification'] ?? null) ? $inForce->section('2_1')['classification'] : null) ?? '—' }}</td>
                        <td class="px-4 py-2">
                            {{ $application->current_version ? 'v'.$application->current_version : __('applications::applications.ui.not_documented') }}
                            @if ($open->has($application->id)) <span class="block"><x-applications::sheet-status :status="$open->get($application->id)->status" /></span> @endif
                        </td>
                        <td @class(['px-4 py-2', 'font-medium text-amber-700' => $application->next_review_on?->isPast()])>{{ $application->next_review_on?->toDateString() ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="px-4 py-3 text-gray-500">{{ __('applications::applications.ui.none') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $applications->links() }}
</div>
