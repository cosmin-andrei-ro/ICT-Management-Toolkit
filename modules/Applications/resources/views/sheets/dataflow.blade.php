{{-- Context data-flow diagram (DFD level 0) drawn from the integrations (2.4): the application in the middle, systems sending data on the left, systems receiving it on the right, exchanges both ways below. --}}
@php($bySide = ['inbound' => [], 'outbound' => [], 'bidirectional' => []])
@foreach ($integrations as $integration)
    @php($side = in_array($integration['direction'] ?? '', ['inbound', 'outbound'], true) ? $integration['direction'] : 'bidirectional')
    @php($bySide[$side][] = $integration)
@endforeach
@php($rows = max(1, count($bySide['inbound']), count($bySide['outbound'])))
@php($bottom = count($bySide['bidirectional']))
@php($height = max(150, 40 + $rows * 70) + ($bottom > 0 ? 130 : 0))
@php($centerY = max(75, 20 + $rows * 35))
@php($label = fn ($integration) => \Illuminate\Support\Str::limit(trim(($integration['name'] ?? '').(($integration['data'] ?? '') !== '' ? ' · '.$integration['data'] : '')), 48))
<figure class="space-y-1">
    <svg viewBox="0 0 900 {{ $height }}" role="img" aria-labelledby="dfd-title" class="h-auto w-full rounded border border-gray-200 bg-white text-gray-900">
        <title id="dfd-title">{{ __('applications::sheet.ui.dfd_title', ['application' => $application->name]) }}</title>
        <defs><marker id="dfd-arrow" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse"><path d="M0,0 L10,5 L0,10 z" fill="currentColor"/></marker></defs>
        <ellipse cx="450" cy="{{ $centerY }}" rx="105" ry="55" fill="#eff6ff" stroke="#1d4ed8" stroke-width="2"/>
        <text x="450" y="{{ $centerY - 4 }}" text-anchor="middle" font-size="13" font-weight="600">{{ \Illuminate\Support\Str::limit($application->name, 24) }}</text>
        <text x="450" y="{{ $centerY + 14 }}" text-anchor="middle" font-size="11" fill="#4b5563">{{ $application->code }}</text>
        @foreach (['inbound' => 20, 'outbound' => 700] as $side => $x)
            @foreach ($bySide[$side] as $index => $integration)
                @php($y = 25 + $index * 70)
                <rect x="{{ $x }}" y="{{ $y }}" width="180" height="40" fill="#f9fafb" stroke="#374151"/>
                <text x="{{ $x + 90 }}" y="{{ $y + 25 }}" text-anchor="middle" font-size="12">{{ \Illuminate\Support\Str::limit($integration['system'] ?? '', 24) }}</text>
                @if ($side === 'inbound')
                    <line x1="200" y1="{{ $y + 20 }}" x2="345" y2="{{ $centerY }}" stroke="currentColor" marker-end="url(#dfd-arrow)"/>
                    <text x="{{ 205 }}" y="{{ $y + 14 }}" font-size="10" fill="#374151">{{ $label($integration) }}</text>
                @else
                    <line x1="555" y1="{{ $centerY }}" x2="700" y2="{{ $y + 20 }}" stroke="currentColor" marker-end="url(#dfd-arrow)"/>
                    <text x="695" y="{{ $y + 14 }}" text-anchor="end" font-size="10" fill="#374151">{{ $label($integration) }}</text>
                @endif
            @endforeach
        @endforeach
        @foreach ($bySide['bidirectional'] as $index => $integration)
            @php($x = $bottom === 1 ? 360 : 20 + $index * (680 / max(1, $bottom - 1)))
            @php($y = $height - 60)
            <rect x="{{ $x }}" y="{{ $y }}" width="180" height="40" fill="#f9fafb" stroke="#374151"/>
            <text x="{{ $x + 90 }}" y="{{ $y + 25 }}" text-anchor="middle" font-size="12">{{ \Illuminate\Support\Str::limit($integration['system'] ?? '', 24) }}</text>
            <line x1="450" y1="{{ $centerY + 55 }}" x2="{{ $x + 90 }}" y2="{{ $y }}" stroke="currentColor" marker-start="url(#dfd-arrow)" marker-end="url(#dfd-arrow)"/>
            <text x="{{ ($x + 90 + 450) / 2 + 6 }}" y="{{ ($y + $centerY + 55) / 2 }}" font-size="10" fill="#374151">{{ $label($integration) }}</text>
        @endforeach
    </svg>
    <figcaption class="text-xs text-gray-500">{{ __('applications::sheet.ui.dfd_caption') }}</figcaption>
</figure>
