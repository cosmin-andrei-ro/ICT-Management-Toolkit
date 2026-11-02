<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold">{{ __('audit::audits.title') }}</h1>
            <p class="text-sm text-gray-600">{{ __('audit::audits.ui.intro') }}</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('audit.findings.index') }}" class="rounded-md bg-white px-4 py-2 text-sm font-semibold ring-1 ring-gray-300">{{ __('audit::audits.findings.title') }}</a>
            @can('create', \Modules\Audit\Domain\Models\Audit::class)
                <a href="{{ route('audit.audits.create') }}" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white">{{ __('audit::audits.ui.new') }}</a>
            @endcan
        </div>
    </div>

    <div>
        <label for="status" class="block text-xs text-gray-600">{{ __('audit::audits.fields.status') }}</label>
        <select id="status" wire:model.live="status" class="mt-1 block">
            <option value="">{{ __('audit::compliance.ui.all') }}</option>
            @foreach ($statuses as $option) <option value="{{ $option->value }}" wire:key="status-{{ $option->value }}">{{ $option->label() }}</option> @endforeach
        </select>
    </div>

    <div class="overflow-x-auto rounded-lg bg-white ring-1 ring-gray-200">
        <table class="min-w-full text-left text-sm">
            <thead class="text-xs text-gray-500">
                <tr>
                    <th scope="col" class="px-4 py-2 font-medium">{{ __('audit::audits.fields.audit') }}</th>
                    <th scope="col" class="px-4 py-2 font-medium">{{ __('audit::audits.fields.kind') }}</th>
                    <th scope="col" class="px-4 py-2 font-medium">{{ __('audit::audits.fields.dates') }}</th>
                    <th scope="col" class="px-4 py-2 font-medium">{{ __('audit::audits.fields.lead_auditor') }}</th>
                    <th scope="col" class="px-4 py-2 font-medium">{{ __('audit::audits.fields.status') }}</th>
                    <th scope="col" class="px-4 py-2 font-medium">{{ __('audit::audits.findings.title') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($audits as $audit)
                    <tr wire:key="audit-{{ $audit->id }}">
                        <td class="px-4 py-2"><a href="{{ route('audit.audits.show', $audit) }}" class="text-blue-700 underline">{{ $audit->code() }} · {{ $audit->title }}</a></td>
                        <td class="px-4 py-2">{{ $audit->kind->label() }}@if ($audit->audit_body) <span class="block text-xs text-gray-500">{{ $audit->audit_body }}</span>@endif</td>
                        <td class="px-4 py-2">{{ $audit->starts_on->toDateString() }}@if ($audit->ends_on && ! $audit->ends_on->equalTo($audit->starts_on)) – {{ $audit->ends_on->toDateString() }}@endif</td>
                        <td class="px-4 py-2">{{ $audit->leadAuditor?->name ?? '—' }}</td>
                        <td class="px-4 py-2">{{ $audit->status->label() }}</td>
                        <td class="px-4 py-2">{{ __('audit::audits.ui.findings_count', ['open' => $audit->open_findings_count, 'total' => $audit->findings_count]) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-3 text-gray-500">{{ __('audit::audits.ui.none') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $audits->links() }}
</div>
