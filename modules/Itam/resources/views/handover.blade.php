<x-core::layouts.base :title="__('itam::handover.title').' '.$asset->asset_tag">
    <main class="mx-auto max-w-3xl space-y-6 bg-white p-8 text-sm print:max-w-none print:p-0">
        <div class="flex items-center justify-between gap-4 print:hidden">
            <a href="{{ url()->previous() }}" class="text-gray-500 underline">{{ __('itam::handover.back') }}</a>
            <button type="button" data-print class="rounded-md bg-gray-900 px-4 py-2 font-semibold text-white">{{ __('itam::handover.print') }}</button>
        </div>

        <header class="space-y-1 border-b border-gray-300 pb-4">
            <p class="text-gray-600">{{ $organization?->name }}</p>
            <h1 class="text-xl font-semibold">{{ __('itam::handover.title') }}</h1>
            <p class="text-gray-600">{{ __('itam::handover.reference', ['reference' => strtoupper(substr($assignment->id, -8))]) }} · {{ $assignment->assigned_at->timezone($timezone)->format('Y-m-d H:i') }}</p>
        </header>

        <section class="grid gap-4 sm:grid-cols-2 print:grid-cols-2">
            <div>
                <h2 class="text-xs font-semibold uppercase text-gray-500">{{ __('itam::handover.handed_by') }}</h2>
                <p>{{ $assignment->assigner?->name ?? '—' }}</p>
            </div>
            <div>
                <h2 class="text-xs font-semibold uppercase text-gray-500">{{ __('itam::handover.received_by') }}</h2>
                <p>{{ $assignment->user->name }}</p>
                <p class="text-gray-600">{{ $assignment->user->email }}</p>
            </div>
        </section>

        <section class="space-y-2">
            <h2 class="text-xs font-semibold uppercase text-gray-500">{{ __('itam::handover.equipment') }}</h2>
            <table class="w-full border border-gray-300">
                <tbody>
                    <tr class="border-b border-gray-200"><th class="w-1/3 bg-gray-50 px-3 py-1.5 text-left font-medium">{{ __('itam::assets.fields.asset_tag') }}</th><td class="px-3 py-1.5 font-mono">{{ $asset->asset_tag }}</td></tr>
                    <tr class="border-b border-gray-200"><th class="bg-gray-50 px-3 py-1.5 text-left font-medium">{{ __('itam::assets.fields.category') }}</th><td class="px-3 py-1.5">{{ $asset->category->name }}</td></tr>
                    <tr class="border-b border-gray-200"><th class="bg-gray-50 px-3 py-1.5 text-left font-medium">{{ __('itam::handover.make_model') }}</th><td class="px-3 py-1.5">{{ trim(($asset->manufacturer ?? '').' '.($asset->model ?? '')) ?: $asset->displayName() }}</td></tr>
                    <tr class="border-b border-gray-200"><th class="bg-gray-50 px-3 py-1.5 text-left font-medium">{{ __('itam::assets.fields.serial_number') }}</th><td class="px-3 py-1.5 font-mono">{{ $asset->serial_number ?? '—' }}</td></tr>
                    @foreach ($fields as $field)
                        @if (array_key_exists($field->key, $asset->properties))
                            <tr class="border-b border-gray-200"><th class="bg-gray-50 px-3 py-1.5 text-left font-medium">{{ $field->label }}</th><td class="px-3 py-1.5">{{ is_bool($asset->properties[$field->key]) ? __($asset->properties[$field->key] ? 'itam::assets.ui.yes' : 'itam::assets.ui.no') : $asset->properties[$field->key] }}</td></tr>
                        @endif
                    @endforeach
                    @if ($location)
                        <tr class="border-b border-gray-200"><th class="bg-gray-50 px-3 py-1.5 text-left font-medium">{{ __('itam::assets.fields.location') }}</th><td class="px-3 py-1.5">{{ $location }}</td></tr>
                    @endif
                    @if ($assignment->notes)
                        <tr><th class="bg-gray-50 px-3 py-1.5 text-left font-medium">{{ __('itam::assets.fields.notes') }}</th><td class="px-3 py-1.5">{{ $assignment->notes }}</td></tr>
                    @endif
                </tbody>
            </table>
        </section>

        <section class="space-y-2">
            <p>{{ __('itam::handover.statement') }}</p>
            @if ($assignment->acknowledged_at)
                <p class="rounded-md border border-green-300 bg-green-50 p-3 text-green-900">{{ __('itam::handover.acknowledged', ['date' => $assignment->acknowledged_at->timezone($timezone)->format('Y-m-d H:i'), 'ip' => $assignment->acknowledged_ip]) }}</p>
            @endif
        </section>

        <section class="grid grid-cols-2 gap-8 pt-8">
            <div class="border-t border-gray-400 pt-1 text-gray-600">{{ __('itam::handover.signature', ['name' => $assignment->assigner?->name ?? '']) }}</div>
            <div class="border-t border-gray-400 pt-1 text-gray-600">{{ __('itam::handover.signature', ['name' => $assignment->user->name]) }}</div>
        </section>

        @if ($assignment->returned_at)
            <section class="space-y-2 border-t border-gray-300 pt-4">
                <h2 class="text-base font-semibold">{{ __('itam::handover.return_title') }}</h2>
                <p>{{ __('itam::handover.returned', ['date' => $assignment->returned_at->timezone($timezone)->format('Y-m-d H:i'), 'name' => $assignment->receiver?->name ?? '—']) }}</p>
                @if ($assignment->return_notes) <p class="text-gray-600">{{ $assignment->return_notes }}</p> @endif
                <div class="grid grid-cols-2 gap-8 pt-8">
                    <div class="border-t border-gray-400 pt-1 text-gray-600">{{ __('itam::handover.signature', ['name' => $assignment->user->name]) }}</div>
                    <div class="border-t border-gray-400 pt-1 text-gray-600">{{ __('itam::handover.signature', ['name' => $assignment->receiver?->name ?? '']) }}</div>
                </div>
            </section>
        @endif
    </main>
</x-core::layouts.base>
