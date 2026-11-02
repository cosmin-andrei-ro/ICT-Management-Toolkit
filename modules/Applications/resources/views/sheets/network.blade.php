{{-- Network diagram (4.2) drawn from the firewall rules (4.3). --}}
@php($layout = \Modules\Applications\Application\Sheets\NetworkDiagram::layout($rules))
@php($w = \Modules\Applications\Application\Sheets\NetworkDiagram::BOX_WIDTH)
@php($h = \Modules\Applications\Application\Sheets\NetworkDiagram::BOX_HEIGHT)
<figure class="space-y-1">
    <div class="overflow-x-auto">
        <svg viewBox="0 0 {{ $layout['width'] }} {{ $layout['height'] }}" role="img" aria-labelledby="network-title" class="h-auto w-full rounded border border-gray-200 bg-white text-gray-900">
            <title id="network-title">{{ __('applications::sheet.ui.network_title', ['application' => $application->name]) }}</title>
            <defs><marker id="net-arrow" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse"><path d="M0,0 L10,5 L0,10 z" fill="currentColor"/></marker></defs>
            @foreach ($layout['edges'] as $index => $edge)
                @php($from = $layout['nodes'][$edge['from']])
                @php($to = $layout['nodes'][$edge['to']])
                @php($x1 = $from['x'] + ($to['x'] >= $from['x'] ? $w : 0))
                @php($x2 = $to['x'] + ($to['x'] >= $from['x'] ? 0 : $w))
                @php($y1 = $from['y'] + $h / 2)
                @php($y2 = $to['y'] + $h / 2)
                <line x1="{{ $x1 }}" y1="{{ $y1 }}" x2="{{ $x2 }}" y2="{{ $y2 }}" stroke="currentColor" marker-end="url(#net-arrow)"/>
                <text x="{{ ($x1 + $x2) / 2 }}" y="{{ ($y1 + $y2) / 2 - 5 }}" text-anchor="middle" font-size="10" fill="#374151">{{ \Illuminate\Support\Str::limit(trim($edge['port'].' '.($options->label('network_protocols', $edge['protocol']) ?? '')), 18) }}</text>
            @endforeach
            @foreach ($layout['nodes'] as $node)
                <rect x="{{ $node['x'] }}" y="{{ $node['y'] }}" width="{{ $w }}" height="{{ $h }}" rx="4" fill="#f9fafb" stroke="#374151"/>
                <text x="{{ $node['x'] + $w / 2 }}" y="{{ $node['y'] + 25 }}" text-anchor="middle" font-size="12">{{ \Illuminate\Support\Str::limit($node['name'], 22) }}</text>
            @endforeach
        </svg>
    </div>
    <figcaption class="text-xs text-gray-500">{{ __('applications::sheet.ui.network_caption') }}</figcaption>
</figure>
