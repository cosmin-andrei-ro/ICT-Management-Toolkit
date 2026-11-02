<div class="overflow-x-auto">
    <table class="min-w-full text-left text-sm">
        <thead class="text-xs text-gray-500">
            <tr>
                <th scope="col" class="px-2 py-2 font-medium">{{ __('audit::audits.findings.fields.finding') }}</th>
                <th scope="col" class="px-2 py-2 font-medium">{{ __('audit::audits.findings.fields.severity') }}</th>
                <th scope="col" class="px-2 py-2 font-medium">{{ __('audit::audits.findings.fields.owner') }}</th>
                <th scope="col" class="px-2 py-2 font-medium">{{ __('audit::audits.findings.fields.due_on') }}</th>
                <th scope="col" class="px-2 py-2 font-medium">{{ __('audit::audits.findings.fields.status') }}</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse ($findings as $finding)
                <tr wire:key="finding-{{ $finding->id }}">
                    <td class="px-2 py-2">
                        <a href="{{ route('audit.findings.show', $finding) }}" class="text-blue-700 underline">{{ $finding->code() }} · {{ $finding->title }}</a>
                        @if ($finding->control) <span class="block text-xs text-gray-500">{{ $finding->control->code() }} {{ $finding->control->name }}</span> @endif
                    </td>
                    <td class="px-2 py-2"><x-audit::severity :severity="$finding->severity" /></td>
                    <td class="px-2 py-2">{{ $finding->owner?->name ?? '—' }}</td>
                    <td @class(['px-2 py-2', 'font-semibold text-red-700' => $finding->isOverdue($today)])>{{ $finding->due_on?->toDateString() ?? '—' }}</td>
                    <td class="px-2 py-2">{{ $finding->status->label() }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-2 py-3 text-gray-500">{{ __('audit::audits.findings.none') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
