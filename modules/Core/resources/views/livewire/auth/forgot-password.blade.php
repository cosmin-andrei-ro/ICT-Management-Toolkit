<div class="space-y-5">
    @if ($sent)
        <p class="rounded-md bg-green-50 p-3 text-sm text-green-800" role="status">{{ __('core::security.forgot.sent') }}</p>
    @else
        <form wire:submit="send" class="space-y-5" novalidate>
            <p class="text-sm text-gray-600">{{ __('core::security.forgot.intro') }}</p>
            <div>
                <label for="email" class="block text-sm font-medium">{{ __('core::security.forgot.email') }}</label>
                <input wire:model="email" id="email" type="email" autocomplete="email" required autofocus
                       class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-gray-900 focus:ring-gray-900">
                @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <button type="submit" wire:loading.attr="disabled" class="w-full rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700 disabled:opacity-60">{{ __('core::security.forgot.submit') }}</button>
        </form>
    @endif
    <p class="text-center text-sm"><a href="{{ route('login') }}" class="text-gray-600 underline">{{ __('core::security.challenge.back') }}</a></p>
</div>
