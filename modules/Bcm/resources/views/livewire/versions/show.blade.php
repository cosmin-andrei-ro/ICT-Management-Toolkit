@php($duration = fn (?int $minutes): string => \Modules\Core\Domain\Time\Duration::format($minutes))
<div class="space-y-6">
    <header class="space-y-1">
        <a href="{{ route('bcm.plans.show', $plan) }}" class="text-sm text-gray-500 underline">← {{ $plan->name }}</a>
        <h1 class="flex flex-wrap items-center gap-2 text-xl font-semibold">
            {{ $plan->name }} · {{ __('bcm::plans.ui.version', ['version' => $version->version]) }}
            <x-bcm::version-status :status="$version->status" />
        </h1>
        <p class="text-xs text-gray-500">{{ $plan->type->label() }}</p>
        @if ($version->submitter && $version->submitted_at)
            <p class="text-xs text-gray-500">{{ __('bcm::plans.ui.submitted_by', ['name' => $version->submitter->name, 'date' => $version->submitted_at->toDateString()]) }}</p>
        @endif
        @if ($version->decider && $version->decided_at)
            <p class="text-xs text-gray-500">{{ __('bcm::plans.ui.decided_by', ['status' => $version->status === \Modules\Bcm\Domain\Enums\VersionStatus::Returned ? $version->status->label() : __('bcm::plans.statuses.approved'), 'name' => $version->decider->name, 'date' => $version->decided_at->toDateString()]) }}</p>
        @endif
        @if ($version->decision_comment)
            <p @class(['rounded-md p-3 text-sm', 'bg-amber-50 text-amber-900' => $version->status === \Modules\Bcm\Domain\Enums\VersionStatus::Returned, 'bg-gray-50 text-gray-700' => $version->status !== \Modules\Bcm\Domain\Enums\VersionStatus::Returned])>{{ $version->decision_comment }}</p>
        @endif
    </header>

    {{-- Recovery time against the business's requirement. --}}
    <p @class(['rounded-md p-3 text-sm',
        'bg-red-50 text-red-800' => $estimate !== null && $strictest !== null && $estimate > $strictest->rtoMinutes,
        'bg-green-50 text-green-800' => $estimate !== null && $strictest !== null && $estimate <= $strictest->rtoMinutes,
        'bg-gray-50 text-gray-700' => $estimate === null || $strictest === null])>
        @if ($estimate === null)
            {{ __('bcm::plans.ui.no_estimate') }}
        @else
            {{ __('bcm::plans.ui.estimate', ['value' => $duration($estimate)]) }}
            @if ($strictest !== null)
                {{ __($estimate > $strictest->rtoMinutes ? 'bcm::plans.ui.over_rto' : 'bcm::plans.ui.within_rto', ['value' => $duration($strictest->rtoMinutes), 'process' => $strictest->name]) }}
            @endif
        @endif
    </p>

    <section aria-labelledby="scope-heading" class="space-y-4 rounded-lg bg-white p-4 ring-1 ring-gray-200">
        <h2 id="scope-heading" class="text-sm font-semibold">{{ __('bcm::plans.ui.scope') }}</h2>
        @error('scope') <p class="rounded-md bg-red-50 p-2 text-sm text-red-800" role="alert">{{ $message }}</p> @enderror
        <div class="grid gap-6 md:grid-cols-2">
            <div class="space-y-2">
                <h3 class="text-sm font-medium">{{ __('bcm::plans.ui.processes') }}</h3>
                @if (! $processesAvailable)
                    <p class="text-sm text-gray-500">{{ __('bcm::plans.ui.bia_off') }}</p>
                @else
                    <ul class="space-y-1 text-sm">
                        @forelse ($scopeProcesses as $process)
                            <li wire:key="process-{{ $process->id }}" class="flex items-center justify-between gap-2">
                                <span><a href="{{ $process->url }}" class="text-blue-700 underline">{{ $process->name }}</a> <span class="text-xs text-gray-500">{{ $process->rtoMinutes !== null ? __('bcm::plans.ui.rto', ['value' => $duration($process->rtoMinutes)]) : __('bcm::plans.ui.not_assessed') }}</span></span>
                                @if ($editable) <button type="button" wire:click="removeProcess('{{ $process->id }}')" class="text-xs text-red-700 underline">{{ __('bcm::plans.ui.remove') }}</button> @endif
                            </li>
                        @empty
                            <li class="text-gray-500">{{ __('bcm::plans.ui.none') }}</li>
                        @endforelse
                    </ul>
                    @if ($editable)
                        <input type="search" wire:model.live.debounce.300ms="processSearch" maxlength="100" placeholder="{{ __('bcm::plans.ui.find_process') }}" aria-label="{{ __('bcm::plans.ui.find_process') }}" class="block w-full text-sm">
                        @error('processSearch') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                        @if ($processCandidates !== [])
                            <ul class="divide-y divide-gray-100 rounded-md text-sm ring-1 ring-gray-200">
                                @foreach ($processCandidates as $candidate)
                                    <li wire:key="process-candidate-{{ $candidate->id }}" class="flex items-center justify-between gap-2 px-2 py-1.5">
                                        <span>{{ $candidate->name }}</span>
                                        <button type="button" wire:click="addProcess('{{ $candidate->id }}')" class="text-xs text-blue-700 underline">{{ __('bcm::plans.ui.add') }}</button>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    @endif
                @endif
            </div>
            <div class="space-y-2">
                <h3 class="text-sm font-medium">{{ __('bcm::plans.ui.systems') }}</h3>
                @if (! $cmdbAvailable)
                    <p class="text-sm text-gray-500">{{ __('bcm::plans.ui.cmdb_off') }}</p>
                @else
                    <ul class="space-y-1 text-sm">
                        @forelse ($linkedItems as $item)
                            <li wire:key="item-{{ $item->id }}" class="flex items-center justify-between gap-2">
                                <span><a href="{{ $item->url }}" class="text-blue-700 underline">{{ $item->name }}</a> <span class="text-xs text-gray-500">{{ $item->typeName }}</span></span>
                                @if ($editable) <button type="button" wire:click="unlinkItem('{{ $item->id }}')" class="text-xs text-red-700 underline">{{ __('bcm::plans.ui.remove') }}</button> @endif
                            </li>
                        @empty
                            <li class="text-gray-500">{{ __('bcm::plans.ui.none') }}</li>
                        @endforelse
                    </ul>
                    @if ($editable)
                        <input type="search" wire:model.live.debounce.300ms="itemSearch" maxlength="100" placeholder="{{ __('bcm::plans.ui.find_item') }}" aria-label="{{ __('bcm::plans.ui.find_item') }}" class="block w-full text-sm">
                        @error('itemSearch') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                        @if ($itemCandidates !== [])
                            <ul class="divide-y divide-gray-100 rounded-md text-sm ring-1 ring-gray-200">
                                @foreach ($itemCandidates as $candidate)
                                    <li wire:key="item-candidate-{{ $candidate->id }}" class="flex items-center justify-between gap-2 px-2 py-1.5">
                                        <span>{{ $candidate->name }} <span class="text-xs text-gray-500">{{ $candidate->typeName }}</span></span>
                                        <button type="button" wire:click="linkItem('{{ $candidate->id }}')" class="text-xs text-blue-700 underline">{{ __('bcm::plans.ui.add') }}</button>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    @endif
                @endif
            </div>
        </div>
    </section>

    <form wire:submit="save" class="space-y-4 rounded-lg bg-white p-4 ring-1 ring-gray-200" novalidate>
        <fieldset class="space-y-2">
            <legend class="text-sm font-medium">{{ __('bcm::plans.fields.scenarios') }}</legend>
            @error('scenarios') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            <div class="grid gap-1 sm:grid-cols-2">
                @foreach ($allScenarios as $scenario)
                    <label wire:key="scenario-{{ $scenario->value }}" class="flex items-center gap-2 text-sm">
                        @if ($editable)
                            <input type="checkbox" wire:model="scenarios" value="{{ $scenario->value }}" class="rounded border-gray-300">
                        @else
                            <span aria-hidden="true">{{ in_array($scenario->value, $version->scenarios, true) ? '☑' : '☐' }}</span>
                        @endif
                        {{ $scenario->label() }}
                    </label>
                @endforeach
            </div>
        </fieldset>

        @foreach (['objective' => 'objective', 'activationCriteria' => 'activation_criteria', 'activationAuthority' => 'activation_authority', 'strategy' => 'strategy', 'communication' => 'communication', 'assumptions' => 'assumptions'] as $property => $field)
            <div wire:key="field-{{ $field }}">
                <label for="{{ $property }}" class="block text-sm font-medium">{{ __('bcm::plans.fields.'.$field) }}</label>
                @if (\Illuminate\Support\Facades\Lang::has('bcm::plans.help.'.$field)) <p class="text-xs text-gray-500">{{ __('bcm::plans.help.'.$field) }}</p> @endif
                @if ($editable)
                    <textarea id="{{ $property }}" wire:model="{{ $property }}" rows="3" maxlength="10000" class="mt-1 block w-full text-sm"></textarea>
                    @error($property) <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                @else
                    <p class="mt-1 whitespace-pre-line text-sm text-gray-700">{{ $version->{$field} ?? '—' }}</p>
                @endif
            </div>
        @endforeach

        @if ($editable)
            <button type="submit" wire:loading.attr="disabled" class="rounded-md bg-white px-4 py-2 text-sm font-semibold ring-1 ring-gray-300">{{ __('bcm::plans.ui.save_draft') }}</button>
        @endif
    </form>

    <section aria-labelledby="team-heading" class="space-y-3 rounded-lg bg-white p-4 ring-1 ring-gray-200">
        <div>
            <h2 id="team-heading" class="text-sm font-semibold">{{ __('bcm::plans.ui.team') }}</h2>
            <p class="text-xs text-gray-500">{{ __('bcm::plans.ui.team_help') }}</p>
        </div>
        @error('team') <p class="rounded-md bg-red-50 p-2 text-sm text-red-800" role="alert">{{ $message }}</p> @enderror
        <table class="w-full text-sm">
            <tbody>
                @forelse ($team as $member)
                    <tr wire:key="member-{{ $member->id }}" class="border-t border-gray-100 align-top">
                        <td class="py-1.5 pr-3 font-medium">{{ $member->role }} @if ($member->is_deputy) <span class="text-xs font-normal text-gray-500">({{ __('bcm::plans.ui.deputy_flag') }})</span> @endif</td>
                        <td class="py-1.5 pr-3">
                            {{ $member->displayName() }}
                            <span class="block text-xs text-gray-500">{{ $member->user?->email ?? collect([$member->contact_phone, $member->contact_email])->filter()->implode(' · ') }}</span>
                        </td>
                        <td class="py-1.5 pr-3 text-xs text-gray-600">{{ $member->responsibilities }}</td>
                        <td class="py-1.5 text-right">@if ($editable) <button type="button" wire:click="removeMember('{{ $member->id }}')" class="text-xs text-red-700 underline">{{ __('bcm::plans.ui.remove') }}</button> @endif</td>
                    </tr>
                @empty
                    <tr><td class="py-1.5 text-gray-500">{{ __('bcm::plans.ui.none') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        @if ($editable)
            <form wire:submit="addMember" class="grid gap-2 rounded-md bg-gray-50 p-3 sm:grid-cols-2" novalidate>
                <input type="text" wire:model="memberRole" maxlength="255" placeholder="{{ __('bcm::plans.ui.role') }}" aria-label="{{ __('bcm::plans.ui.role') }}" class="text-sm">
                <select wire:model="memberUserId" aria-label="{{ __('bcm::plans.ui.person') }}" class="text-sm">
                    <option value="">{{ __('bcm::plans.ui.person') }}…</option>
                    @foreach ($people as $person) <option value="{{ $person->id }}" wire:key="member-person-{{ $person->id }}">{{ $person->name }}</option> @endforeach
                </select>
                <p class="text-xs text-gray-500 sm:col-span-2">{{ __('bcm::plans.ui.or_contact') }}</p>
                <input type="text" wire:model="memberContactName" maxlength="255" placeholder="{{ __('bcm::plans.ui.contact_name') }}" aria-label="{{ __('bcm::plans.ui.contact_name') }}" class="text-sm">
                <div class="flex gap-2">
                    <input type="text" wire:model="memberContactPhone" maxlength="64" placeholder="{{ __('bcm::plans.ui.contact_phone') }}" aria-label="{{ __('bcm::plans.ui.contact_phone') }}" class="min-w-0 flex-1 text-sm">
                    <input type="email" wire:model="memberContactEmail" maxlength="255" placeholder="{{ __('bcm::plans.ui.contact_email') }}" aria-label="{{ __('bcm::plans.ui.contact_email') }}" class="min-w-0 flex-1 text-sm">
                </div>
                <input type="text" wire:model="memberResponsibilities" maxlength="2000" placeholder="{{ __('bcm::plans.ui.responsibilities') }}" aria-label="{{ __('bcm::plans.ui.responsibilities') }}" class="text-sm sm:col-span-2">
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="memberDeputy" class="rounded border-gray-300"> {{ __('bcm::plans.ui.deputy_flag') }}</label>
                <div class="text-right"><button type="submit" class="rounded-md bg-white px-3 py-1.5 text-sm font-semibold ring-1 ring-gray-300">{{ __('bcm::plans.ui.add_member') }}</button></div>
                @error('memberRole') <p class="text-sm text-red-600 sm:col-span-2">{{ $message }}</p> @enderror
                @error('memberContactEmail') <p class="text-sm text-red-600 sm:col-span-2">{{ $message }}</p> @enderror
            </form>
        @endif
    </section>

    <section aria-labelledby="runbook-heading" class="space-y-3 rounded-lg bg-white p-4 ring-1 ring-gray-200">
        <div>
            <h2 id="runbook-heading" class="text-sm font-semibold">{{ __('bcm::plans.ui.runbook') }}</h2>
            <p class="text-xs text-gray-500">{{ __('bcm::plans.ui.runbook_help') }}</p>
        </div>
        @error('steps') <p class="rounded-md bg-red-50 p-2 text-sm text-red-800" role="alert">{{ $message }}</p> @enderror
        <ol class="space-y-2 text-sm">
            @forelse ($steps as $step)
                <li wire:key="step-{{ $step->id }}" class="flex gap-3 rounded-md p-2 ring-1 ring-gray-100">
                    <span class="font-mono text-gray-500">{{ $step->position }}.</span>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-semibold uppercase text-gray-500">{{ $step->phase->label() }}</p>
                        <p class="whitespace-pre-line">{{ $step->action }}</p>
                        <p class="text-xs text-gray-500">
                            {{ collect([$step->responsible, $step->responsibleUser?->name])->filter()->implode(' · ') ?: '—' }}
                            @if ($step->estimated_minutes !== null) · {{ $duration($step->estimated_minutes) }} @endif
                            @if ($step->configuration_item_id) · {{ $itemNames[$step->configuration_item_id] ?? '' }} @endif
                        </p>
                        @if ($step->notes) <p class="text-xs text-gray-500">{{ $step->notes }}</p> @endif
                    </div>
                    @if ($editable)
                        <div class="flex shrink-0 flex-col items-end gap-1 text-xs">
                            <button type="button" wire:click="editStep('{{ $step->id }}')" class="text-blue-700 underline">{{ __('bcm::plans.ui.edit') }}</button>
                            <button type="button" wire:click="moveStepUp('{{ $step->id }}')" class="text-gray-600 underline">{{ __('bcm::plans.ui.move_up') }}</button>
                            <button type="button" wire:click="moveStepDown('{{ $step->id }}')" class="text-gray-600 underline">{{ __('bcm::plans.ui.move_down') }}</button>
                            <button type="button" wire:click="removeStep('{{ $step->id }}')" class="text-red-700 underline">{{ __('bcm::plans.ui.remove') }}</button>
                        </div>
                    @endif
                </li>
            @empty
                <li class="text-gray-500">{{ __('bcm::plans.ui.none') }}</li>
            @endforelse
        </ol>
        @if ($editable)
            @if ($editingStepId === null)
                <button type="button" wire:click="editStep" class="rounded-md bg-white px-3 py-1.5 text-sm font-semibold ring-1 ring-gray-300">{{ __('bcm::plans.ui.add_step') }}</button>
            @else
                <form wire:submit="saveStep" class="grid gap-2 rounded-md bg-gray-50 p-3 sm:grid-cols-2" novalidate>
                    <select wire:model="stepPhase" aria-label="{{ __('bcm::plans.ui.step') }}" class="text-sm">
                        @foreach ($phases as $phase) <option value="{{ $phase->value }}">{{ $phase->label() }}</option> @endforeach
                    </select>
                    <div class="flex gap-2">
                        <input type="number" min="0" wire:model="stepDurationValue" placeholder="{{ __('bcm::plans.ui.duration') }}" aria-label="{{ __('bcm::plans.ui.duration') }}" class="w-24 text-sm">
                        <select wire:model="stepDurationUnit" aria-label="{{ __('bcm::plans.ui.duration') }}" class="text-sm">
                            @foreach ($units as $unit) <option value="{{ $unit }}">{{ __('core::durations.unit_names.'.$unit) }}</option> @endforeach
                        </select>
                    </div>
                    <textarea wire:model="stepAction" rows="2" maxlength="5000" placeholder="{{ __('bcm::plans.ui.action') }}" aria-label="{{ __('bcm::plans.ui.action') }}" class="text-sm sm:col-span-2"></textarea>
                    @error('stepAction') <p class="text-sm text-red-600 sm:col-span-2">{{ $message }}</p> @enderror
                    <input type="text" wire:model="stepResponsible" maxlength="255" placeholder="{{ __('bcm::plans.ui.responsible') }}" aria-label="{{ __('bcm::plans.ui.responsible') }}" class="text-sm">
                    <select wire:model="stepResponsibleUserId" aria-label="{{ __('bcm::plans.ui.responsible_person') }}" class="text-sm">
                        <option value="">{{ __('bcm::plans.ui.responsible_person') }}…</option>
                        @foreach ($people as $person) <option value="{{ $person->id }}" wire:key="step-person-{{ $person->id }}">{{ $person->name }}</option> @endforeach
                    </select>
                    <select wire:model="stepItemId" aria-label="{{ __('bcm::plans.ui.system') }}" class="text-sm">
                        <option value="">{{ __('bcm::plans.ui.system') }}…</option>
                        @foreach ($linkedItems as $item) <option value="{{ $item->id }}" wire:key="step-item-{{ $item->id }}">{{ $item->name }}</option> @endforeach
                    </select>
                    <input type="text" wire:model="stepNotes" maxlength="2000" placeholder="{{ __('bcm::plans.ui.notes') }}" aria-label="{{ __('bcm::plans.ui.notes') }}" class="text-sm">
                    <div class="flex gap-2 sm:col-span-2">
                        <button type="submit" class="rounded-md bg-gray-900 px-3 py-1.5 text-sm font-semibold text-white">{{ __('bcm::plans.ui.save_step') }}</button>
                        <button type="button" wire:click="cancelStep" class="rounded-md bg-white px-3 py-1.5 text-sm font-semibold ring-1 ring-gray-300">{{ __('bcm::plans.ui.cancel') }}</button>
                    </div>
                </form>
            @endif
        @endif
    </section>

    @if ($editable)
        <div class="flex flex-wrap gap-2">
            <button type="button" wire:click="submit" wire:loading.attr="disabled" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white">{{ __('bcm::plans.ui.submit') }}</button>
        </div>
    @elseif ($version->status === \Modules\Bcm\Domain\Enums\VersionStatus::Submitted && ! $canDecide)
        <p class="text-sm text-gray-500">{{ __('bcm::plans.ui.waiting') }}</p>
    @elseif (! $canDecide)
        <p class="text-sm text-gray-500">{{ __('bcm::plans.ui.read_only') }}</p>
    @endif

    @if ($canDecide)
        <section aria-labelledby="decision-heading" class="space-y-3 rounded-lg bg-white p-4 ring-1 ring-gray-200">
            <h2 id="decision-heading" class="text-sm font-semibold">{{ __('bcm::plans.ui.decision') }}</h2>
            <label for="comment" class="block text-sm">{{ __('bcm::plans.ui.comment') }} <span class="text-xs text-gray-500">{{ __('bcm::plans.ui.comment_help') }}</span></label>
            <textarea id="comment" wire:model="comment" rows="3" maxlength="5000" class="block w-full text-sm"></textarea>
            @error('comment') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            <div class="flex flex-wrap gap-2">
                <button type="button" wire:click="approve" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white">{{ __('bcm::plans.ui.approve') }}</button>
                <button type="button" wire:click="sendBack" class="rounded-md bg-white px-4 py-2 text-sm font-semibold text-amber-800 ring-1 ring-gray-300">{{ __('bcm::plans.ui.send_back') }}</button>
            </div>
        </section>
    @endif
</div>
