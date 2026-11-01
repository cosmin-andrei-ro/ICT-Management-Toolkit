<form wire:submit="verify" class="space-y-5" novalidate>
    <p class="text-sm text-gray-600">{{ __('core::security.challenge.intro') }}</p>
    <div>
        <label for="code" class="block text-sm font-medium">{{ __('core::security.challenge.code') }}</label>
        <input wire:model="code" id="code" type="text" inputmode="numeric" autocomplete="one-time-code" autocapitalize="none" spellcheck="false" required autofocus maxlength="32"
               class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-gray-900 focus:ring-gray-900"
               @error('code') aria-invalid="true" aria-describedby="code-error" @enderror>
        @error('code')
            <p id="code-error" class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
        <p class="mt-1 text-xs text-gray-500">{{ __('core::security.challenge.recovery_hint') }}</p>
    </div>

    <button type="submit" wire:loading.attr="disabled"
            class="w-full rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700 disabled:opacity-60">
        {{ __('core::security.challenge.submit') }}
    </button>
    <p class="text-center text-sm"><a href="{{ route('login') }}" class="text-gray-600 underline">{{ __('core::security.challenge.back') }}</a></p>
</form>
