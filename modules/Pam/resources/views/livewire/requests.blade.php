<div class="space-y-6">
    <header class="space-y-1">
        <a href="{{ route('pam.accounts.index') }}" class="text-sm text-gray-500 underline">← {{ __('pam::accounts.title') }}</a>
        <h1 class="text-xl font-semibold">{{ __('pam::accounts.requests.title') }}</h1>
        <p class="text-sm text-gray-600">{{ __('pam::accounts.requests.intro') }}</p>
    </header>

    @if ($requests->isEmpty())
        <p class="text-sm text-gray-500">{{ __('pam::accounts.requests.none') }}</p>
    @else
        <div class="grid gap-3 rounded-lg bg-white p-4 text-sm ring-1 ring-gray-200 sm:grid-cols-2">
            <div>
                <label for="code" class="block font-medium">{{ __('pam::accounts.ui.code') }}</label>
                <input id="code" type="text" wire:model="code" inputmode="numeric" autocomplete="one-time-code" maxlength="32" class="mt-1 block w-40">
                @error('code') <p class="mt-1 text-red-700">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="comment" class="block font-medium">{{ __('pam::accounts.requests.comment') }}</label>
                <input id="comment" type="text" wire:model="comment" maxlength="500" class="mt-1 block w-full">
                <p class="mt-1 text-xs text-gray-500">{{ __('pam::accounts.requests.comment_help') }}</p>
                @error('comment') <p class="mt-1 text-red-700">{{ $message }}</p> @enderror
            </div>
        </div>

        <ul class="divide-y divide-gray-100 rounded-lg bg-white text-sm ring-1 ring-gray-200">
            @foreach ($requests as $request)
                <li wire:key="request-{{ $request->id }}" class="flex flex-wrap items-center justify-between gap-3 p-4">
                    <div>
                        <p><span class="font-medium">{{ $request->user->name }}</span> · <a href="{{ route('pam.accounts.show', $request->account_id) }}" class="text-blue-700 underline">{{ $request->account->name }}</a></p>
                        <p class="text-gray-600">{{ $request->reason }}</p>
                        <p class="text-xs text-gray-500">{{ __('pam::accounts.requests.for_minutes', ['minutes' => $request->account->access_minutes]) }} · {{ $request->created_at?->diffForHumans() }}</p>
                    </div>
                    <div class="flex gap-2">
                        <button type="button" wire:click="approve('{{ $request->id }}')" class="rounded-md bg-gray-900 px-3 py-1.5 font-semibold text-white">{{ __('pam::accounts.requests.approve') }}</button>
                        <button type="button" wire:click="reject('{{ $request->id }}')" class="rounded-md bg-white px-3 py-1.5 font-semibold text-red-700 ring-1 ring-gray-300">{{ __('pam::accounts.requests.reject') }}</button>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</div>
