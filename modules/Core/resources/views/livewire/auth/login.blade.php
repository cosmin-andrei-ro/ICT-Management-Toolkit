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
</div>
