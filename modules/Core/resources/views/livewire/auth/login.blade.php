<form wire:submit="login" class="space-y-5" novalidate>
    <div>
        <label for="email" class="block text-sm font-medium">{{ __('core::auth.login.email') }}</label>
        <input wire:model="email" id="email" type="email" autocomplete="username" required autofocus
               class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-gray-900 focus:ring-gray-900"
               @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
        @error('email')
            <p id="email-error" class="mt-1 text-sm text-red-600">{{ $message }}</p>
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

    <button type="submit" wire:loading.attr="disabled"
            class="w-full rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700 disabled:opacity-60">
        {{ __('core::auth.login.submit') }}
    </button>
</form>
