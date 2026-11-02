@php($duration = fn (?int $minutes): string => \Modules\Core\Domain\Time\Duration::format($minutes))
<x-core::printable :title="$plan->name" :pdf="$pdf" :styles="$styles ?? ''" :back-url="route('bcm.plans.show', $plan)" :back-label="'← '.$plan->name" :pdf-url="$pdfAvailable ? route('bcm.plans.sheet.pdf', $plan) : null">
    <main class="mx-auto max-w-4xl space-y-6 bg-white p-8 text-sm print:max-w-none print:p-0">
        <header class="space-y-1 border-b border-gray-300 pb-4">
            <p class="text-gray-600">{{ $organization?->name }} · {{ $plan->type->label() }}</p>
            <h1 class="text-xl font-semibold">{{ $plan->name }}</h1>
            <p class="text-gray-600">
                {{ __('bcm::sheet.version', ['version' => $version->version]) }}
                @if ($version->decider && $version->decided_at) · {{ __('bcm::sheet.approved', ['name' => $version->decider->name, 'date' => $version->decided_at->timezone($timezone)->format('Y-m-d')]) }} @endif
                · {{ __('bcm::sheet.owner', ['name' => $plan->owner?->name ?? '—']) }}
                · {{ __('bcm::sheet.printed', ['date' => now()->timezone($timezone)->format('Y-m-d H:i')]) }}
            </p>
        </header>

        @if ($version->objective) <section><h2 class="font-semibold">{{ __('bcm::plans.fields.objective') }}</h2><p class="whitespace-pre-line">{{ $version->objective }}</p></section> @endif

        <section class="space-y-1">
            <h2 class="font-semibold">{{ __('bcm::plans.ui.scope') }}</h2>
            <ul class="list-disc pl-5">
                @foreach ($processes as $process) <li>{{ $process->name }} @if ($process->rtoMinutes !== null) ({{ __('bcm::plans.ui.rto', ['value' => $duration($process->rtoMinutes)]) }}) @endif</li> @endforeach
                @foreach ($items as $item) <li>{{ $item->name }} ({{ $item->typeName }})</li> @endforeach
            </ul>
            <p><span class="text-gray-500">{{ __('bcm::plans.fields.scenarios') }}:</span> {{ collect($version->scenarios)->map(fn ($value) => __('bcm::plans.scenarios.'.$value))->implode('; ') }}</p>
        </section>

        <section class="grid gap-4 sm:grid-cols-2 print:grid-cols-2">
            <div><h2 class="font-semibold">{{ __('bcm::plans.fields.activation_criteria') }}</h2><p class="whitespace-pre-line">{{ $version->activation_criteria ?? '—' }}</p></div>
            <div><h2 class="font-semibold">{{ __('bcm::plans.fields.activation_authority') }}</h2><p class="whitespace-pre-line">{{ $version->activation_authority ?? '—' }}</p></div>
        </section>

        <section class="space-y-2 break-inside-avoid">
            <h2 class="font-semibold">{{ __('bcm::plans.ui.team') }}</h2>
            <table class="w-full border border-gray-300">
                <thead class="bg-gray-50 text-xs"><tr><th class="border border-gray-300 px-2 py-1 text-left">{{ __('bcm::plans.ui.role') }}</th><th class="border border-gray-300 px-2 py-1 text-left">{{ __('bcm::sheet.who') }}</th><th class="border border-gray-300 px-2 py-1 text-left">{{ __('bcm::sheet.contact') }}</th><th class="border border-gray-300 px-2 py-1 text-left">{{ __('bcm::plans.ui.responsibilities') }}</th></tr></thead>
                <tbody>
                    @foreach ($team as $member)
                        <tr>
                            <td class="border border-gray-300 px-2 py-1">{{ $member->role }} @if ($member->is_deputy) ({{ __('bcm::plans.ui.deputy_flag') }}) @endif</td>
                            <td class="border border-gray-300 px-2 py-1">{{ $member->displayName() }}</td>
                            <td class="border border-gray-300 px-2 py-1">{{ $member->user?->email ?? collect([$member->contact_phone, $member->contact_email])->filter()->implode(' · ') }}</td>
                            <td class="border border-gray-300 px-2 py-1">{{ $member->responsibilities }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>

        <section class="space-y-2">
            <h2 class="font-semibold">{{ __('bcm::plans.ui.runbook') }}</h2>
            @if ($estimate !== null)
                <p class="text-gray-600">{{ __('bcm::plans.ui.estimate', ['value' => $duration($estimate)]) }} @if ($strictest !== null) {{ __('bcm::sheet.rto', ['value' => $duration($strictest->rtoMinutes), 'process' => $strictest->name]) }} @endif</p>
            @endif
            <table class="w-full border border-gray-300">
                <thead class="bg-gray-50 text-xs"><tr><th class="border border-gray-300 px-2 py-1">#</th><th class="border border-gray-300 px-2 py-1 text-left">{{ __('bcm::plans.ui.action') }}</th><th class="border border-gray-300 px-2 py-1 text-left">{{ __('bcm::sheet.who') }}</th><th class="border border-gray-300 px-2 py-1">{{ __('bcm::plans.ui.duration') }}</th><th class="border border-gray-300 px-2 py-1">{{ __('bcm::sheet.done') }}</th></tr></thead>
                <tbody>
                    @foreach ($steps as $step)
                        <tr class="break-inside-avoid align-top">
                            <td class="border border-gray-300 px-2 py-1 text-center">{{ $step->position }}</td>
                            <td class="border border-gray-300 px-2 py-1"><span class="text-xs uppercase text-gray-500">{{ $step->phase->label() }}</span><br><span class="whitespace-pre-line">{{ $step->action }}</span>@if ($step->configuration_item_id) <br><span class="text-xs text-gray-500">{{ $itemNames[$step->configuration_item_id] ?? '' }}</span>@endif @if ($step->notes) <br><span class="text-xs text-gray-500">{{ $step->notes }}</span>@endif</td>
                            <td class="border border-gray-300 px-2 py-1">{{ collect([$step->responsible, $step->responsibleUser?->name])->filter()->implode(' · ') }}</td>
                            <td class="border border-gray-300 px-2 py-1 text-center">{{ $step->estimated_minutes !== null ? $duration($step->estimated_minutes) : '' }}</td>
                            <td class="w-16 border border-gray-300 px-2 py-1"></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>

        @foreach (['strategy', 'communication', 'assumptions'] as $field)
            @if ($version->{$field}) <section class="break-inside-avoid"><h2 class="font-semibold">{{ __('bcm::plans.fields.'.$field) }}</h2><p class="whitespace-pre-line">{{ $version->{$field} }}</p></section> @endif
        @endforeach
    </main>
</x-core::printable>
