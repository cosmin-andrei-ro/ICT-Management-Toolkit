@props(['heat', 'settings', 'caption'])
{{-- A static 5 × 5 heat map (reports): likelihood rows from 5 down, impact columns, counts per cell. --}}
<table class="w-full max-w-xs table-fixed border-separate border-spacing-0.5 text-center text-xs">
    <caption class="pb-1 text-left text-xs font-medium">{{ $caption }}</caption>
    <tbody>
        @foreach ([5, 4, 3, 2, 1] as $likelihood)
            <tr>
                <th scope="row" class="w-6 font-normal text-gray-600">{{ $likelihood }}</th>
                @foreach ([1, 2, 3, 4, 5] as $impact)
                    @php($level = $settings->level($likelihood * $impact))
                    <td @class([
                        'h-8 rounded font-semibold',
                        'bg-green-100 text-green-900' => $level === \Modules\Risk\Domain\Enums\RiskLevel::Low,
                        'bg-yellow-100 text-yellow-900' => $level === \Modules\Risk\Domain\Enums\RiskLevel::Medium,
                        'bg-orange-200 text-orange-900' => $level === \Modules\Risk\Domain\Enums\RiskLevel::High,
                        'bg-red-200 text-red-900' => $level === \Modules\Risk\Domain\Enums\RiskLevel::Critical,
                    ])>{{ ($heat[$likelihood][$impact] ?? 0) ?: '' }}</td>
                @endforeach
            </tr>
        @endforeach
        <tr><td></td>@foreach ([1, 2, 3, 4, 5] as $impact) <th scope="col" class="font-normal text-gray-600">{{ $impact }}</th> @endforeach</tr>
    </tbody>
</table>
