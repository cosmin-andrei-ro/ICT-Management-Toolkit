<div>
@if (session('status'))
    <p class="mb-4 rounded-md bg-green-50 p-3 text-sm text-green-800" role="status">{{ session('status') }}</p>
@endif
<form wire:submit="submit" class="space-y-5" novalidate>
    <div>
        <label for="login" class="block text-sm font-medium">{{ __('core::auth.login.identifier') }}</label>
        <input wire:model="login" id="login" type="text" autocomplete="username" autocapitalize="none" spellcheck="false" required autofocus
               class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-gray-900 focus:ring-gray-900"
               @error('login') aria-invalid="true" aria-describedby="login-error" @enderror>
        @error('login')
            <p id="login-error" class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="password" class="block text-sm font-medium">{{ __('core::auth.login.password') }}</label>
        <input wire:model="password" id="password" type="password" autocomplete="current-password" required
               class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-gray-900 focus:ring-gray-900"
               @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
        @error('password')
            <p id="password-error" class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <label class="flex items-center gap-2 text-sm">
        <input wire:model="remember" type="checkbox" class="rounded border-gray-300">
        {{ __('core::auth.login.remember') }}
    </label>
    @if ($directoryEnabled)
        <p class="-mt-3 text-xs text-gray-500">{{ __('core::auth.login.remember_local_only') }}</p>
    @endif

    <button type="submit" wire:loading.attr="disabled"
            class="w-full rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700 disabled:opacity-60">
        {{ __('core::auth.login.submit') }}
    </button>
    <p class="text-center text-sm"><a href="{{ route('password.request') }}" class="text-gray-600 underline">{{ __('core::security.forgot.link') }}</a></p>
</form>
<div class="mt-6 space-y-3 border-t border-gray-200 pt-6">
    @if (session('sso_error')) <p class="rounded-md bg-red-50 p-2 text-sm text-red-800" role="alert">{{ session('sso_error') }}</p> @endif
    @if ($askSsoEmail)
        <form wire:submit="findSsoProvider" class="space-y-2">
            <label for="sso-email" class="block text-sm font-medium">{{ __('core::sso.find_label') }}</label>
            <input wire:model="ssoEmail" id="sso-email" type="email" autocomplete="email" maxlength="255" class="block w-full rounded-md border-gray-300 text-sm">
            @error('ssoEmail') <p class="text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
            <button type="submit" class="w-full rounded-md bg-white px-4 py-2 text-sm font-semibold ring-1 ring-gray-300 hover:bg-gray-50">{{ __('core::sso.find') }}</button>
        </form>
    @endif
    @foreach ($ssoProviders as $ssoProvider)
        <a href="{{ $ssoProvider['route'] }}" wire:key="sso-{{ $ssoProvider['id'] }}" class="block w-full rounded-md bg-white px-4 py-2 text-center text-sm font-semibold ring-1 ring-gray-300 hover:bg-gray-50">{{ __('core::sso.sign_in_with', ['provider' => $ssoProvider['name']]) }}</a>
    @endforeach
    <div x-data="webauthn" data-mode="get" data-start="passkeyOptions" data-finish="signInWithPasskey" data-unsupported="{{ __('core::security.keys.unsupported') }}" data-failed="{{ __('core::security.keys.cancelled') }}" class="space-y-2">
        <button type="button" x-on:click="run" x-bind:disabled="busy" class="w-full rounded-md bg-white px-4 py-2 text-sm font-semibold ring-1 ring-gray-300 hover:bg-gray-50 disabled:opacity-60">{{ __('core::security.keys.passkey_sign_in') }}</button>
        <p x-show="message" x-text="message" class="text-sm text-red-600" role="alert"></p>
    </div>
</div>
</div>
