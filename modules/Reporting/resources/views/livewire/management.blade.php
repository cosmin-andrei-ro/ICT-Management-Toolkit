<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold">{{ __('reporting::management.title') }}</h1>
            <p class="text-sm text-gray-600">{{ __('reporting::management.intro') }}</p>
        </div>
        <div class="flex flex-wrap items-end gap-2">
            <div>
                <label for="report-month" class="block text-xs text-gray-600">{{ __('reporting::management.report.month') }}</label>
                <select id="report-month" wire:model.live="month" class="rounded-md border-gray-300 text-sm">
                    @foreach ($months as $option)
                        <option wire:key="month-{{ $option->format('Y-m') }}" value="{{ $option->format('Y-m') }}">{{ $option->isoFormat('MMMM YYYY') }}@if ($loop->first) ({{ __('reporting::management.report.so_far') }})@endif</option>
                    @endforeach
                </select>
            </div>
            <a href="{{ route('reporting.report.show', ['month' => $month]) }}" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white">{{ __('reporting::management.report.open') }}</a>
        </div>
    </div>

    @foreach ($groups as $group)
        <section wire:key="group-{{ $group['source']->key }}" aria-labelledby="group-{{ $group['source']->key }}" class="space-y-2 rounded-lg bg-white p-4 ring-1 ring-gray-200">
            <h2 id="group-{{ $group['source']->key }}" class="text-sm font-semibold">{{ __($group['source']->label) }}</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="text-xs text-gray-500">
                        <tr>
                            <th scope="col" class="py-2 pr-4 font-medium">{{ __('reporting::management.columns.indicator') }}</th>
                            <th scope="col" class="py-2 pr-4 text-right font-medium">{{ __('reporting::management.columns.now') }}</th>
                            <th scope="col" class="py-2 pr-4 font-medium">{{ __('reporting::management.columns.status') }}</th>
                            <th scope="col" class="py-2 pr-4 text-right font-medium">{{ __('reporting::management.columns.change') }}</th>
                            <th scope="col" class="w-40 py-2 pr-4 font-medium">{{ __('reporting::management.columns.trend') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($group['metrics'] as $row)
                            @php($metric = $row['metric'])
                            @php($monthAgo = $before[$metric->key] ?? null)
                            <tr wire:key="metric-{{ $metric->key }}">
                                <td class="py-2 pr-4">
                                    @if ($user->can($metric->permission))
                                        <a href="{{ route('reporting.metrics.show', $metric->key) }}" class="text-blue-700 underline">{{ __($metric->label) }}</a>
                                    @else
                                        {{ __($metric->label) }}
                                    @endif
                                </td>
                                <td class="py-2 pr-4 text-right font-semibold tabular-nums">{{ $metric->format($row['value']) }}</td>
                                <td class="py-2 pr-4">
                                    <x-reporting::status :status="$row['status']" />
                                </td>
                                <td class="py-2 pr-4 text-right tabular-nums text-gray-600">
                                    @if ($monthAgo !== null && $row['value'] !== null)
                                        {{ ($row['value'] - $monthAgo > 0 ? '+' : '').$metric->format($row['value'] - $monthAgo) }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="py-2 pr-4"><x-reporting::sparkline :series="$series[$metric->key] ?? []" :label="__('reporting::management.trend_of', ['metric' => __($metric->label)])" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endforeach

    @if ($manages)
        <section aria-labelledby="subscribers-heading" class="space-y-3 rounded-lg bg-white p-4 ring-1 ring-gray-200">
            <div>
                <h2 id="subscribers-heading" class="text-sm font-semibold">{{ __('reporting::management.subscriptions.title') }}</h2>
                <p class="text-sm text-gray-600">{{ __('reporting::management.subscriptions.help') }}</p>
            </div>
            <ul class="divide-y divide-gray-100 text-sm">
                @forelse ($subscriptions as $subscription)
                    <li wire:key="subscription-{{ $subscription->id }}" class="flex items-center justify-between gap-2 py-2">
                        <span>{{ $subscription->user->name }} <span class="text-gray-500">{{ $subscription->user->email }}</span></span>
                        <button type="button" wire:click="unsubscribe('{{ $subscription->id }}')" class="text-xs font-medium text-red-700 underline">{{ __('reporting::management.subscriptions.remove') }}<span class="sr-only">: {{ $subscription->user->name }}</span></button>
                    </li>
                @empty
                    <li class="py-2 text-gray-600">{{ __('reporting::management.subscriptions.none') }}</li>
                @endforelse
            </ul>
            <form wire:submit="subscribe" class="flex flex-wrap items-end gap-2">
                <div>
                    <label for="subscriber" class="block text-xs text-gray-600">{{ __('reporting::management.subscriptions.person') }}</label>
                    <select id="subscriber" wire:model="subscriber" class="rounded-md border-gray-300 text-sm">
                        <option value="">{{ __('reporting::management.subscriptions.choose') }}</option>
                        @foreach ($candidates as $candidate)
                            <option wire:key="candidate-{{ $candidate->id }}" value="{{ $candidate->id }}">{{ $candidate->name }} ({{ $candidate->email }})</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="rounded-md bg-white px-4 py-2 text-sm font-semibold ring-1 ring-gray-300">{{ __('reporting::management.subscriptions.add') }}</button>
            </form>
            @error('subscriber') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
        </section>
    @endif
</div>
