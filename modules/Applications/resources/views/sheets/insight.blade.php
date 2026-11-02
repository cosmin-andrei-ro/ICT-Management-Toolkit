{{-- Information from other modules or the template, never stored in the sheet. --}}
@php($duration = fn (?int $minutes): string => \Modules\Core\Domain\Time\Duration::format($minutes))
<div class="space-y-2 rounded-md bg-blue-50 p-3 text-sm text-blue-950">
    @switch($field->key)
        @case('bia')
            @php($assessed = array_filter($insights['processes'], fn ($process) => $process->rtoMinutes !== null))
            <p class="font-medium">{{ __('applications::sheet.ui.from_bia') }}</p>
            @if ($assessed === [])
                <p>{{ __('applications::sheet.ui.bia_none') }}</p>
            @else
                <ul class="space-y-0.5">
                    @foreach ($assessed as $process)
                        <li wire:key="bia-{{ $process->id }}">{{ $process->name }}: {{ __('applications::sheet.ui.bia_values', ['rto' => $duration($process->rtoMinutes), 'rpo' => $duration($process->rpoMinutes), 'tier' => $process->tier ?? '—']) }}</li>
                    @endforeach
                </ul>
                @php($strictest = collect($assessed)->min(fn ($process) => $process->rtoMinutes))
                @php($strictestRpo = collect($assessed)->filter(fn ($process) => $process->rpoMinutes !== null)->min(fn ($process) => $process->rpoMinutes))
                <p class="font-medium">{{ __('applications::sheet.ui.bia_strictest', ['rto' => $duration($strictest), 'rpo' => $duration($strictestRpo)]) }}</p>
            @endif
            @break
        @case('dependencies')
            <p class="font-medium">{{ __('applications::sheet.ui.from_cmdb') }}</p>
            @if ($insights['dependencies'] === [])
                <p>{{ __('applications::sheet.ui.cmdb_dependencies_none') }}</p>
            @else
                <ul class="space-y-0.5">
                    @foreach ($insights['dependencies'] as $item)
                        <li wire:key="dependency-{{ $item->id }}"><a href="{{ $item->url }}" class="underline">{{ $item->name }}</a> <span class="text-xs">{{ $item->typeName }} · {{ __('applications::sheet.ui.recovery_capability', ['value' => $duration($item->recoveryTimeMinutes)]) }}@if ($item->verifiedOn) · {{ __('applications::sheet.ui.tested_on', ['date' => $item->verifiedOn]) }}@endif</span></li>
                    @endforeach
                </ul>
            @endif
            @break
        @case('recovery_plans')
            <p class="font-medium">{{ __('applications::sheet.ui.from_bcm') }}</p>
            @if ($insights['plans'] === [])
                <p>{{ __('applications::sheet.ui.bcm_none') }}</p>
            @else
                <ul class="space-y-0.5">
                    @foreach ($insights['plans'] as $plan)
                        <li wire:key="plan-{{ $plan->id }}"><a href="{{ $plan->url }}" class="underline">{{ $plan->title }}</a> @if ($plan->status) <span class="text-xs">{{ $plan->status }}</span> @endif</li>
                    @endforeach
                </ul>
            @endif
            @break
        @case('glossary')
            <p class="font-medium">{{ __('applications::sheet.ui.standard_glossary') }}</p>
            <dl class="grid gap-x-4 gap-y-1 text-xs sm:grid-cols-2">
                @foreach ((array) trans('applications::glossary') as $term => $meaning)
                    <div wire:key="term-{{ $term }}"><dt class="inline font-semibold">{{ $term }}</dt> <dd class="inline">{{ $meaning }}</dd></div>
                @endforeach
            </dl>
            @break
    @endswitch
</div>
