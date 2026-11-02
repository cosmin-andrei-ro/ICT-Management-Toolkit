@php($duration = fn (?int $minutes): string => \Modules\Core\Domain\Time\Duration::format($minutes))
<div class="space-y-6" @if ($preparing) wire:poll.3s @endif>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold">{{ __('bcm::coverage.title') }}</h1>
            <p class="text-sm text-gray-600">{{ __('bcm::coverage.intro') }}</p>
        </div>
        <button type="button" wire:click="export('xlsx')" wire:loading.attr="disabled" class="rounded-md bg-white px-4 py-2 text-sm font-semibold ring-1 ring-gray-300">{{ __('bcm::export.button') }}</button>
    </div>

    @if ($exports->isNotEmpty())
        <ul class="divide-y divide-gray-100 rounded-lg bg-white px-4 text-sm ring-1 ring-gray-200">
            @foreach ($exports as $export)
                <li wire:key="export-{{ $export->id }}" class="flex flex-wrap items-center justify-between gap-2 py-2">
                    <span>{{ __('bcm::export.of', ['date' => $export->created_at?->timezone($timezone)->format('Y-m-d H:i')]) }}</span>
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

    <dl class="grid gap-3 sm:grid-cols-3 lg:grid-cols-6">
        @foreach (['uncovered', 'not_in_force', 'review_overdue', 'exercise_overdue', 'awaiting_approval', 'open_findings'] as $key)
            <div wire:key="figure-{{ $key }}" @class(['rounded-lg p-3 ring-1', 'bg-amber-50 ring-amber-200' => $figures[$key] > 0, 'bg-white ring-gray-200' => $figures[$key] === 0])>
                <dt class="text-xs text-gray-600">{{ __('bcm::coverage.figures.'.$key) }}</dt>
                <dd class="text-2xl font-semibold">{{ $figures[$key] }}</dd>
            </div>
        @endforeach
    </dl>

    <section aria-labelledby="plans-heading" class="space-y-2 rounded-lg bg-white p-4 ring-1 ring-gray-200">
        <h2 id="plans-heading" class="text-sm font-semibold">{{ __('bcm::coverage.plans_heading') }}</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="text-xs text-gray-500">
                    <tr>
                        <th scope="col" class="py-2 pr-4 font-medium">{{ __('bcm::plans.fields.name') }}</th>
                        <th scope="col" class="py-2 pr-4 font-medium">{{ __('bcm::plans.fields.approved_version') }}</th>
                        <th scope="col" class="py-2 pr-4 font-medium">{{ __('bcm::plans.fields.next_review') }}</th>
                        <th scope="col" class="py-2 pr-4 font-medium">{{ __('bcm::exercises.fields.last_exercised') }}</th>
                        <th scope="col" class="py-2 pr-4 font-medium">{{ __('bcm::coverage.next_exercise') }}</th>
                        <th scope="col" class="py-2 pr-4 font-medium">{{ __('bcm::coverage.recovery') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($plans as $standing)
                        @php($plan = $standing->plan)
                        <tr wire:key="plan-{{ $plan->id }}">
                            <td class="py-2 pr-4"><a href="{{ route('bcm.plans.show', $plan) }}" class="text-blue-700 underline">{{ $plan->name }}</a> <span class="text-xs text-gray-500">{{ __('bcm::plans.type_short.'.$plan->type->value) }}</span></td>
                            <td class="py-2 pr-4">
                                @if ($standing->inForce()) v{{ $plan->approved_version }} @else <span class="font-medium text-amber-700">{{ __('bcm::coverage.not_in_force') }}</span> @endif
                                @if ($standing->awaitingApproval) <span class="block text-xs text-blue-700">{{ __('bcm::coverage.awaiting') }}</span> @endif
                            </td>
                            <td @class(['py-2 pr-4', 'font-medium text-amber-700' => $standing->reviewOverdue])>{{ $plan->next_review_on?->toDateString() ?? '—' }}</td>
                            <td class="py-2 pr-4">{{ $plan->last_exercised_on?->toDateString() ?? __('bcm::exercises.ui.never') }} @if ($standing->lastOutcome) <span class="block text-xs text-gray-500">{{ $standing->lastOutcome }}</span> @endif</td>
                            <td @class(['py-2 pr-4', 'font-medium text-amber-700' => $standing->exerciseOverdue])>{{ $standing->exerciseDueOn?->toDateString() ?? '—' }}</td>
                            <td @class(['py-2 pr-4', 'text-red-700' => $standing->fitsRto() === false, 'text-green-700' => $standing->fitsRto() === true])>
                                {{ $duration($standing->estimateMinutes) }} / {{ $duration($standing->rtoMinutes) }}
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-2 text-gray-500">{{ __('bcm::plans.ui.none') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <p class="text-xs text-gray-500">{{ __('bcm::coverage.recovery_help') }}</p>
    </section>

    @if ($processesAvailable)
        <section aria-labelledby="processes-heading" class="space-y-2 rounded-lg bg-white p-4 ring-1 ring-gray-200">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 id="processes-heading" class="text-sm font-semibold">{{ __('bcm::coverage.processes_heading') }}</h2>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model.live="gapsOnly"> {{ __('bcm::coverage.gaps_only') }}</label>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="text-xs text-gray-500">
                        <tr>
                            <th scope="col" class="py-2 pr-4 font-medium">{{ __('bcm::coverage.process') }}</th>
                            <th scope="col" class="py-2 pr-4 font-medium">{{ __('bcm::coverage.rto') }}</th>
                            <th scope="col" class="py-2 pr-4 font-medium">{{ __('bcm::coverage.plans') }}</th>
                            <th scope="col" class="py-2 pr-4 font-medium">{{ __('bcm::coverage.within_rto') }}</th>
                            <th scope="col" class="py-2 pr-4 font-medium">{{ __('bcm::coverage.tested') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($processRows as $row)
                            <tr wire:key="process-{{ $row->process->id }}">
                                <td class="py-2 pr-4"><a href="{{ $row->process->url }}" class="underline">{{ $row->process->name }}</a> @if ($row->process->tier) <span class="text-xs text-gray-500">{{ __('bcm::coverage.tier', ['tier' => $row->process->tier]) }}</span> @endif</td>
                                <td class="py-2 pr-4">{{ $duration($row->process->rtoMinutes) }}</td>
                                <td class="py-2 pr-4">
                                    @forelse ($row->plans as $standing)
                                        <a href="{{ route('bcm.plans.show', $standing->plan) }}" class="block underline">{{ $standing->plan->name }}</a>
                                    @empty
                                        <span class="font-medium text-red-700">{{ __('bcm::coverage.no_plan') }}</span>
                                    @endforelse
                                </td>
                                <td class="py-2 pr-4">{{ match ($row->fitsRto()) { true => __('bcm::coverage.yes'), false => __('bcm::coverage.no'), null => '—' } }}</td>
                                <td class="py-2 pr-4">{{ $row->isCovered() ? __($row->isTested() ? 'bcm::coverage.yes' : 'bcm::coverage.no') : '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-2 text-gray-500">{{ __('bcm::coverage.no_processes') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</div>
