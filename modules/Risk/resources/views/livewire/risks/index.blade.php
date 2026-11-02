<div class="space-y-6" @if ($preparing) wire:poll.3s @endif>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold">{{ __('risk::risks.title') }}</h1>
            <p class="text-sm text-gray-600">{{ __('risk::risks.ui.intro') }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('risk.report') }}" class="rounded-md bg-white px-4 py-2 text-sm font-semibold ring-1 ring-gray-300">{{ __('risk::report.button') }}</a>
            <button type="button" wire:click="export('xlsx')" wire:loading.attr="disabled" class="rounded-md bg-white px-4 py-2 text-sm font-semibold ring-1 ring-gray-300">{{ __('risk::report.export.button') }}</button>
        @can('create', \Modules\Risk\Domain\Models\Risk::class)
                <a href="{{ route('risk.risks.suggestions') }}" @class(['rounded-md px-4 py-2 text-sm font-semibold ring-1', 'bg-amber-50 text-amber-900 ring-amber-300' => $openSuggestions > 0, 'bg-white ring-gray-300' => ! $openSuggestions])>{{ __('risk::suggestions.ui.link', ['count' => $openSuggestions]) }}</a>
                <a href="{{ route('risk.risks.create') }}" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white">{{ __('risk::risks.ui.new') }}</a>
        @endcan
        </div>
    </div>

    @if ($exports->isNotEmpty())
        <ul class="divide-y divide-gray-100 rounded-lg bg-white px-4 text-sm ring-1 ring-gray-200">
            @foreach ($exports as $export)
                <li wire:key="export-{{ $export->id }}" class="flex flex-wrap items-center justify-between gap-2 py-2">
                    <span>{{ __('risk::report.export.of', ['date' => $export->created_at?->timezone($timezone)->format('Y-m-d H:i')]) }}</span>
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

    <div class="grid gap-6 lg:grid-cols-3">
        <section aria-labelledby="heat-heading" class="space-y-2 rounded-lg bg-white p-4 ring-1 ring-gray-200">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 id="heat-heading" class="text-sm font-semibold">{{ __('risk::risks.ui.heat_map') }}</h2>
                <fieldset class="flex gap-3 text-xs">
                    <legend class="sr-only">{{ __('risk::risks.ui.map_kind') }}</legend>
                    <label class="flex items-center gap-1"><input type="radio" wire:model.live="map" value="current"> {{ __('risk::risks.ui.map_current') }}</label>
                    <label class="flex items-center gap-1"><input type="radio" wire:model.live="map" value="inherent"> {{ __('risk::risks.ui.map_inherent') }}</label>
                </fieldset>
            </div>
            <table class="w-full table-fixed text-center text-xs">
                <caption class="sr-only">{{ __('risk::risks.ui.heat_caption') }}</caption>
                <tbody>
                    @foreach ([5, 4, 3, 2, 1] as $likelihood)
                        <tr wire:key="heat-row-{{ $likelihood }}">
                            <th scope="row" class="w-16 pr-1 text-right font-normal text-gray-600" title="{{ $settings->levelLabel('likelihood', $likelihood) }}">{{ $likelihood }}</th>
                            @foreach ([1, 2, 3, 4, 5] as $impact)
                                @php($level = $settings->level($likelihood * $impact))
                                @php($count = $heat[$likelihood][$impact] ?? 0)
                                <td wire:key="heat-{{ $likelihood }}-{{ $impact }}" class="p-0.5">
                                    <button type="button" wire:click="selectCell({{ $likelihood }}, {{ $impact }})" aria-label="{{ __('risk::risks.ui.cell', ['likelihood' => $likelihood, 'impact' => $impact, 'count' => $count]) }}" @if ($cell === $likelihood.'-'.$impact) aria-pressed="true" @endif @class([
                                        'h-10 w-full rounded font-semibold',
                                        'ring-2 ring-gray-900' => $cell === $likelihood.'-'.$impact,
                                        'bg-green-100 text-green-900' => $level === \Modules\Risk\Domain\Enums\RiskLevel::Low,
                                        'bg-yellow-100 text-yellow-900' => $level === \Modules\Risk\Domain\Enums\RiskLevel::Medium,
                                        'bg-orange-200 text-orange-900' => $level === \Modules\Risk\Domain\Enums\RiskLevel::High,
                                        'bg-red-200 text-red-900' => $level === \Modules\Risk\Domain\Enums\RiskLevel::Critical,
                                    ])>{{ $count > 0 ? $count : '' }}</button>
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                    <tr>
                        <td></td>
                        @foreach ([1, 2, 3, 4, 5] as $impact) <th scope="col" class="pt-1 font-normal text-gray-600" title="{{ $settings->levelLabel('impact', $impact) }}">{{ $impact }}</th> @endforeach
                    </tr>
                </tbody>
            </table>
            <p class="text-xs text-gray-500">{{ __('risk::risks.ui.heat_help') }}</p>
        </section>

        <div class="space-y-3 lg:col-span-2">
            <div class="flex flex-wrap items-end gap-3">
                <div>
                    <label for="search" class="block text-xs text-gray-600">{{ __('risk::risks.ui.search') }}</label>
                    <input id="search" type="search" wire:model.live.debounce.400ms="search" maxlength="200" class="mt-1 block w-56">
                </div>
                <div>
                    <label for="category" class="block text-xs text-gray-600">{{ __('risk::risks.fields.category') }}</label>
                    <select id="category" wire:model.live="category" class="mt-1 block">
                        <option value="">{{ __('risk::risks.ui.all') }}</option>
                        @foreach ($categories as $option) <option value="{{ $option->value }}" wire:key="category-{{ $option->value }}">{{ $option->label() }}</option> @endforeach
                    </select>
                </div>
                <div>
                    <label for="level" class="block text-xs text-gray-600">{{ __('risk::risks.fields.level') }}</label>
                    <select id="level" wire:model.live="level" class="mt-1 block">
                        <option value="">{{ __('risk::risks.ui.all') }}</option>
                        @foreach ($levels as $option) <option value="{{ $option->value }}" wire:key="level-{{ $option->value }}">{{ $option->label() }}</option> @endforeach
                    </select>
                </div>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model.live="closed"> {{ __('risk::risks.ui.show_closed') }}</label>
            </div>

            <div class="overflow-x-auto rounded-lg bg-white ring-1 ring-gray-200">
                <table class="min-w-full text-left text-sm">
                    <thead class="text-xs text-gray-500">
                        <tr>
                            <th scope="col" class="px-4 py-2 font-medium">{{ __('risk::risks.fields.risk') }}</th>
                            <th scope="col" class="px-4 py-2 font-medium">{{ __('risk::risks.fields.category') }}</th>
                            <th scope="col" class="px-4 py-2 font-medium">{{ __('risk::risks.fields.owner') }}</th>
                            <th scope="col" class="px-4 py-2 font-medium">{{ __('risk::risks.fields.inherent') }}</th>
                            <th scope="col" class="px-4 py-2 font-medium">{{ __('risk::treatment.fields.residual') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($risks as $risk)
                            @php($score = $risk->inherentScore())
                            <tr wire:key="risk-{{ $risk->id }}">
                                <td class="px-4 py-2"><a href="{{ route('risk.risks.show', $risk) }}" class="text-blue-700 underline">{{ $risk->code() }} · {{ $risk->title }}</a>@if ($risk->status === \Modules\Risk\Domain\Enums\RiskStatus::Closed) <span class="text-xs text-gray-500">({{ $risk->status->label() }})</span>@endif</td>
                                <td class="px-4 py-2">{{ $risk->category->label() }}</td>
                                <td class="px-4 py-2">{{ $risk->owner?->name ?? '—' }}</td>
                                <td class="px-4 py-2"><x-risk::level :level="$score !== null ? $settings->level($score) : null" :score="$score" /></td>
                                @php($residual = $risk->residualScore())
                                <td class="px-4 py-2">
                                    <x-risk::level :level="$residual !== null ? $settings->level($residual) : null" :score="$residual" />
                                    @if ($risk->isAccepted()) <span class="block text-xs text-gray-500">{{ __('risk::treatment.acceptance_statuses.approved') }}</span> @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-3 text-gray-500">{{ __('risk::risks.ui.none') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $risks->links() }}
        </div>
    </div>
</div>
