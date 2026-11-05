<div class="mx-auto max-w-2xl space-y-8">
    <h1 class="text-xl font-semibold">{{ __('core::security.title') }}</h1>

    <section aria-labelledby="mfa-heading" class="space-y-4 rounded-lg bg-white p-5 ring-1 ring-gray-200">
        <h2 id="mfa-heading" class="font-semibold">{{ __('core::security.mfa.title') }}</h2>
        <p class="text-sm text-gray-600">{{ __('core::security.mfa.intro') }}</p>

        @if ($required && ! $enabled)
            <p class="rounded-md bg-amber-50 p-3 text-sm text-amber-800">{{ __('core::security.required') }}</p>
        @endif

        @if ($recoveryCodes !== [])
            <div class="space-y-2 rounded-md bg-gray-50 p-4">
                <p class="text-sm font-medium">{{ __('core::security.mfa.recovery_codes_intro') }}</p>
                <ul class="grid grid-cols-2 gap-1 font-mono text-sm">
                    @foreach ($recoveryCodes as $recoveryCode)
                        <li wire:key="recovery-{{ $loop->index }}">{{ $recoveryCode }}</li>
                    @endforeach
                </ul>
                <button type="button" wire:click="downloadRecoveryCodes" class="rounded-md bg-white px-3 py-1.5 text-sm font-semibold ring-1 ring-gray-300">{{ __('core::security.mfa.download_recovery_codes') }}</button>
            </div>
        @endif

        @if ($enabled)
            <p class="text-sm text-green-700">{{ __('core::security.mfa.on', ['count' => $remaining]) }}</p>
            <form wire:submit="regenerateRecoveryCodes" class="space-y-2">
                <label for="code" class="block text-sm font-medium">{{ __('core::security.mfa.confirm_code') }}</label>
                <input wire:model="code" id="code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="32" class="block w-48 rounded-md border-gray-300 text-sm">
                @error('code') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                <div class="flex flex-wrap gap-2">
                    <button type="submit" class="rounded-md bg-white px-3 py-1.5 text-sm font-semibold ring-1 ring-gray-300">{{ __('core::security.mfa.new_recovery_codes') }}</button>
                    <button type="button" wire:click="disable" class="rounded-md bg-white px-3 py-1.5 text-sm font-semibold text-red-700 ring-1 ring-gray-300">{{ __($required ? 'core::security.mfa.new_phone' : 'core::security.mfa.turn_off') }}</button>
                </div>
            </form>
        @elseif ($qrCode !== null)
            <ol class="list-decimal space-y-3 pl-5 text-sm">
                <li>{{ __('core::security.mfa.step_install') }}</li>
                <li>
                    {{ __('core::security.mfa.step_scan') }}
                    <img src="{{ $qrCode }}" alt="{{ __('core::security.mfa.qr_alt') }}" width="200" height="200" class="mt-2 rounded bg-white p-2 ring-1 ring-gray-200">
                    <p class="mt-1 text-xs text-gray-500">{{ __('core::security.mfa.manual_key') }} <span class="font-mono">{{ $secret }}</span></p>
                </li>
                <li>
                    <form wire:submit="enable" class="space-y-2">
                        <label for="code" class="block">{{ __('core::security.mfa.step_confirm') }}</label>
                        <input wire:model="code" id="code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="16" required class="block w-48 rounded-md border-gray-300 text-sm">
                        @error('code') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                        <button type="submit" class="rounded-md bg-gray-900 px-3 py-1.5 text-sm font-semibold text-white">{{ __('core::security.mfa.turn_on') }}</button>
                    </form>
                </li>
            </ol>
        @else
            <form wire:submit="startSetup" class="space-y-2">
                @if ($setupNeedsPassword)
                    <div>
                        <label for="setupPassword" class="block text-sm font-medium">{{ __('core::security.password.current') }}</label>
                        <input id="setupPassword" type="password" wire:model="setupPassword" autocomplete="current-password" class="mt-1 block w-64">
                        <p class="mt-1 text-xs text-gray-500">{{ __('core::security.mfa.set_up_password_help') }}</p>
                    </div>
                @endif
                @error('setupPassword') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
                <button type="submit" class="rounded-md bg-gray-900 px-3 py-1.5 text-sm font-semibold text-white">{{ __('core::security.mfa.set_up') }}</button>
            </form>
        @endif
    </section>

    @if ($enabled)
        <section aria-labelledby="keys-heading" class="space-y-4 rounded-lg bg-white p-5 ring-1 ring-gray-200">
            <h2 id="keys-heading" class="font-semibold">{{ __('core::security.keys.title') }}</h2>
            <p class="text-sm text-gray-600">{{ __('core::security.keys.intro') }}</p>

            <ul class="divide-y divide-gray-100 text-sm">
                @forelse ($securityKeys as $securityKey)
                    <li wire:key="key-{{ $securityKey->id }}" class="flex flex-wrap items-center justify-between gap-2 py-2">
                        <span>
                            <span class="font-medium">{{ $securityKey->name }}</span>
                            @if ($securityKey->is_passkey) <span class="rounded bg-green-100 px-1.5 py-0.5 text-xs font-semibold text-green-900">{{ __('core::security.keys.passkey') }}</span> @endif
                            <span class="block text-xs text-gray-500">{{ __('core::security.keys.added', ['date' => $securityKey->created_at?->toDateString()]) }} · {{ $securityKey->last_used_at ? __('core::security.keys.last_used', ['date' => $securityKey->last_used_at->toDateString()]) : __('core::security.keys.never_used') }}</span>
                        </span>
                        <button type="button" wire:click="removeKey('{{ $securityKey->id }}')" wire:confirm="{{ __('core::security.keys.remove_confirm') }}" class="text-xs text-red-700 underline">{{ __('core::security.keys.remove') }}</button>
                    </li>
                @empty
                    <li class="py-2 text-gray-500">{{ __('core::security.keys.none') }}</li>
                @endforelse
            </ul>

            @if ($keyAdded) <p class="rounded-md bg-green-50 p-2 text-sm text-green-800" role="status">{{ __('core::security.keys.added_now') }}</p> @endif

            <div x-data="webauthn" data-mode="create" data-start="keyOptions" data-finish="addKey" data-unsupported="{{ __('core::security.keys.unsupported') }}" data-failed="{{ __('core::security.keys.cancelled') }}" class="space-y-3 border-t border-gray-100 pt-4">
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="block text-sm">
                        <span class="font-medium">{{ __('core::security.keys.name') }}</span>
                        <input wire:model="keyName" type="text" maxlength="100" placeholder="{{ __('core::security.keys.name_example') }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                        @error('keyName') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                    </label>
                    <label class="block text-sm">
                        <span class="font-medium">{{ __('core::security.mfa.confirm_code') }}</span>
                        <input wire:model="keyCode" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="32" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                        @error('keyCode') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                    </label>
                </div>
                <p class="text-xs text-gray-500">{{ __('core::security.keys.code_help') }}</p>
                <button type="button" x-on:click="run" x-bind:disabled="busy" class="rounded-md bg-gray-900 px-3 py-1.5 text-sm font-semibold text-white disabled:opacity-60">{{ __('core::security.keys.add') }}</button>
                <p x-show="message" x-text="message" class="text-sm text-red-600" role="alert"></p>
            </div>
        </section>
    @endif

    <section aria-labelledby="password-heading" class="space-y-4 rounded-lg bg-white p-5 ring-1 ring-gray-200">
        <h2 id="password-heading" class="font-semibold">{{ __('core::security.password.title') }}</h2>
        @if ($user->isDirectoryAccount())
            <p class="text-sm text-gray-600">{{ __('core::security.password.directory') }}</p>
        @else
            @if (session('password_changed'))
                <p class="rounded-md bg-green-50 p-2 text-sm text-green-800">{{ session('password_changed') }}</p>
            @endif
            <form wire:submit="changePassword" class="space-y-3">
                <label class="block text-sm">
                    <span class="font-medium">{{ __('core::security.password.current') }}</span>
                    <input wire:model="currentPassword" type="password" autocomplete="current-password" required class="mt-1 block w-full rounded-md border-gray-300">
                    @error('currentPassword') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                </label>
                <label class="block text-sm">
                    <span class="font-medium">{{ __('core::security.password.new') }}</span>
                    <input wire:model="newPassword" type="password" autocomplete="new-password" required class="mt-1 block w-full rounded-md border-gray-300">
                    @error('newPassword') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                </label>
                <label class="block text-sm">
                    <span class="font-medium">{{ __('core::security.password.confirm') }}</span>
                    <input wire:model="newPasswordConfirmation" type="password" autocomplete="new-password" required class="mt-1 block w-full rounded-md border-gray-300">
                </label>
                <p class="text-xs text-gray-500">{{ $passwordHint }}</p>
                <button type="submit" class="rounded-md bg-gray-900 px-3 py-1.5 text-sm font-semibold text-white">{{ __('core::security.password.submit') }}</button>
            </form>
        @endif
    </section>

    @if ($pendingInvitations > 0)
        <section class="space-y-2 rounded-lg bg-white p-5 ring-1 ring-gray-200">
            <h2 class="font-semibold">{{ __('core::invitations.title') }}</h2>
            <p class="text-sm text-gray-600">{{ trans_choice('core::invitations.waiting', $pendingInvitations, ['count' => $pendingInvitations]) }}</p>
            <a href="{{ route('account.invitations') }}" class="inline-block rounded-md bg-white px-3 py-1.5 text-sm font-semibold ring-1 ring-gray-300">{{ __('core::invitations.see') }}</a>
        </section>
    @endif

    @can('core.api.use')
        <section class="space-y-2 rounded-lg bg-white p-5 ring-1 ring-gray-200">
            <h2 class="font-semibold">{{ __('core::api_tokens.title') }}</h2>
            <p class="text-sm text-gray-600">{{ __('core::api_tokens.intro') }}</p>
            <a href="{{ route('account.api-tokens') }}" class="inline-block rounded-md bg-white px-3 py-1.5 text-sm font-semibold ring-1 ring-gray-300">{{ __('core::api_tokens.menu') }}</a>
        </section>
    @endcan
</div>
