@php($timezone = config()->string('app.display_timezone'))
<main class="mx-auto max-w-4xl space-y-6 bg-white p-8 text-sm text-gray-900 print:max-w-none print:p-0">
    <header class="space-y-1 border-b border-gray-300 pb-4">
        <p class="text-gray-600">{{ $organization?->name }}</p>
        <h1 class="text-2xl font-semibold">{{ __('audit::report.soa_title', ['framework' => $framework->name]) }}</h1>
        <p class="text-gray-600">{{ __('audit::report.as_of', ['date' => now()->setTimezone($timezone)->format('Y-m-d H:i')]) }}@if ($framework->version) · {{ __('audit::compliance.fields.version') }} {{ $framework->version }}@endif</p>
        <p class="text-gray-600">{{ __('audit::report.soa_summary', ['total' => $coverage['total'], 'applicable' => $coverage['applicable'], 'excluded' => $coverage['states']['not_applicable'], 'undecided' => $coverage['states']['undecided']]) }}</p>
    </header>

    @foreach ($groups as $group => $requirements)
        <section class="space-y-2">
            @if ($group !== '') <h2 class="text-base font-semibold">{{ $group }}</h2> @endif
            <table class="min-w-full border border-gray-300 text-left text-xs">
                <thead class="bg-gray-100"><tr>
                    <th class="w-14 border border-gray-300 px-1.5 py-1">{{ __('audit::compliance.fields.reference') }}</th>
                    <th class="border border-gray-300 px-1.5 py-1">{{ __('audit::compliance.fields.requirement') }}</th>
                    <th class="w-20 border border-gray-300 px-1.5 py-1">{{ __('audit::report.included') }}</th>
                    <th class="border border-gray-300 px-1.5 py-1">{{ __('audit::compliance.fields.justification') }}</th>
                    <th class="border border-gray-300 px-1.5 py-1">{{ __('audit::compliance.fields.controls') }}</th>
                    <th class="w-24 border border-gray-300 px-1.5 py-1">{{ __('audit::compliance.fields.state') }}</th>
                </tr></thead>
                <tbody>
                    @foreach ($requirements as $requirement)
                        <tr class="break-inside-avoid align-top">
                            <td class="border border-gray-300 px-1.5 py-1 font-mono">{{ $requirement->reference }}</td>
                            <td class="border border-gray-300 px-1.5 py-1">{{ $requirement->title }}</td>
                            <td class="border border-gray-300 px-1.5 py-1">{{ match ($requirement->is_applicable) { true => __('audit::report.yes'), false => __('audit::report.no'), null => __('audit::report.undecided') } }}</td>
                            <td class="border border-gray-300 px-1.5 py-1 whitespace-pre-line">{{ $requirement->justification }}</td>
                            <td class="border border-gray-300 px-1.5 py-1">
                                @foreach ($requirement->controls as $control) <span class="block">{{ $control->code() }} {{ $control->name }} ({{ $control->status->label() }})</span> @endforeach
                            </td>
                            <td class="border border-gray-300 px-1.5 py-1">{{ $requirement->state()->label() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    @endforeach
</main>
