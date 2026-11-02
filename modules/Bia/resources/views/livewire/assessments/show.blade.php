<div class="space-y-6">
    <header class="space-y-1">
        <a href="{{ route('bia.processes.show', $process) }}" class="text-sm text-gray-500 underline">{{ __('bia::assessments.ui.back', ['process' => $process->name]) }}</a>
        <h1 class="flex flex-wrap items-center gap-2 text-xl font-semibold">
            {{ $process->name }} · {{ __('bia::assessments.ui.title', ['version' => $assessment->version]) }}
            <x-bia::assessment-status :status="$assessment->status" />
        </h1>
        @if ($assessment->submitter && $assessment->submitted_at)
            <p class="text-xs text-gray-500">{{ __('bia::assessments.ui.submitted_by', ['name' => $assessment->submitter->name, 'date' => $assessment->submitted_at->toDateString()]) }}</p>
        @endif
        @if ($assessment->decider && $assessment->decided_at)
            <p class="text-xs text-gray-500">{{ __('bia::assessments.ui.decided_by', ['status' => $assessment->status === \Modules\Bia\Domain\Enums\AssessmentStatus::Returned ? $assessment->status->label() : __('bia::assessments.statuses.approved'), 'name' => $assessment->decider->name, 'date' => $assessment->decided_at->toDateString()]) }}</p>
        @endif
        @if ($assessment->decision_comment)
            <p @class(['rounded-md p-3 text-sm', 'bg-amber-50 text-amber-900' => $assessment->status === \Modules\Bia\Domain\Enums\AssessmentStatus::Returned, 'bg-gray-50 text-gray-700' => $assessment->status !== \Modules\Bia\Domain\Enums\AssessmentStatus::Returned])>{{ $assessment->decision_comment }}</p>
        @endif
    </header>

    <form wire:submit="save" class="space-y-6" novalidate>
        <section aria-labelledby="matrix-heading" class="space-y-3 rounded-lg bg-white p-4 ring-1 ring-gray-200">
            <h2 id="matrix-heading" class="text-sm font-semibold">{{ __('bia::assessments.ui.matrix') }}</h2>
            @if ($editable)
                <p class="text-sm text-gray-600">{{ __('bia::assessments.ui.matrix_help') }}</p>
            @endif
            <p class="text-xs text-gray-500">{{ __('bia::assessments.ui.unacceptable', ['level' => $level]) }}</p>
            @error('scores') <p class="rounded-md bg-red-50 p-2 text-sm text-red-800" role="alert">{{ $message }}</p> @enderror

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-left text-xs text-gray-500">
                        <tr>
                            <th class="py-1 pr-3">{{ __('bia::assessments.ui.category') }}</th>
                            @foreach ($horizons as $horizon)
                                <th class="px-1 py-1 text-center" wire:key="horizon-{{ $horizon->id }}">{{ $horizon->label }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($categories as $category)
                            <tr wire:key="category-{{ $category->id }}" class="border-t border-gray-100">
                                <th scope="row" class="py-1.5 pr-3 text-left font-medium">
                                    {{ $category->name }}
                                    @if ($category->description) <span class="block text-xs font-normal text-gray-500">{{ $category->description }}</span> @endif
                                </th>
                                @foreach ($horizons as $horizon)
                                    @php($key = $category->id.'|'.$horizon->id)
                                    @php($value = (int) ($scores[$key] ?? 0))
                                    <td class="px-1 py-1.5 text-center" wire:key="cell-{{ $key }}">
                                        @if ($editable)
                                            <select wire:model="scores.{{ $key }}" aria-label="{{ $category->name }}, {{ $horizon->label }}"
                                                    @class(['w-16 text-center', 'border-red-400 bg-red-50 font-semibold text-red-800' => $value >= $level])>
                                                <option value="">–</option>
                                                @foreach (range(1, 5) as $option)
                                                    <option value="{{ $option }}" title="{{ $category->levelDescription($option) }}">{{ $option }}</option>
                                                @endforeach
                                            </select>
                                        @else
                                            <span @class(['inline-block w-8 rounded py-0.5', 'bg-red-50 font-semibold text-red-800' => $value >= $level, 'bg-gray-50' => $value < $level])>{{ $value ?: '–' }}</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <details class="text-xs text-gray-600">
                <summary class="cursor-pointer">{{ __('bia::settings.category.levels') }}</summary>
                <dl class="mt-2 grid gap-3 sm:grid-cols-2">
                    @foreach ($categories as $category)
                        <div wire:key="legend-{{ $category->id }}">
                            <dt class="font-semibold">{{ $category->name }}</dt>
                            @foreach (range(1, 5) as $option)
                                @if ($category->levelDescription($option)) <dd>{{ $option }} – {{ $category->levelDescription($option) }}</dd> @endif
                            @endforeach
                        </div>
                    @endforeach
                </dl>
            </details>
        </section>

        <section aria-labelledby="objectives-heading" class="space-y-4 rounded-lg bg-white p-4 ring-1 ring-gray-200">
            <h2 id="objectives-heading" class="text-sm font-semibold">{{ __('bia::assessments.ui.objectives') }}</h2>

            <div class="space-y-2">
                <p class="text-sm font-medium">{{ __('bia::assessments.ui.mtpd') }}</p>
                @if ($editable)
                    <p class="text-sm text-gray-600">{{ $computedMtpd !== null ? __('bia::assessments.ui.mtpd_computed', ['value' => \Modules\Bia\Domain\Duration::format($computedMtpd)]) : __('bia::assessments.ui.mtpd_none') }} <span class="text-xs text-gray-500">{{ __('bia::assessments.ui.mtpd_refresh') }}</span></p>
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model.live="overrideMtpd" class="rounded border-gray-300"> {{ __('bia::assessments.ui.override') }}</label>
                    @if ($overrideMtpd)
                        <div class="flex flex-wrap gap-2">
                            <input type="number" min="1" wire:model="mtpdValue" aria-label="{{ __('bia::assessments.ui.mtpd') }}" class="w-24 text-sm">
                            <select wire:model="mtpdUnit" aria-label="{{ __('bia::assessments.ui.mtpd') }}" class="text-sm">
                                @foreach ($units as $unit) <option value="{{ $unit }}">{{ __('bia::processes.unit_names.'.$unit) }}</option> @endforeach
                            </select>
                        </div>
                        @error('mtpdValue') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                        <textarea wire:model="mtpdReason" rows="2" maxlength="2000" placeholder="{{ __('bia::assessments.ui.override_reason') }}" aria-label="{{ __('bia::assessments.ui.override_reason') }}" class="block w-full text-sm"></textarea>
                        @error('mtpdReason') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                    @endif
                @else
                    <p class="text-sm">{{ \Modules\Bia\Domain\Duration::format($assessment->mtpd_minutes) }}</p>
                    @if ($assessment->mtpd_reason) <p class="whitespace-pre-line text-xs text-gray-500">{{ $assessment->mtpd_reason }}</p> @endif
                @endif
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                @foreach (['rto' => 'rtoValue', 'rpo' => 'rpoValue'] as $objective => $property)
                    <div wire:key="objective-{{ $objective }}">
                        <p class="text-sm font-medium">{{ __('bia::assessments.ui.'.$objective) }}</p>
                        <p class="text-xs text-gray-500">{{ __('bia::assessments.ui.'.$objective.'_help') }}</p>
                        @if ($editable)
                            <div class="mt-1 flex gap-2">
                                <input type="number" min="0" wire:model="{{ $property }}" aria-label="{{ __('bia::assessments.ui.'.$objective) }}" class="w-24 text-sm">
                                <select wire:model="{{ $objective }}Unit" aria-label="{{ __('bia::assessments.ui.'.$objective) }}" class="text-sm">
                                    @foreach ($units as $unit) <option value="{{ $unit }}">{{ __('bia::processes.unit_names.'.$unit) }}</option> @endforeach
                                </select>
                            </div>
                            @error($property) <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            @error($objective.'_minutes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        @else
                            <p class="mt-1 text-sm">{{ \Modules\Bia\Domain\Duration::format($assessment->{$objective.'_minutes'}) }}</p>
                        @endif
                    </div>
                @endforeach
            </div>

            @foreach (['mbco' => 'mbco', 'workarounds' => 'workarounds', 'notes' => 'notes'] as $field => $property)
                <div wire:key="text-{{ $field }}">
                    <label for="{{ $property }}" class="block text-sm font-medium">{{ __('bia::assessments.ui.'.$field) }}</label>
                    @if ($field === 'workarounds') <p class="text-xs text-gray-500">{{ __('bia::assessments.ui.workarounds_help') }}</p> @endif
                    @if ($editable)
                        <textarea id="{{ $property }}" wire:model="{{ $property }}" rows="2" maxlength="5000" class="mt-1 block w-full text-sm"></textarea>
                    @else
                        <p class="mt-1 whitespace-pre-line text-sm text-gray-700">{{ $assessment->{$field} ?? '—' }}</p>
                    @endif
                </div>
            @endforeach

            <div>
                <label for="minimumStaff" class="block text-sm font-medium">{{ __('bia::assessments.ui.minimum_staff') }}</label>
                @if ($editable)
                    <input id="minimumStaff" type="number" min="0" wire:model="minimumStaff" class="mt-1 block w-24 text-sm">
                @else
                    <p class="mt-1 text-sm">{{ $assessment->minimum_staff ?? '—' }}</p>
                @endif
            </div>
        </section>

        @if ($editable)
            <div class="flex flex-wrap gap-2">
                <button type="submit" wire:loading.attr="disabled" class="rounded-md bg-white px-4 py-2 text-sm font-semibold ring-1 ring-gray-300">{{ __('bia::assessments.ui.save') }}</button>
                <button type="button" wire:click="submit" wire:loading.attr="disabled" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white">{{ __('bia::assessments.ui.submit') }}</button>
            </div>
        @elseif ($assessment->status === \Modules\Bia\Domain\Enums\AssessmentStatus::Submitted && ! $canDecide)
            <p class="text-sm text-gray-500">{{ __('bia::assessments.ui.waiting') }}</p>
        @elseif (! $canDecide)
            <p class="text-sm text-gray-500">{{ __('bia::assessments.ui.read_only') }}</p>
        @endif
    </form>

    @if ($canDecide)
        <section aria-labelledby="decision-heading" class="space-y-3 rounded-lg bg-white p-4 ring-1 ring-gray-200">
            <h2 id="decision-heading" class="text-sm font-semibold">{{ __('bia::assessments.ui.decision') }}</h2>
            <label for="comment" class="block text-sm">{{ __('bia::assessments.ui.comment') }} <span class="text-xs text-gray-500">{{ __('bia::assessments.ui.comment_help') }}</span></label>
            <textarea id="comment" wire:model="comment" rows="3" maxlength="5000" class="block w-full text-sm"></textarea>
            @error('comment') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            <div class="flex flex-wrap gap-2">
                <button type="button" wire:click="approve" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white">{{ __('bia::assessments.ui.approve') }}</button>
                <button type="button" wire:click="sendBack" class="rounded-md bg-white px-4 py-2 text-sm font-semibold text-amber-800 ring-1 ring-gray-300">{{ __('bia::assessments.ui.send_back') }}</button>
            </div>
        </section>
    @endif
</div>
