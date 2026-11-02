<div class="space-y-6" @if ($preparing) wire:poll.3s @endif>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold">{{ __('bia::processes.title') }}</h1>
            <p class="text-sm text-gray-600">{{ __('bia::processes.ui.intro') }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <button type="button" wire:click="export('xlsx')" wire:loading.attr="disabled" class="rounded-md bg-white px-4 py-2 text-sm font-semibold ring-1 ring-gray-300">{{ __('bia::export.button') }}</button>
            @can('create', \Modules\Bia\Domain\Models\BusinessProcess::class)
                <a href="{{ route('bia.processes.create') }}" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white">{{ __('bia::processes.ui.new') }}</a>
            @endcan
        </div>
    </div>

    <dl class="grid grid-cols-2 gap-3 text-sm sm:grid-cols-3 lg:grid-cols-6">
        @foreach ([1, 2, 3, 4] as $tier)
            <div wire:key="tier-{{ $tier }}" class="rounded-lg bg-white p-3 ring-1 ring-gray-200">
                <dt class="text-xs text-gray-500">{{ __('bia::processes.tiers.'.$tier) }}</dt>
                <dd class="text-lg font-semibold">{{ $figures['by_tier'][$tier] ?? 0 }}</dd>
            </div>
        @endforeach
        <div class="rounded-lg bg-white p-3 ring-1 ring-gray-200">
            <dt class="text-xs text-gray-500">{{ __('bia::processes.summary.attention') }}</dt>
            <dd class="text-xs">
                {{ __('bia::processes.summary.never_assessed', ['count' => $figures['never_assessed']]) }}<br>
                <span @class(['font-semibold text-amber-700' => $figures['overdue'] > 0])>{{ __('bia::processes.summary.overdue', ['count' => $figures['overdue']]) }}</span>
            </dd>
        </div>
        <div class="rounded-lg bg-white p-3 ring-1 ring-gray-200">
            <dt class="text-xs text-gray-500">{{ __('bia::processes.summary.assessments') }}</dt>
            <dd class="text-xs">
                {{ __('bia::processes.summary.in_progress', ['count' => $figures['in_progress']]) }}<br>
                {{ __('bia::processes.summary.awaiting', ['count' => $figures['awaiting_approval']]) }}
            </dd>
        </div>
    </dl>

    @if ($exports->isNotEmpty())
        <ul class="divide-y divide-gray-100 rounded-lg bg-white px-4 text-sm ring-1 ring-gray-200">
            @foreach ($exports as $export)
                <li wire:key="export-{{ $export->id }}" class="flex flex-wrap items-center justify-between gap-2 py-2">
                    <span>{{ __('bia::export.of', ['date' => $export->created_at?->timezone($timezone)->format('Y-m-d H:i')]) }}</span>
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

    <div class="flex flex-wrap items-center gap-2">
        <input type="search" wire:model.live.debounce.400ms="search" maxlength="200" placeholder="{{ __('bia::processes.ui.search') }}" aria-label="{{ __('bia::processes.ui.search') }}" class="w-64 rounded-md text-sm">
        <select wire:model.live="department" aria-label="{{ __('bia::processes.fields.department') }}" class="rounded-md text-sm">
            <option value="">{{ __('bia::processes.ui.all_departments') }}</option>
            @foreach ($departments as $option)
                <option value="{{ $option->id }}" wire:key="department-{{ $option->id }}">{{ $option->name }}</option>
            @endforeach
        </select>
        <select wire:model.live="tier" aria-label="{{ __('bia::processes.fields.tier') }}" class="rounded-md text-sm">
            <option value="">{{ __('bia::processes.ui.all_tiers') }}</option>
            @foreach ($tiers as $option)
                <option value="{{ $option->value }}">{{ $option->label() }}</option>
            @endforeach
        </select>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model.live="due" class="rounded border-gray-300"> {{ __('bia::processes.ui.only_due') }}</label>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model.live="retired" class="rounded border-gray-300"> {{ __('bia::processes.ui.show_retired') }}</label>
    </div>

    <div class="overflow-x-auto rounded-lg bg-white ring-1 ring-gray-200">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs text-gray-500">
                <tr>
                    <th class="px-3 py-2">{{ __('bia::processes.fields.name') }}</th>
                    <th class="px-3 py-2">{{ __('bia::processes.fields.department') }}</th>
                    <th class="px-3 py-2">{{ __('bia::processes.fields.owner') }}</th>
                    <th class="px-3 py-2">{{ __('bia::processes.fields.tier') }}</th>
                    <th class="px-3 py-2">{{ __('bia::processes.fields.rto') }}</th>
                    <th class="px-3 py-2">{{ __('bia::processes.fields.rpo') }}</th>
                    <th class="px-3 py-2">{{ __('bia::processes.fields.next_review') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($processes as $process)
                    <tr wire:key="process-{{ $process->id }}" class="border-t border-gray-100">
                        <td class="px-3 py-2">
                            <a href="{{ route('bia.processes.show', $process) }}" class="font-medium text-blue-700 underline">{{ $process->name }}</a>
                            @if ($process->status === 'retired') <span class="ml-1 text-xs text-gray-500">{{ __('bia::processes.statuses.retired') }}</span> @endif
                            @if ($open->has($process->id)) <span class="ml-1"><x-bia::assessment-status :status="$open->get($process->id)->status" /></span> @endif
                        </td>
                        <td class="px-3 py-2">{{ $process->department?->name ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $process->owner?->name ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $process->tier?->value ?? '—' }}</td>
                        <td class="px-3 py-2">{{ \Modules\Core\Domain\Time\Duration::format($process->rto_minutes) }}</td>
                        <td class="px-3 py-2">{{ \Modules\Core\Domain\Time\Duration::format($process->rpo_minutes) }}</td>
                        <td @class(['px-3 py-2', 'font-medium text-amber-700' => $process->next_review_on !== null && $process->next_review_on->isPast()])>{{ $process->next_review_on?->toDateString() ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-3 py-6 text-center text-gray-500">{{ __('bia::processes.ui.empty') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $processes->links() }}
</div>
