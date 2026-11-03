<div class="mx-auto max-w-3xl space-y-6">
    <header class="space-y-1">
        <a href="{{ route('ticketing.changes.approvals') }}" class="text-sm text-gray-500 underline">{{ __('ticketing::changes.ui.back_to_approvals') }}</a>
        <h1 class="text-xl font-semibold"><span class="font-mono text-gray-500">{{ $change->number }}</span> {{ $change->title }}</h1>
        <p class="text-sm text-gray-600">
            {{ $details?->change_type->label() }} · {{ __('ticketing::changes.ui.risk') }}: {{ $details?->risk->label() }} ·
            {{ __('ticketing::ui.requested_by', ['name' => $change->requester->name ?? __('ticketing::retention.requester')]) }} · {{ $details?->approval_status->label() }}
        </p>
    </header>

    @if (session('decided'))
        <p class="rounded-md bg-green-50 p-3 text-sm text-green-800">{{ session('decided') }}</p>
    @endif

    <x-ticketing::affected-items :items="$configurationItems" />

    @if ($conflicts->isNotEmpty())
        <div class="rounded-md bg-amber-50 p-3 text-sm text-amber-900" role="alert">
            {{ __('ticketing::changes.ui.conflicts') }}
            <ul class="mt-1 list-disc pl-5">
                @foreach ($conflicts as $other)
                    <li wire:key="conflict-{{ $other->id }}">{{ $other->number }} — {{ $other->title }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="space-y-3 rounded-lg bg-white p-4 text-sm ring-1 ring-gray-200">
        <h2 class="font-semibold">{{ __('ticketing::changes.ui.reason') }}</h2>
        <p class="whitespace-pre-line text-gray-800">{{ $change->description }}</p>
        <h2 class="font-semibold">{{ __('ticketing::changes.ui.window') }}</h2>
        <p>{{ $details?->planned_start?->timezone(config('app.display_timezone'))->format('Y-m-d H:i') }} → {{ $details?->planned_end?->timezone(config('app.display_timezone'))->format('Y-m-d H:i') }} ({{ config('app.display_timezone') }})</p>
        @foreach (['implementation_plan', 'rollback_plan', 'test_plan'] as $field)
            <h2 class="font-semibold">{{ __('ticketing::changes.ui.'.$field) }}</h2>
            <p class="whitespace-pre-line text-gray-800">{{ $details?->{$field} ?? '—' }}</p>
        @endforeach
    </section>

    <section class="space-y-2 rounded-lg bg-white p-4 text-sm ring-1 ring-gray-200">
        <h2 class="font-semibold">{{ __('ticketing::changes.ui.decisions_so_far') }}</h2>
        <ul class="space-y-1">
            @forelse ($approvals as $approval)
                <li wire:key="approval-{{ $approval->id }}">
                    <span class="font-medium">{{ $approval->user->name }}</span>: {{ __('ticketing::changes.decisions.'.$approval->decision->value) }}
                    @if ($approval->comment) — <span class="whitespace-pre-line">{{ $approval->comment }}</span>@endif
                </li>
            @empty
                <li class="text-gray-500">{{ __('ticketing::changes.ui.no_decisions') }}</li>
            @endforelse
        </ul>
    </section>

    @can('decideOnChange', $change)
        <form wire:submit="approve" class="space-y-3 rounded-lg bg-white p-4 ring-1 ring-gray-200">
            <label class="block text-sm">
                <span class="font-medium">{{ __('ticketing::changes.ui.comment') }}</span>
                <textarea wire:model="comment" rows="3" class="mt-1 block w-full rounded-md border-gray-300"></textarea>
            </label>
            @error('comment') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            <div class="flex gap-3">
                <button type="submit" class="rounded-md bg-green-700 px-4 py-2 text-sm font-semibold text-white">{{ __('ticketing::changes.ui.approve') }}</button>
                <button type="button" wire:click="reject" class="rounded-md bg-red-700 px-4 py-2 text-sm font-semibold text-white">{{ __('ticketing::changes.ui.reject') }}</button>
            </div>
            <p class="text-xs text-gray-500">{{ __('ticketing::changes.ui.reject_help') }}</p>
        </form>
    @endcan
</div>
