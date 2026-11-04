<div class="space-y-6">
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div class="space-y-1">
            @can('viewAny', \Modules\Risk\Domain\Models\Risk::class)
                <a href="{{ route('risk.index') }}" class="text-sm text-gray-500 underline">{{ __('risk::risks.ui.back') }}</a>
            @else
                <a href="{{ route('risk.mine') }}" class="text-sm text-gray-500 underline">← {{ __('risk::risks.mine.title') }}</a>
            @endcan
            <h1 class="flex flex-wrap items-center gap-2 text-xl font-semibold">{{ $risk->code() }} · {{ $risk->title }}</h1>
            <p class="flex flex-wrap items-center gap-2 text-sm">
                <span class="text-xs text-gray-500">{{ __('risk::risks.fields.inherent') }}</span> <x-risk::level :level="$level" :score="$score" />
                <span class="text-xs text-gray-500">{{ __('risk::treatment.fields.residual') }}</span> <x-risk::level :level="$residualLevel" :score="$residual" />
                @if ($risk->isAccepted()) <span class="rounded bg-gray-100 px-1.5 py-0.5 text-xs">{{ __('risk::treatment.ui.accepted_until', ['date' => $risk->accepted_until?->toDateString()]) }}</span> @endif
                @if ($pending) <span class="rounded bg-blue-50 px-1.5 py-0.5 text-xs text-blue-800">{{ __('risk::treatment.ui.acceptance_pending') }}</span> @endif
            </p>
            <p class="text-xs text-gray-500">{{ $risk->category->label() }} · {{ $risk->status->label() }} · {{ __('risk::risks.fields.owner') }}: {{ $risk->owner?->name ?? '—' }} · {{ __('risk::risks.fields.next_review') }}: {{ $risk->next_review_on?->toDateString() ?? '—' }}</p>
        </div>
        @can('update', $risk)
            <a href="{{ route('risk.risks.edit', $risk) }}" class="rounded-md bg-white px-3 py-1.5 text-sm font-semibold ring-1 ring-gray-300">{{ __('risk::risks.ui.edit') }}</a>
        @endcan
    </header>

    @if ($risk->description) <p class="whitespace-pre-line rounded-lg bg-white p-4 text-sm ring-1 ring-gray-200">{{ $risk->description }}</p> @endif

    <section aria-labelledby="assets-heading" class="space-y-3 rounded-lg bg-white p-4 ring-1 ring-gray-200">
        <h2 id="assets-heading" class="text-sm font-semibold">{{ __('risk::risks.ui.concerns') }}</h2>
        <ul class="space-y-1 text-sm">
            @forelse ($linked as $asset)
                <li wire:key="asset-{{ $asset->type->value }}-{{ $asset->id }}" class="flex flex-wrap items-center justify-between gap-2">
                    <span>
                        <span class="text-xs text-gray-500">{{ $asset->type->label() }}:</span>
                        @if ($asset->url) <a href="{{ $asset->url }}" class="text-blue-700 underline">{{ $asset->name }}</a> @else {{ $asset->name }} @endif
                        @if ($asset->detail) <span class="text-xs text-gray-500">{{ $asset->detail }}</span> @endif
                    </span>
                    @if ($assessable) <button type="button" wire:click="removeAsset('{{ $asset->type->value }}', '{{ $asset->id }}')" class="text-xs text-red-700 underline">{{ __('risk::risks.ui.remove') }}</button> @endif
                </li>
            @empty
                <li class="text-gray-500">{{ __('risk::risks.ui.no_assets') }}</li>
            @endforelse
        </ul>
        @if ($assessable)
            <div class="flex flex-wrap gap-2">
                <label for="assetType" class="sr-only">{{ __('risk::risks.ui.asset_type') }}</label>
                <select id="assetType" wire:model.live="assetType" class="block text-sm">
                    @foreach ($assetTypes as $type) <option value="{{ $type->value }}" wire:key="type-{{ $type->value }}">{{ $type->label() }}</option> @endforeach
                </select>
                <input type="search" wire:model.live.debounce.300ms="assetSearch" maxlength="100" placeholder="{{ __('risk::risks.ui.find_asset') }}" aria-label="{{ __('risk::risks.ui.find_asset') }}" class="block min-w-64 flex-1 text-sm">
            </div>
            @error('assetSearch') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            @if ($candidates !== [])
                <ul class="divide-y divide-gray-100 rounded-md text-sm ring-1 ring-gray-200">
                    @foreach ($candidates as $candidate)
                        <li wire:key="candidate-{{ $candidate->id }}" class="flex items-center justify-between gap-2 px-2 py-1.5">
                            <span>{{ $candidate->name }} @if ($candidate->detail) <span class="text-xs text-gray-500">{{ $candidate->detail }}</span> @endif</span>
                            <button type="button" wire:click="addAsset('{{ $candidate->type->value }}', '{{ $candidate->id }}')" class="text-xs text-blue-700 underline">{{ __('risk::risks.ui.link') }}</button>
                        </li>
                    @endforeach
                </ul>
            @endif
        @endif
    </section>

    <form wire:submit="assess" class="space-y-5 rounded-lg bg-white p-4 ring-1 ring-gray-200" novalidate>
        <h2 class="text-sm font-semibold">{{ __('risk::risks.ui.assessment') }}</h2>
        <div class="grid gap-6 md:grid-cols-2">
            @foreach (['threats' => $threats, 'vulnerabilities' => $vulnerabilities] as $kind => $entries)
                <fieldset wire:key="fieldset-{{ $kind }}" class="space-y-1">
                    <legend class="text-sm font-medium">{{ __('risk::risks.ui.'.$kind) }}</legend>
                    <div class="max-h-64 space-y-1 overflow-y-auto rounded-md p-2 ring-1 ring-gray-200">
                        @foreach ($entries as $entry)
                            <label wire:key="entry-{{ $entry->id }}" class="flex items-start gap-2 text-sm" @if ($entry->description) title="{{ $entry->description }}" @endif>
                                <input type="checkbox" wire:model="entryIds" value="{{ $entry->id }}" class="mt-1" @disabled(! $assessable)> {{ $entry->name }}
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            @endforeach
        </div>
        @error('entryIds') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        <div>
            <label for="controls" class="block text-sm font-medium">{{ __('risk::risks.fields.existing_controls') }}</label>
            <textarea id="controls" wire:model="controls" rows="3" maxlength="10000" placeholder="{{ __('risk::risks.examples.controls') }}" class="mt-1 block w-full" @disabled(! $assessable)></textarea>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            @foreach (['likelihood' => $settings->likelihood_levels, 'impact' => $settings->impact_levels] as $axis => $scale)
                <div wire:key="axis-{{ $axis }}">
                    <label for="{{ $axis }}" class="block text-sm font-medium">{{ __('risk::risks.fields.'.$axis) }}</label>
                    <select id="{{ $axis }}" wire:model="{{ $axis }}" class="mt-1 block w-full" @disabled(! $assessable)>
                        <option value="">{{ __('risk::risks.ui.not_assessed') }}</option>
                        @foreach ($scale as $index => $levelInfo) <option value="{{ $index + 1 }}" wire:key="{{ $axis }}-{{ $index }}">{{ $index + 1 }} - {{ $levelInfo['label'] }}</option> @endforeach
                    </select>
                    @error($axis) <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    <ul class="mt-1 space-y-0.5 text-xs text-gray-500">
                        @foreach ($scale as $index => $levelInfo) <li wire:key="{{ $axis }}-help-{{ $index }}"><span class="font-medium">{{ $index + 1 }}</span> {{ $levelInfo['description'] }}</li> @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
        @if ($assessable)
            <button type="submit" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white">{{ __('risk::risks.ui.save_assessment') }}</button>
        @endif
    </form>

    <form wire:submit="saveTreatment" class="space-y-4 rounded-lg bg-white p-4 ring-1 ring-gray-200" novalidate>
        <h2 class="text-sm font-semibold">{{ __('risk::treatment.ui.treatment') }}</h2>
        <fieldset class="space-y-1">
            <legend class="text-sm font-medium">{{ __('risk::treatment.fields.option') }}</legend>
            <div class="flex flex-wrap gap-x-5 gap-y-1">
                @foreach ($treatments as $option)
                    <label wire:key="treatment-{{ $option->value }}" class="flex items-center gap-2 text-sm" title="{{ __('risk::treatment.option_help.'.$option->value) }}"><input type="radio" wire:model="treatment" value="{{ $option->value }}" @disabled(! $assessable)> {{ $option->label() }}</label>
                @endforeach
            </div>
        </fieldset>
        <div>
            <label for="treatmentPlan" class="block text-sm font-medium">{{ __('risk::treatment.fields.plan') }}</label>
            <textarea id="treatmentPlan" wire:model="treatmentPlan" rows="3" maxlength="10000" placeholder="{{ __('risk::treatment.examples.plan') }}" class="mt-1 block w-full" @disabled(! $assessable)></textarea>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            @foreach (['residualLikelihood' => ['likelihood', $settings->likelihood_levels], 'residualImpact' => ['impact', $settings->impact_levels]] as $property => [$axis, $scale])
                <div wire:key="residual-{{ $axis }}">
                    <label for="{{ $property }}" class="block text-sm font-medium">{{ __('risk::treatment.fields.residual_'.$axis) }}</label>
                    <select id="{{ $property }}" wire:model="{{ $property }}" class="mt-1 block w-full" @disabled(! $assessable)>
                        <option value="">{{ __('risk::risks.ui.not_assessed') }}</option>
                        @foreach ($scale as $index => $levelInfo) <option value="{{ $index + 1 }}" wire:key="{{ $property }}-{{ $index }}">{{ $index + 1 }} - {{ $levelInfo['label'] }}</option> @endforeach
                    </select>
                    @error($property) <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            @endforeach
        </div>
        <p class="text-xs text-gray-500">{{ __('risk::treatment.ui.residual_help') }}</p>
        @if ($assessable)
            <button type="submit" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white">{{ __('risk::treatment.ui.save_treatment') }}</button>
        @endif
    </form>

    <section aria-labelledby="actions-heading" class="space-y-3 rounded-lg bg-white p-4 ring-1 ring-gray-200">
        <h2 id="actions-heading" class="text-sm font-semibold">{{ __('risk::treatment.ui.actions') }}</h2>
        @error('actions') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        <ul class="divide-y divide-gray-100 text-sm">
            @forelse ($actions as $action)
                <li wire:key="action-{{ $action->id }}" class="flex flex-wrap items-center justify-between gap-2 py-2">
                    <span>
                        {{ $action->title }}
                        <span class="block text-xs text-gray-500">
                            {{ $action->owner?->name ?? '—' }} ·
                            <span @class(['font-medium text-red-700' => $action->isOverdue()])>{{ $action->due_on ? __('risk::treatment.ui.due', ['date' => $action->due_on->toDateString()]) : __('risk::treatment.ui.no_due') }}</span>
                            · {{ $action->status->label() }}
                            @if ($action->ticket_number) · {{ $action->ticket_number }} @endif
                        </span>
                    </span>
                    @if ($assessable && $action->status->isOpen())
                        <span class="flex flex-wrap gap-2 text-xs">
                            @if ($action->status === \Modules\Risk\Domain\Enums\ActionStatus::Planned) <button type="button" wire:click="changeActionStatus('{{ $action->id }}', 'in_progress')" class="underline">{{ __('risk::treatment.ui.start') }}</button> @endif
                            <button type="button" wire:click="changeActionStatus('{{ $action->id }}', 'done')" class="text-green-700 underline">{{ __('risk::treatment.ui.done') }}</button>
                            <button type="button" wire:click="changeActionStatus('{{ $action->id }}', 'cancelled')" class="text-red-700 underline">{{ __('risk::treatment.ui.cancel') }}</button>
                            @if ($ticketsAvailable && ! $action->ticket_id) <button type="button" wire:click="raiseActionTicket('{{ $action->id }}')" class="text-blue-700 underline">{{ __('risk::treatment.ui.raise_ticket') }}</button> @endif
                        </span>
                    @endif
                </li>
            @empty
                <li class="py-2 text-gray-500">{{ __('risk::treatment.ui.no_actions') }}</li>
            @endforelse
        </ul>
        @if ($assessable)
            <form wire:submit="addAction" class="grid gap-2 border-t border-gray-100 pt-3 sm:grid-cols-6" novalidate>
                <div class="sm:col-span-3">
                    <label for="actionTitle" class="block text-xs font-medium">{{ __('risk::treatment.fields.action') }}</label>
                    <input id="actionTitle" type="text" wire:model="actionTitle" maxlength="255" placeholder="{{ __('risk::treatment.examples.action') }}" class="mt-1 block w-full">
                    @error('actionTitle') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-2">
                    <label for="actionOwnerId" class="block text-xs font-medium">{{ __('risk::treatment.fields.action_owner') }}</label>
                    <select id="actionOwnerId" wire:model="actionOwnerId" class="mt-1 block w-full">
                        <option value="">—</option>
                        @foreach ($people as $person) <option value="{{ $person->id }}" wire:key="action-owner-{{ $person->id }}">{{ $person->name }}</option> @endforeach
                    </select>
                </div>
                <div>
                    <label for="actionDueOn" class="block text-xs font-medium">{{ __('risk::treatment.fields.due_on') }}</label>
                    <input id="actionDueOn" type="date" wire:model="actionDueOn" class="mt-1 block w-full">
                </div>
                <div class="sm:col-span-6"><button type="submit" class="rounded-md bg-white px-3 py-1.5 text-sm font-semibold ring-1 ring-gray-300">{{ __('risk::treatment.ui.add_action') }}</button></div>
            </form>
        @endif
    </section>

    <section aria-labelledby="acceptance-heading" class="space-y-3 rounded-lg bg-white p-4 ring-1 ring-gray-200">
        <h2 id="acceptance-heading" class="text-sm font-semibold">{{ __('risk::treatment.ui.acceptance') }}</h2>
        @if ($pending)
            <div class="space-y-2 rounded-md bg-blue-50 p-3 text-sm">
                <p>{{ __('risk::treatment.ui.pending_line', ['name' => $pending->requester?->name ?? '—', 'score' => $pending->score()]) }}</p>
                <p class="whitespace-pre-line text-gray-700">{{ $pending->justification }}</p>
                @if ($canDecide)
                    <label for="decisionComment" class="block text-sm font-medium">{{ __('risk::treatment.fields.decision_comment') }}</label>
                    <textarea id="decisionComment" wire:model="decisionComment" rows="2" maxlength="2000" class="block w-full"></textarea>
                    @error('decisionComment') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                    <div class="flex gap-2">
                        <button type="button" wire:click="approveAcceptance" class="rounded-md bg-gray-900 px-3 py-1.5 text-sm font-semibold text-white">{{ __('risk::treatment.ui.approve') }}</button>
                        <button type="button" wire:click="rejectAcceptance" class="rounded-md bg-white px-3 py-1.5 text-sm font-semibold text-red-700 ring-1 ring-gray-300">{{ __('risk::treatment.ui.reject') }}</button>
                    </div>
                @endif
            </div>
        @elseif ($assessable && $residual !== null && ! $risk->isAccepted())
            <form wire:submit="requestAcceptance" class="space-y-2" novalidate>
                <p class="text-xs text-gray-500">{{ __($needsApproval ? 'risk::treatment.ui.needs_approval' : 'risk::treatment.ui.accept_at_once', ['threshold' => $settings->approval_from]) }}</p>
                <label for="justification" class="block text-sm font-medium">{{ __('risk::treatment.fields.justification') }}</label>
                <textarea id="justification" wire:model="justification" rows="2" maxlength="5000" placeholder="{{ __('risk::treatment.examples.justification') }}" class="block w-full"></textarea>
                @error('justification') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                <button type="submit" class="rounded-md bg-white px-3 py-1.5 text-sm font-semibold ring-1 ring-gray-300">{{ __($needsApproval ? 'risk::treatment.ui.request_acceptance' : 'risk::treatment.ui.accept') }}</button>
            </form>
        @elseif ($residual === null)
            <p class="text-sm text-gray-500">{{ __('risk::treatment.ui.residual_first') }}</p>
        @endif
        <ul class="divide-y divide-gray-100 text-xs text-gray-600">
            @foreach ($acceptances as $acceptance)
                <li wire:key="acceptance-{{ $acceptance->id }}" class="py-1.5">
                    {{ $acceptance->requested_at->toDateString() }} · {{ $acceptance->status->label() }} · {{ __('risk::treatment.ui.score', ['score' => $acceptance->score()]) }}
                    · {{ $acceptance->requester?->name ?? '—' }}@if ($acceptance->decider && $acceptance->decided_by !== $acceptance->requested_by) → {{ $acceptance->decider->name }}@endif
                    @if ($acceptance->valid_until) · {{ __('risk::treatment.ui.until', ['date' => $acceptance->valid_until->toDateString()]) }} @endif
                    @if ($acceptance->comment) <span class="block">{{ $acceptance->comment }}</span> @endif
                </li>
            @endforeach
        </ul>
    </section>

    <section aria-labelledby="history-heading" class="space-y-2 rounded-lg bg-white p-4 ring-1 ring-gray-200">
        <h2 id="history-heading" class="text-sm font-semibold">{{ __('risk::treatment.ui.history') }}</h2>
        <table class="min-w-full text-left text-sm">
            <thead class="text-xs text-gray-500"><tr>
                <th scope="col" class="py-1 pr-4 font-medium">{{ __('risk::treatment.ui.date') }}</th>
                <th scope="col" class="py-1 pr-4 font-medium">{{ __('risk::risks.fields.inherent') }}</th>
                <th scope="col" class="py-1 pr-4 font-medium">{{ __('risk::treatment.fields.residual') }}</th>
                <th scope="col" class="py-1 font-medium">{{ __('risk::treatment.ui.by') }}</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($history as $record)
                    @php($inherentScore = $record->inherent_likelihood !== null && $record->inherent_impact !== null ? $record->inherent_likelihood * $record->inherent_impact : null)
                    @php($residualScore = $record->residual_likelihood !== null && $record->residual_impact !== null ? $record->residual_likelihood * $record->residual_impact : null)
                    <tr wire:key="history-{{ $record->id }}">
                        <td class="py-1 pr-4">{{ $record->recorded_at->toDateString() }}</td>
                        <td class="py-1 pr-4"><x-risk::level :level="$inherentScore !== null ? $settings->level($inherentScore) : null" :score="$inherentScore" /></td>
                        <td class="py-1 pr-4"><x-risk::level :level="$residualScore !== null ? $settings->level($residualScore) : null" :score="$residualScore" /></td>
                        <td class="py-1">{{ $record->recorder?->name ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-2 text-gray-500">{{ __('risk::treatment.ui.no_history') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>

    <x-core::record-tags :record="$risk" />

    <x-core::record-history :record="$risk" :can-change="auth()->user()->can('update', $risk)" />
</div>
