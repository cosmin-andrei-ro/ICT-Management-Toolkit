<div class="mx-auto max-w-3xl space-y-6">
    <div>
        <h1 class="text-xl font-semibold">{{ __('core::invitations.title') }}</h1>
        <p class="text-sm text-gray-600">{{ __('core::invitations.intro') }}</p>
    </div>

    @if (session('invitation_status'))
        <p class="rounded-md bg-green-50 p-3 text-sm text-green-800 ring-1 ring-green-200" role="status">{{ session('invitation_status') }}</p>
    @endif
    @if (session('invitation_error'))
        <p class="rounded-md bg-red-50 p-3 text-sm text-red-800 ring-1 ring-red-200" role="alert">{{ session('invitation_error') }}</p>
    @endif

    <ul class="space-y-3">
        @forelse ($invitations as $invitation)
            <li wire:key="invitation-{{ $invitation->id }}" class="space-y-2 rounded-lg bg-white p-5 ring-1 ring-gray-200">
                <p class="font-semibold">{{ $invitation->organization->name }}</p>
                <p class="text-sm text-gray-600">
                    {{ __('core::invitations.from', ['name' => $invitation->inviter?->name ?? '—', 'date' => $invitation->created_at?->toDateString()]) }}
                    · {{ __('core::invitations.expires', ['date' => $invitation->expires_at->toDateString()]) }}
                </p>
                <p class="text-sm"><span class="text-gray-500">{{ __('core::invitations.roles') }}:</span> {{ collect($invitation->role_ids)->map(fn ($id) => $roleNames[$id] ?? null)->filter()->join(', ') ?: '—' }}</p>
                <div class="flex gap-3">
                    <button type="button" wire:click="accept('{{ $invitation->id }}')" class="rounded-md bg-blue-700 px-3 py-1.5 text-sm font-semibold text-white">{{ __('core::invitations.accept') }}</button>
                    <button type="button" wire:click="decline('{{ $invitation->id }}')" class="rounded-md bg-white px-3 py-1.5 text-sm font-semibold ring-1 ring-gray-300">{{ __('core::invitations.decline') }}</button>
                </div>
            </li>
        @empty
            <li class="text-sm text-gray-500">{{ __('core::invitations.none') }}</li>
        @endforelse
    </ul>
</div>
