<form wire:submit="save" class="space-y-5" novalidate>
    <p class="text-sm text-gray-600">{{ __($invitation ? 'core::security.reset.welcome_intro' : 'core::security.reset.intro') }}</p>
    <div>
        <label for="email" class="block text-sm font-medium">{{ __('core::security.forgot.email') }}</label>
        <input wire:model="email" id="email" type="email" autocomplete="username" required
               class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-gray-900 focus:ring-gray-900">
        @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="password" class="block text-sm font-medium">{{ __('core::security.password.new') }}</label>
        <input wire:model="password" id="password" type="password" autocomplete="new-password" required autofocus
               class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-gray-900 focus:ring-gray-900">
        @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="password_confirmation" class="block text-sm font-medium">{{ __('core::security.password.confirm') }}</label>
        <input wire:model="passwordConfirmation" id="password_confirmation" type="password" autocomplete="new-password" required
               class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-gray-900 focus:ring-gray-900">
    </div>
    <p class="text-xs text-gray-500">{{ __('core::security.password.rules') }}</p>
    <button type="submit" wire:loading.attr="disabled" class="w-full rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700 disabled:opacity-60">{{ __('core::security.reset.submit') }}</button>
</form>
