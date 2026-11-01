<div class="space-y-6">
    <h1 class="text-xl font-semibold">{{ __('ticketing::changes.ui.approvals') }}</h1>
    <p class="text-sm text-gray-600">{{ __('ticketing::changes.ui.approvals_intro') }}</p>

    <ul class="space-y-3">
        @forelse ($waiting as $change)
            <li wire:key="waiting-{{ $change->id }}" class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-white p-4 ring-1 ring-gray-200">
                <div>
                    <a href="{{ route('ticketing.changes.review', $change) }}" class="font-semibold text-blue-700 underline"><span class="font-mono">{{ $change->number }}</span> {{ $change->title }}</a>
                    <p class="text-sm text-gray-600">
                        {{ $change->changeDetails?->change_type->label() }} · {{ __('ticketing::changes.ui.risk') }}: {{ $change->changeDetails?->risk->label() }} ·
                        {{ $change->changeDetails?->planned_start?->timezone(config('app.display_timezone'))->format('Y-m-d H:i') }}
                    </p>
                </div>
                <span class="text-sm text-gray-500">{{ $change->changeDetails?->cabBoard?->name }}</span>
            </li>
        @empty
            <li class="text-sm text-gray-500">{{ __('ticketing::changes.ui.nothing_to_approve') }}</li>
        @endforelse
    </ul>
</div>
